<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
sefaz_check();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Solicitação inválida.');
}

function fornecedor_fluxo_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fornecedor_fluxo_json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt_fluxo = $pdo->prepare("
    SELECT
        o.id,
        o.numero,
        o.status,
        o.fornecedor_indicado_id,
        s.nome AS secretaria,
        f.nome AS fornecedor_nome,
        f.cnpj AS fornecedor_cnpj,
        (SELECT COUNT(*) FROM itens_oficio io WHERE io.oficio_id = o.id) AS total_itens,
        (SELECT COUNT(*) FROM aquisicoes aq WHERE aq.oficio_id = o.id) AS total_aquisicoes
    FROM oficios o
    JOIN secretarias s ON s.id = o.secretaria_id
    LEFT JOIN fornecedores f ON f.id = o.fornecedor_indicado_id
    WHERE o.id = ?
");
$stmt_fluxo->execute([$id]);
$oficio_fluxo = $stmt_fluxo->fetch(PDO::FETCH_ASSOC);

if (!$oficio_fluxo) {
    die('Solicitação não encontrada.');
}

if (empty($_SESSION['csrf_fornecedor_antes_itens'])) {
    $_SESSION['csrf_fornecedor_antes_itens'] = bin2hex(random_bytes(32));
}
$csrf_fornecedor_fluxo = (string)$_SESSION['csrf_fornecedor_antes_itens'];

/*
|--------------------------------------------------------------------------
| AUTOCOMPLETE POR FORNECEDOR
|--------------------------------------------------------------------------
| A tela legada continua com todos os recursos existentes. Apenas a fonte
| das sugestões é interceptada aqui para impedir preço de outro fornecedor.
|--------------------------------------------------------------------------
*/
if (($_GET['ajax'] ?? '') === 'sugerir_itens') {
    $fornecedor_id = (int)($oficio_fluxo['fornecedor_indicado_id'] ?? 0);
    if ($fornecedor_id <= 0) {
        fornecedor_fluxo_json_error('Informe o fornecedor antes de pesquisar itens.', 409);
    }

    $termo = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($termo, 'UTF-8') < 2) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $termo_like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo);

        $stmt_sugestoes = $pdo->prepare("
            SELECT
                produto,
                unidade,
                CAST(
                    SUBSTRING_INDEX(
                        GROUP_CONCAT(valor_unitario ORDER BY prioridade ASC, ultima_data DESC, origem_id DESC SEPARATOR ','),
                        ',',
                        1
                    ) AS DECIMAL(15,2)
                ) AS valor_unitario,
                COUNT(*) AS usos,
                MAX(ultima_data) AS ultima_data,
                GROUP_CONCAT(DISTINCT origem SEPARATOR ',') AS origens
            FROM (
                SELECT
                    TRIM(ia.produto) AS produto,
                    COALESCE(NULLIF(TRIM(io.unidade), ''), 'UN') AS unidade,
                    COALESCE(ia.valor_unitario, 0) AS valor_unitario,
                    COALESCE(a.criado_em, NOW()) AS ultima_data,
                    ia.id AS origem_id,
                    0 AS prioridade,
                    'Aquisições' AS origem
                FROM itens_aquisicao ia
                JOIN aquisicoes a ON a.id = ia.aquisicao_id
                LEFT JOIN itens_oficio io ON io.id = ia.oficio_item_id
                WHERE a.fornecedor_id = :fornecedor_aquisicao
                  AND ia.produto LIKE :termo_aquisicao ESCAPE '\\\\'

                UNION ALL

                SELECT
                    TRIM(io.produto) AS produto,
                    COALESCE(NULLIF(TRIM(io.unidade), ''), 'UN') AS unidade,
                    COALESCE(io.valor_unitario, 0) AS valor_unitario,
                    COALESCE(o.criado_em, NOW()) AS ultima_data,
                    io.id AS origem_id,
                    1 AS prioridade,
                    'Solicitações' AS origem
                FROM itens_oficio io
                JOIN oficios o ON o.id = io.oficio_id
                WHERE o.fornecedor_indicado_id = :fornecedor_oficio
                  AND o.id <> :oficio_atual
                  AND io.produto LIKE :termo_oficio ESCAPE '\\\\'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM aquisicoes aq_hist
                      WHERE aq_hist.oficio_id = o.id
                  )
            ) historico
            WHERE produto <> ''
            GROUP BY produto, unidade
            ORDER BY
                CASE WHEN produto LIKE :termo_prefixo ESCAPE '\\\\' THEN 0 ELSE 1 END,
                ultima_data DESC,
                usos DESC,
                produto ASC
            LIMIT 15
        ");

        $stmt_sugestoes->execute([
            ':fornecedor_aquisicao' => $fornecedor_id,
            ':termo_aquisicao' => '%' . $termo_like . '%',
            ':fornecedor_oficio' => $fornecedor_id,
            ':oficio_atual' => $id,
            ':termo_oficio' => '%' . $termo_like . '%',
            ':termo_prefixo' => $termo_like . '%',
        ]);

        $resultado = [];
        foreach ($stmt_sugestoes->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $origens = array_filter(array_map('trim', explode(',', (string)($row['origens'] ?? ''))));
            $resultado[] = [
                'produto' => (string)$row['produto'],
                'unidade' => (string)($row['unidade'] ?: 'UN'),
                'valor_unitario' => (float)($row['valor_unitario'] ?? 0),
                'usos' => (int)($row['usos'] ?? 0),
                'origem' => !empty($origens) ? implode(' + ', array_unique($origens)) : 'Histórico do fornecedor',
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        fornecedor_fluxo_json_error('Não foi possível consultar o histórico deste fornecedor.', 500);
    }
    exit;
}

