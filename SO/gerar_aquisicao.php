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
        f.nome AS fornecedor_nome,
        f.cnpj AS fornecedor_cnpj
    FROM oficios o
    JOIN secretarias s ON s.id = o.secretaria_id
    LEFT JOIN fornecedores f ON f.id = o.fornecedor_indicado_id
    WHERE o.id = ? AND o.status = 'APROVADO'
");
$stmt->execute([$id]);
$oficio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$oficio) {
    die('Solicitação não encontrada ou não está aprovada.');
}

$stmt_existing = $pdo->prepare("
    SELECT a.id, a.numero_aq, a.valor_total, a.status, f.nome AS fornecedor
    FROM aquisicoes a
    JOIN fornecedores f ON f.id = a.fornecedor_id
    WHERE a.oficio_id = ?
    ORDER BY a.id ASC
");
$stmt_existing->execute([$id]);
$aquisicoes_existentes = $stmt_existing->fetchAll(PDO::FETCH_ASSOC);

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$valor_total = 0.0;
foreach ($items as $item) {
    $valor_total += (float)$item['quantidade'] * (float)($item['valor_unitario'] ?? 0);
}

if (empty($_SESSION['csrf_gerar_aquisicao'])) {
    $_SESSION['csrf_gerar_aquisicao'] = bin2hex(random_bytes(32));
}
$csrf_gerar_aquisicao = (string)$_SESSION['csrf_gerar_aquisicao'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';

    if ($csrf_token === '' || !hash_equals($csrf_gerar_aquisicao, $csrf_token)) {
        $error = 'A sessão expirou. Atualize a página e tente novamente.';
    } else {
        try {
            if (!empty($aquisicoes_existentes)) {
                throw new DomainException('Esta solicitação já possui aquisição gerada.');
            }
            if (empty($items)) {
                throw new DomainException('Esta solicitação não possui itens para gerar aquisição.');
            }

            $fornecedor_id = (int)($oficio['fornecedor_indicado_id'] ?? 0);
            if ($fornecedor_id <= 0 || empty($oficio['fornecedor_nome'])) {
                throw new DomainException('Esta solicitação não possui fornecedor definido. O fornecedor deve ser informado antes dos itens.');
            }

            $pdo->beginTransaction();

            $stmt_lock = $pdo->prepare("
                SELECT id, status, criado_em, fornecedor_indicado_id
                FROM oficios
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt_lock->execute([$id]);
            $lock = $stmt_lock->fetch(PDO::FETCH_ASSOC);
            if (!$lock || (string)$lock['status'] !== 'APROVADO') {
                throw new DomainException('A solicitação foi alterada e não está mais disponível para gerar aquisição.');
            }
            if ((int)($lock['fornecedor_indicado_id'] ?? 0) !== $fornecedor_id) {
                throw new DomainException('O fornecedor da solicitação foi alterado. Atualize a página antes de continuar.');
            }

            $stmt_existente_lock = $pdo->prepare("SELECT id FROM aquisicoes WHERE oficio_id = ? LIMIT 1 FOR UPDATE");
            $stmt_existente_lock->execute([$id]);
            if ($stmt_existente_lock->fetchColumn()) {
                throw new DomainException('Já existe uma aquisição vinculada a esta solicitação.');
            }

            $numero_aq = generate_aquisicao_number($pdo);
            $codigo_entrega = generate_unique_code($pdo);
            $stmt_aq = $pdo->prepare("
                INSERT INTO aquisicoes (numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total, criado_em)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_aq->execute([
                $numero_aq,
                $codigo_entrega,
                $id,
                $fornecedor_id,
                $valor_total,
                $lock['criado_em'],
            ]);
            $aquisicao_id = (int)$pdo->lastInsertId();

            $stmt_item = $pdo->prepare("
                INSERT INTO itens_aquisicao (aquisicao_id, oficio_item_id, produto, quantidade, valor_unitario)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $stmt_item->execute([
                    $aquisicao_id,
                    (int)$item['id'],
                    $item['produto'],
                    (float)$item['quantidade'],
                    (float)($item['valor_unitario'] ?? 0),
                ]);
            }

            log_action(
                $pdo,
                'GERAR_AQUISICAO',
                'Aquisição ' . $numero_aq . ' gerada para a solicitação ' . $oficio['numero'] . ' usando o fornecedor previamente definido ' . $oficio['fornecedor_nome']
            );
            $pdo->commit();

            $_SESSION['csrf_gerar_aquisicao'] = bin2hex(random_bytes(32));
            flash_message('success', 'Aquisição ' . $numero_aq . ' gerada com sucesso para ' . $oficio['fornecedor_nome'] . '.');
            header('Location: aquisicoes_visualizar.php?id=' . $aquisicao_id, true, 303);
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
            $error = 'Não foi possível gerar a aquisição. Nenhuma alteração parcial foi mantida.';
        }
    }
}

$page_title = 'Gerar Aquisição: ' . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
.gerar-wrap{max-width:980px;margin:0 auto}.gerar-head{display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;margin-bottom:1.25rem}.gerar-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.25rem}.gerar-summary>div{padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px}.gerar-summary small{display:block;color:#64748b;margin-bottom:.3rem}.supplier-lock{background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:1rem;margin-bottom:1.25rem}.supplier-lock strong{display:block;font-size:1.05rem}.items-table{width:100%;border-collapse:collapse}.items-table th,.items-table td{padding:.7rem;border-bottom:1px solid #e2e8f0}.items-table th{text-align:left;background:#f8fafc}.items-table .num{text-align:right;white-space:nowrap}.gerar-actions{display:flex;justify-content:flex-end;gap:.75rem;padding-top:1.25rem;margin-top:1.25rem;border-top:1px solid #e2e8f0}@media(max-width:768px){.gerar-summary{grid-template-columns:1fr}.gerar-actions,.gerar-head{flex-direction:column;align-items:stretch}.gerar-actions .btn,.gerar-head .btn{width:100%;justify-content:center}}
</style>

<div class="gerar-wrap">
    <div class="gerar-head">
        <div><h3 style="margin:0;"><i class="fas fa-shopping-cart"></i> Gerar Aquisição</h3><small class="text-muted">Solicitação <?= htmlspecialchars($oficio['numero'], ENT_QUOTES, 'UTF-8') ?></small></div>
        <a href="oficios_lista.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if (!empty($aquisicoes_existentes)): ?>
        <div class="alert alert-info">Esta solicitação já possui aquisição gerada.</div>
        <?php foreach ($aquisicoes_existentes as $aq): ?>
            <a href="aquisicoes_visualizar.php?id=<?= (int)$aq['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> <?= htmlspecialchars($aq['numero_aq'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($aq['fornecedor'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="supplier-lock">
            <small>Fornecedor definido antes dos itens</small>
            <?php if (!empty($oficio['fornecedor_indicado_id'])): ?>
                <strong><i class="fas fa-truck" style="margin-right:.4rem;color:#2563eb;"></i><?= htmlspecialchars((string)$oficio['fornecedor_nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (!empty($oficio['fornecedor_cnpj'])): ?><span class="text-muted">CNPJ: <?= htmlspecialchars((string)$oficio['fornecedor_cnpj'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            <?php else: ?>
                <strong style="color:#b91c1c;">Fornecedor não informado</strong>
            <?php endif; ?>
        </div>

        <div class="gerar-summary">
            <div><small>Secretaria</small><strong><?= htmlspecialchars($oficio['secretaria'], ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div><small>Quantidade de itens</small><strong><?= count($items) ?></strong></div>
            <div><small>Valor total</small><strong><?= format_money($valor_total) ?></strong></div>
        </div>

        <div class="card">
            <div class="card-body">
                <div style="overflow-x:auto;">
                    <table class="items-table">
                        <thead><tr><th>Item</th><th>Unid.</th><th class="num">Qtd.</th><th class="num">Preço unit.</th><th class="num">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $total_item = (float)$item['quantidade'] * (float)($item['valor_unitario'] ?? 0); ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$item['produto'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($item['unidade'] ?? 'UN'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= number_format((float)$item['quantidade'], 2, ',', '.') ?></td>
                                <td class="num"><?= format_money((float)($item['valor_unitario'] ?? 0)) ?></td>
                                <td class="num"><strong><?= format_money($total_item) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" class="gerar-actions">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_gerar_aquisicao, ENT_QUOTES, 'UTF-8') ?>">
                    <a href="oficios_lista.php" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-primary" <?= empty($items) || empty($oficio['fornecedor_indicado_id']) ? 'disabled' : '' ?> onclick="return confirm('Gerar a aquisição com o fornecedor e preços já definidos nesta solicitação?')"><i class="fas fa-check"></i> Gerar aquisição</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'views/layout/footer.php'; ?>
