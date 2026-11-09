<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Solicitação inválida.');
}

$stmt = $pdo->prepare("
    SELECT
        o.*,
        s.nome AS secretaria,
        s.responsavel AS sec_responsavel,
        f.nome AS fornecedor_nome,
        f.cnpj AS fornecedor_cnpj
    FROM oficios o
    JOIN secretarias s ON s.id = o.secretaria_id
    LEFT JOIN fornecedores f ON f.id = o.fornecedor_indicado_id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$oficio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$oficio) {
    die('Solicitação não encontrada.');
}

$stmt_total_itens = $pdo->prepare("SELECT COUNT(*) FROM itens_oficio WHERE oficio_id = ?");
$stmt_total_itens->execute([$id]);
$total_itens_oficio = (int)$stmt_total_itens->fetchColumn();

/*
 * Compatibilidade com solicitações criadas antes do fluxo fornecedor -> itens.
 * Se já existem itens e ainda não há fornecedor, permitimos defini-lo na análise.
 * Solicitações novas continuam obrigadas a informar o fornecedor antes dos itens.
 */
$modo_compatibilidade_legado = (int)($oficio['fornecedor_indicado_id'] ?? 0) <= 0
    && $total_itens_oficio > 0;

$fornecedores_list = [];
if ($modo_compatibilidade_legado) {
    $fornecedores_list = $pdo->query("
        SELECT id, nome, cnpj
        FROM fornecedores
        ORDER BY nome
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($_SESSION['csrf_analise_oficio'])) {
    $_SESSION['csrf_analise_oficio'] = bin2hex(random_bytes(32));
}
$csrf_analise_oficio = (string)$_SESSION['csrf_analise_oficio'];
$error = null;
$justificativa_admin = '';
$fornecedor_post_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = is_scalar($_POST['status'] ?? null) ? (string)$_POST['status'] : '';
    $justificativa_admin = is_scalar($_POST['justificativa_admin'] ?? null)
        ? trim((string)$_POST['justificativa_admin'])
        : '';
    $csrf_token = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';
    $fornecedor_post_raw = is_scalar($_POST['fornecedor_id'] ?? null)
        ? trim((string)$_POST['fornecedor_id'])
        : '';
    $fornecedor_post_id = ctype_digit($fornecedor_post_raw) ? (int)$fornecedor_post_raw : 0;

    if ($csrf_token === '' || !hash_equals($csrf_analise_oficio, $csrf_token)) {
        $error = 'A sessão de análise expirou. Atualize a página e tente novamente.';
    } elseif (!in_array($status, ['APROVADO', 'REPROVADO'], true)) {
        $error = 'A decisão informada é inválida.';
    } elseif ((string)$oficio['status'] !== 'ENVIADO') {
        $error = 'Esta solicitação não está mais disponível para análise.';
    } elseif ($status === 'APROVADO' && (int)($oficio['fornecedor_indicado_id'] ?? 0) <= 0 && !$modo_compatibilidade_legado) {
        $error = 'Esta solicitação não possui fornecedor definido. Retorne à etapa de itens e informe o fornecedor antes de aprovar.';
    } elseif ($status === 'APROVADO' && $modo_compatibilidade_legado && $fornecedor_post_id <= 0) {
        $error = 'Selecione o fornecedor responsável por esta solicitação antes de aprovar.';
    }

    if ($error === null) {
        try {
            $pdo->beginTransaction();

            $stmt_lock = $pdo->prepare("
                SELECT id, numero, status, criado_em, fornecedor_indicado_id
                FROM oficios
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt_lock->execute([$id]);
            $oficio_atual = $stmt_lock->fetch(PDO::FETCH_ASSOC);

            if (!$oficio_atual || (string)$oficio_atual['status'] !== 'ENVIADO') {
                throw new DomainException('Esta solicitação foi alterada por outro usuário e não está mais disponível para análise.');
            }

            $fornecedor_id = (int)($oficio_atual['fornecedor_indicado_id'] ?? 0);
            $fornecedor_definido_na_analise = false;
            $aquisicao_id = null;
            $numero_aquisicao = null;
            $fornecedor_aprovado = null;

            if ($status === 'APROVADO') {
                $stmt_itens = $pdo->prepare("
                    SELECT id, produto, quantidade, valor_unitario
                    FROM itens_oficio
                    WHERE oficio_id = ?
                    ORDER BY id
                    FOR UPDATE
                ");
                $stmt_itens->execute([$id]);
                $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

                if (empty($itens)) {
                    throw new DomainException('Esta solicitação não possui itens para gerar a aquisição automaticamente.');
                }

                /*
                 * Regra transitória para solicitações antigas:
                 * os itens já existiam antes de o fornecedor passar a ser obrigatório.
                 */
                if ($fornecedor_id <= 0) {
                    if ($fornecedor_post_id <= 0) {
                        throw new DomainException('Fornecedor não definido. Selecione um fornecedor para concluir esta solicitação antiga.');
                    }

                    $stmt_fornecedor = $pdo->prepare("SELECT id, nome, cnpj FROM fornecedores WHERE id = ? FOR UPDATE");
                    $stmt_fornecedor->execute([$fornecedor_post_id]);
                    $fornecedor_aprovado = $stmt_fornecedor->fetch(PDO::FETCH_ASSOC);
                    if (!$fornecedor_aprovado) {
                        throw new DomainException('O fornecedor selecionado não foi encontrado. Atualize a página e tente novamente.');
                    }

                    $stmt_define_fornecedor = $pdo->prepare("
                        UPDATE oficios
                        SET fornecedor_indicado_id = ?
                        WHERE id = ?
                          AND fornecedor_indicado_id IS NULL
                    ");
                    $stmt_define_fornecedor->execute([$fornecedor_post_id, $id]);

                    if ($stmt_define_fornecedor->rowCount() !== 1) {
                        throw new DomainException('O fornecedor desta solicitação foi alterado por outro usuário. Atualize a página e tente novamente.');
                    }

                    $fornecedor_id = $fornecedor_post_id;
                    $fornecedor_definido_na_analise = true;
                } else {
                    $stmt_fornecedor = $pdo->prepare("SELECT id, nome, cnpj FROM fornecedores WHERE id = ? FOR UPDATE");
                    $stmt_fornecedor->execute([$fornecedor_id]);
                    $fornecedor_aprovado = $stmt_fornecedor->fetch(PDO::FETCH_ASSOC);
                    if (!$fornecedor_aprovado) {
                        throw new DomainException('O fornecedor desta solicitação não existe mais. Atualize os dados antes de aprovar.');
                    }
                }

                $stmt_existente = $pdo->prepare("SELECT id FROM aquisicoes WHERE oficio_id = ? ORDER BY id LIMIT 1 FOR UPDATE");
                $stmt_existente->execute([$id]);
                if ($stmt_existente->fetchColumn()) {
                    throw new DomainException('Esta solicitação já possui aquisição vinculada e não pode gerar outra automaticamente.');
                }

                $valor_total = 0.0;
                foreach ($itens as $item) {
                    $valor_total += (float)$item['quantidade'] * (float)($item['valor_unitario'] ?? 0);
                }

                $stmt_update = $pdo->prepare("UPDATE oficios SET status = 'APROVADO' WHERE id = ? AND status = 'ENVIADO'");
                $stmt_update->execute([$id]);
                if ($stmt_update->rowCount() !== 1) {
                    throw new DomainException('A solicitação foi alterada por outro usuário.');
                }

                $numero_aquisicao = generate_aquisicao_number($pdo);
                $codigo_entrega = generate_unique_code($pdo);
                $stmt_aquisicao = $pdo->prepare("
                    INSERT INTO aquisicoes (numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total, criado_em)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_aquisicao->execute([
                    $numero_aquisicao,
                    $codigo_entrega,
                    $id,
                    $fornecedor_id,
                    $valor_total,
                    $oficio_atual['criado_em'],
                ]);
                $aquisicao_id = (int)$pdo->lastInsertId();

                $stmt_item_aquisicao = $pdo->prepare("
                    INSERT INTO itens_aquisicao (aquisicao_id, oficio_item_id, produto, quantidade, valor_unitario)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($itens as $item) {
                    $stmt_item_aquisicao->execute([
                        $aquisicao_id,
                        (int)$item['id'],
                        $item['produto'],
                        (float)$item['quantidade'],
                        (float)($item['valor_unitario'] ?? 0),
                    ]);
                }
            } else {
                $stmt_update = $pdo->prepare("UPDATE oficios SET status = 'REPROVADO' WHERE id = ? AND status = 'ENVIADO'");
                $stmt_update->execute([$id]);
                if ($stmt_update->rowCount() !== 1) {
                    throw new DomainException('A solicitação foi alterada por outro usuário.');
                }
            }

            $detalhes = 'Solicitação ' . $oficio['numero'] . ' alterada para ' . $status;
            if ($status === 'APROVADO' && $fornecedor_aprovado) {
                $detalhes .= ' com aquisição ' . $numero_aquisicao . ' gerada para o fornecedor ' . $fornecedor_aprovado['nome'];
                if ($fornecedor_definido_na_analise) {
                    $detalhes .= ' (fornecedor definido na análise por compatibilidade com solicitação anterior ao novo fluxo)';
                }
            }
            if ($justificativa_admin !== '') {
                $detalhes .= '. Parecer: ' . mb_substr($justificativa_admin, 0, 500, 'UTF-8');
            }
            log_action($pdo, 'ANALISAR_SOLICITACAO', $detalhes);

            $pdo->commit();
            $_SESSION['csrf_analise_oficio'] = bin2hex(random_bytes(32));

            if ($status === 'APROVADO') {
                flash_message('success', 'Solicitação aprovada. A aquisição ' . $numero_aquisicao . ' foi gerada para ' . $fornecedor_aprovado['nome'] . '.');
                header('Location: aquisicoes_visualizar.php?id=' . $aquisicao_id, true, 303);
            } else {
                flash_message('danger', 'Solicitação ' . $oficio['numero'] . ' reprovada e arquivada.');
                header('Location: oficios_lista.php', true, 303);
            }
            exit;
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Não foi possível concluir a análise. Atualize a página e tente novamente.';
        }
    }
}

$page_title = 'Analisar Solicitação: ' . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
.analise-wrapper{width:100%}.analise-card{border-radius:14px;overflow:hidden}.analise-header{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}.resumo-oficio{background:#f8fafc;padding:1.15rem;border-radius:12px;margin-bottom:1.25rem;border:1px solid #e2e8f0}.resumo-oficio p{margin:0 0 .55rem;line-height:1.5}.resumo-oficio p:last-child{margin-bottom:0}.resumo-label{font-weight:800}.fornecedor-fixo{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:1rem;margin-bottom:1.25rem}.fornecedor-fixo strong{display:block;font-size:1rem;color:#0f172a}.fornecedor-fixo small{color:#475569}.fornecedor-legado{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:1rem;margin-bottom:1.25rem}.fornecedor-legado .form-group{margin:1rem 0 0}.fornecedor-legado .form-label{font-weight:800;color:#78350f}.fornecedor-legado .helper{display:block;color:#92400e;font-size:.8rem;margin-top:.45rem}.analise-form textarea{min-height:120px;resize:vertical}.analise-actions{display:flex;justify-content:flex-end;gap:.75rem;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #e2e8f0;flex-wrap:wrap}.btn-reprovar{background:#dc2626!important;border-color:#dc2626!important;color:#fff!important}.btn-aprovar{background:#15803d!important;border-color:#15803d!important;color:#fff!important}@media(max-width:768px){.analise-header,.fornecedor-fixo{flex-direction:column;align-items:stretch}.analise-actions{flex-direction:column-reverse}.analise-actions .btn,.analise-header .btn{width:100%;justify-content:center}}
</style>

<div class="analise-wrapper">
    <div class="card analise-card">
        <div class="card-body">
            <div class="analise-header">
                <h3 style="margin:0;"><i class="fas fa-search"></i> Analisar Solicitação</h3>
                <a href="oficios_visualizar.php?id=<?= (int)$id ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Ver detalhes</a>
            </div>

            <div class="resumo-oficio">
                <p><span class="resumo-label">Número:</span> <?= htmlspecialchars($oficio['numero'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><span class="resumo-label">Secretaria:</span> <?= htmlspecialchars($oficio['secretaria'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><span class="resumo-label">Justificativa:</span> <?= nl2br(htmlspecialchars((string)$oficio['justificativa'], ENT_QUOTES, 'UTF-8')) ?></p>
                <p><span class="resumo-label">Data:</span> <?= format_date($oficio['criado_em']) ?></p>
                <p><span class="resumo-label">Itens cadastrados:</span> <?= $total_itens_oficio ?></p>
            </div>

            <?php if (!empty($oficio['fornecedor_indicado_id'])): ?>
                <div class="fornecedor-fixo">
                    <div>
                        <small>Fornecedor definido antes dos itens</small>
                        <strong><i class="fas fa-truck" style="margin-right:.4rem;color:#2563eb;"></i><?= htmlspecialchars((string)$oficio['fornecedor_nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if (!empty($oficio['fornecedor_cnpj'])): ?><small>CNPJ: <?= htmlspecialchars((string)$oficio['fornecedor_cnpj'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                    </div>
                    <span class="badge badge-approved">Fornecedor bloqueado nesta etapa</span>
                </div>
            <?php elseif (!$modo_compatibilidade_legado): ?>
                <div class="alert alert-warning"><strong>Fornecedor não informado.</strong> Esta solicitação não possui itens legados para usar a regra de compatibilidade. Retorne à etapa de itens e defina o fornecedor primeiro.</div>
            <?php endif; ?>

            <?php if ($error !== null): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <form method="POST" class="analise-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_analise_oficio, ENT_QUOTES, 'UTF-8') ?>">

                <?php if ($modo_compatibilidade_legado): ?>
                    <div class="fornecedor-legado">
                        <strong><i class="fas fa-history" style="margin-right:.4rem;"></i>Solicitação anterior ao novo fluxo</strong>
                        <div style="margin-top:.35rem;color:#78350f;line-height:1.45;">
                            Esta solicitação já possui <?= $total_itens_oficio ?> item(ns) cadastrados, mas foi criada antes de o fornecedor se tornar obrigatório antes dos itens. Selecione o fornecedor para concluir a aprovação sem refazer os itens.
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="fornecedor_id">Fornecedor desta solicitação</label>
                            <select name="fornecedor_id" id="fornecedor_id" class="form-control" required>
                                <option value="">Selecione o fornecedor...</option>
                                <?php foreach ($fornecedores_list as $fornecedor): ?>
                                    <option value="<?= (int)$fornecedor['id'] ?>" <?= $fornecedor_post_id === (int)$fornecedor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$fornecedor['nome'], ENT_QUOTES, 'UTF-8') ?><?= !empty($fornecedor['cnpj']) ? ' — ' . htmlspecialchars((string)$fornecedor['cnpj'], ENT_QUOTES, 'UTF-8') : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="helper">Os itens e valores já cadastrados serão preservados. O fornecedor escolhido ficará gravado na solicitação e na aquisição gerada.</small>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Parecer / Observação da Administração</label>
                    <textarea name="justificativa_admin" class="form-control" placeholder="Descreva o motivo da aprovação ou reprovação..."><?= htmlspecialchars($justificativa_admin, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="analise-actions">
                    <button type="submit" name="status" value="REPROVADO" class="btn btn-reprovar" formnovalidate onclick="return confirm('Tem certeza que deseja REPROVAR esta solicitação?')"><i class="fas fa-times"></i> Reprovar e arquivar</button>
                    <button type="submit" name="status" value="APROVADO" class="btn btn-aprovar" <?= ((int)($oficio['fornecedor_indicado_id'] ?? 0) <= 0 && !$modo_compatibilidade_legado) ? 'disabled' : '' ?>><i class="fas fa-check"></i> Aprovar e gerar aquisição</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