/*
|--------------------------------------------------------------------------
| DEFINIÇÃO DO FORNECEDOR ANTES DOS ITENS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['acao'] ?? '') === 'definir_fornecedor') {
    $csrf = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';
    $fornecedor_post = $_POST['fornecedor_id'] ?? null;

    try {
        if ($csrf === '' || !hash_equals($csrf_fornecedor_fluxo, $csrf)) {
            throw new DomainException('A sessão expirou. Atualize a página e tente novamente.');
        }
        if (!is_scalar($fornecedor_post) || !ctype_digit((string)$fornecedor_post) || (int)$fornecedor_post <= 0) {
            throw new DomainException('Selecione um fornecedor válido.');
        }

        $novo_fornecedor_id = (int)$fornecedor_post;
        $stmt_fornecedor = $pdo->prepare('SELECT id, nome FROM fornecedores WHERE id = ?');
        $stmt_fornecedor->execute([$novo_fornecedor_id]);
        $fornecedor = $stmt_fornecedor->fetch(PDO::FETCH_ASSOC);
        if (!$fornecedor) {
            throw new DomainException('O fornecedor selecionado não existe.');
        }

        $pdo->beginTransaction();
        $stmt_lock = $pdo->prepare("
            SELECT id, numero, status, fornecedor_indicado_id,
                   (SELECT COUNT(*) FROM aquisicoes aq WHERE aq.oficio_id = oficios.id) AS total_aquisicoes
            FROM oficios
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt_lock->execute([$id]);
        $lock = $stmt_lock->fetch(PDO::FETCH_ASSOC);

        if (!$lock) {
            throw new DomainException('Solicitação não encontrada.');
        }
        if ((int)$lock['total_aquisicoes'] > 0) {
            throw new DomainException('Esta solicitação já possui aquisição. O fornecedor não pode ser alterado.');
        }
        if (!in_array((string)$lock['status'], ['PENDENTE_ITENS', 'ENVIADO'], true)) {
            throw new DomainException('O fornecedor não pode ser definido no status atual da solicitação.');
        }
        if ((int)($lock['fornecedor_indicado_id'] ?? 0) > 0 && (int)$lock['fornecedor_indicado_id'] !== $novo_fornecedor_id) {
            throw new DomainException('Esta solicitação já possui fornecedor definido.');
        }

        $stmt_update = $pdo->prepare('UPDATE oficios SET fornecedor_indicado_id = ? WHERE id = ?');
        $stmt_update->execute([$novo_fornecedor_id, $id]);

        log_action(
            $pdo,
            'DEFINIR_FORNECEDOR_SOLICITACAO',
            'Fornecedor ' . $fornecedor['nome'] . ' definido antes dos itens na solicitação ' . $lock['numero']
        );
        $pdo->commit();

        $_SESSION['csrf_fornecedor_antes_itens'] = bin2hex(random_bytes(32));
        flash_message('success', 'Fornecedor definido. A pesquisa de itens agora utilizará somente os preços deste fornecedor.');
        header('Location: atribuir_itens.php?id=' . $id, true, 303);
        exit;
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro_fornecedor_fluxo = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro_fornecedor_fluxo = 'Não foi possível definir o fornecedor. Tente novamente.';
    }
}

/* Se ainda não há fornecedor, não liberamos a tela de itens. */
if ((int)($oficio_fluxo['fornecedor_indicado_id'] ?? 0) <= 0) {
    $fornecedores_fluxo = $pdo->query('SELECT id, nome, cnpj FROM fornecedores ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    $page_title = 'Informar Fornecedor - ' . $oficio_fluxo['numero'];
    include 'views/layout/header.php';
?>
<style>
    .supplier-step-wrap{max-width:820px;margin:0 auto}.supplier-step-head{margin-bottom:1.25rem}.supplier-step-progress{display:flex;align-items:center;gap:.55rem;color:#64748b;font-size:.8rem;font-weight:800;margin-top:.45rem}.supplier-step-progress .active{color:#0054a6}.supplier-step-card{border-radius:14px}.supplier-step-help{padding:1rem;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff;color:#1e3a8a;margin-bottom:1.25rem;line-height:1.45}.supplier-step-actions{display:flex;justify-content:flex-end;gap:.75rem;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #e2e8f0}@media(max-width:700px){.supplier-step-actions{flex-direction:column-reverse}.supplier-step-actions .btn{width:100%;justify-content:center}}
</style>
<div class="supplier-step-wrap">
    <div class="supplier-step-head">
        <h2 style="margin:0;">Informar fornecedor</h2>
        <div class="supplier-step-progress"><span class="active">1. Fornecedor</span><i class="fas fa-chevron-right"></i><span>2. Itens e preços</span><i class="fas fa-chevron-right"></i><span>3. Aprovação</span></div>
    </div>

    <?php if (!empty($erro_fornecedor_fluxo)): ?>
        <div class="alert alert-danger"><?php echo fornecedor_fluxo_h($erro_fornecedor_fluxo); ?></div>
    <?php endif; ?>

    <div class="card supplier-step-card">
        <div class="card-body">
            <div class="supplier-step-help">
                <i class="fas fa-info-circle"></i>
                Escolha primeiro o fornecedor da solicitação. Na próxima etapa, as sugestões de itens e valores serão obtidas somente do histórico desse fornecedor, evitando usar preço de outra empresa.
            </div>

            <div style="margin-bottom:1rem;">
                <small class="text-muted">Solicitação</small><br>
                <strong><?php echo fornecedor_fluxo_h($oficio_fluxo['numero']); ?></strong><br>
                <span class="text-muted"><?php echo fornecedor_fluxo_h($oficio_fluxo['secretaria']); ?></span>
            </div>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="acao" value="definir_fornecedor">
                <input type="hidden" name="csrf_token" value="<?php echo fornecedor_fluxo_h($csrf_fornecedor_fluxo); ?>">
                <div class="form-group">
                    <label class="form-label" for="fornecedor-fluxo">Fornecedor</label>
                    <select name="fornecedor_id" id="fornecedor-fluxo" class="form-control" required>
                        <option value="">Selecione o fornecedor...</option>
                        <?php foreach ($fornecedores_fluxo as $fornecedor): ?>
                            <option value="<?php echo (int)$fornecedor['id']; ?>">
                                <?php echo fornecedor_fluxo_h($fornecedor['nome']); ?><?php echo !empty($fornecedor['cnpj']) ? ' - ' . fornecedor_fluxo_h($fornecedor['cnpj']) : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="supplier-step-actions">
                    <a href="oficios_lista_sefaz.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Voltar</a>
                    <button type="submit" class="btn btn-primary" <?php echo empty($fornecedores_fluxo) ? 'disabled' : ''; ?>><i class="fas fa-arrow-right"></i> Confirmar e adicionar itens</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
    include 'views/layout/footer.php';
    exit;
}

/*
 * Fornecedor já definido: mantém integralmente a tela anterior e todos os
 * recursos existentes (importação, geração de linhas, cálculos e validações).
 * O AJAX dessa tela sempre volta a este wrapper e, portanto, fica filtrado.
 */
require __DIR__ . '/atribuir_itens_legacy.php';
