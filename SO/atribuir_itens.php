<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
sefaz_check();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Solicitação inválida.');
}

function ai_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ai_parse_money($valor): float
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return 0.0;
    }

    $valor = str_ireplace('R$', '', $valor);
    $valor = preg_replace('/\s+/', '', $valor);

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
        $valor = str_replace('.', '', $valor);
    }

    if (!is_numeric($valor)) {
        throw new DomainException('Informe um valor monetário válido.');
    }

    $valor = (float)$valor;
    if ($valor < 0) {
        throw new DomainException('Valores monetários não podem ser negativos.');
    }

    return $valor;
}

function ai_parse_quantity($valor, int $itemIndex): float
{
    $valor = str_replace(',', '.', trim((string)$valor));
    if ($valor === '' || !is_numeric($valor)) {
        throw new DomainException('Informe uma quantidade válida para o item ' . ($itemIndex + 1) . '.');
    }

    $valor = (float)$valor;
    if ($valor <= 0) {
        throw new DomainException('A quantidade do item ' . ($itemIndex + 1) . ' deve ser maior que zero.');
    }

    return $valor;
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
    WHERE o.id = ?
");
$stmt->execute([$id]);
$oficio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$oficio) {
    die('Solicitação não encontrada.');
}

$fornecedores = $pdo->query("SELECT id, nome, cnpj FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores_by_id = [];
foreach ($fornecedores as $fornecedor) {
    $fornecedores_by_id[(int)$fornecedor['id']] = $fornecedor;
}

if (empty($_SESSION['csrf_atribuir_itens'])) {
    $_SESSION['csrf_atribuir_itens'] = bin2hex(random_bytes(32));
}
$csrf_atribuir_itens = (string)$_SESSION['csrf_atribuir_itens'];

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC");
$stmt_items->execute([$id]);
$items_existentes = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$stmt_aquisicao = $pdo->prepare("SELECT id FROM aquisicoes WHERE oficio_id = ? ORDER BY id LIMIT 1");
$stmt_aquisicao->execute([$id]);
$aquisicao_existente_id = (int)($stmt_aquisicao->fetchColumn() ?: 0);

/*
 * Autocomplete estritamente vinculado ao fornecedor previamente informado.
 * Prioridade: aquisições efetivamente geradas para o fornecedor; em seguida,
 * solicitações indicadas para ele que ainda não geraram aquisição.
 */
if (($_GET['ajax'] ?? '') === 'sugerir_itens') {
    header('Content-Type: application/json; charset=utf-8');

    $termo = trim((string)($_GET['q'] ?? ''));
    $fornecedor_id = (int)($oficio['fornecedor_indicado_id'] ?? 0);

    if ($fornecedor_id <= 0) {
        http_response_code(409);
        echo json_encode(['erro' => 'Informe o fornecedor antes de pesquisar itens.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($termo, 'UTF-8') < 2) {
        echo json_encode([]);
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
                GROUP_CONCAT(DISTINCT origem ORDER BY prioridade ASC SEPARATOR ',') AS origens
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
                WHERE a.fornecedor_id = :fornecedor_aq
                  AND ia.produto LIKE :termo_aq ESCAPE '\\\\'

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
            ':fornecedor_aq' => $fornecedor_id,
            ':termo_aq' => '%' . $termo_like . '%',
            ':fornecedor_oficio' => $fornecedor_id,
            ':oficio_atual' => $id,
            ':termo_oficio' => '%' . $termo_like . '%',
            ':termo_prefixo' => $termo_like . '%',
        ]);

        $resultado = [];
        foreach ($stmt_sugestoes->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resultado[] = [
                'produto' => (string)$row['produto'],
                'unidade' => (string)($row['unidade'] ?: 'UN'),
                'valor_unitario' => (float)($row['valor_unitario'] ?? 0),
                'usos' => (int)($row['usos'] ?? 0),
                'origem' => (string)($row['origens'] ?: 'Histórico do fornecedor'),
            ];
        }

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Não foi possível consultar o histórico deste fornecedor.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = is_scalar($_POST['acao'] ?? null) ? (string)$_POST['acao'] : 'salvar_itens';
    $csrf_token = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';

    if ($csrf_token === '' || !hash_equals($csrf_atribuir_itens, $csrf_token)) {
        $error = 'A sessão expirou. Atualize a página e tente novamente.';
    } elseif ($acao === 'definir_fornecedor') {
        try {
            $fornecedor_post = $_POST['fornecedor_id'] ?? null;
            if (!is_scalar($fornecedor_post) || !ctype_digit((string)$fornecedor_post)) {
                throw new DomainException('Selecione um fornecedor válido.');
            }

            $novo_fornecedor_id = (int)$fornecedor_post;
            if ($novo_fornecedor_id <= 0 || !isset($fornecedores_by_id[$novo_fornecedor_id])) {
                throw new DomainException('O fornecedor selecionado não é válido.');
            }

            if ($aquisicao_existente_id > 0) {
                throw new DomainException('Esta solicitação já possui aquisição. O fornecedor não pode mais ser alterado.');
            }

            $fornecedor_atual_id = (int)($oficio['fornecedor_indicado_id'] ?? 0);
            if ($fornecedor_atual_id > 0 && $fornecedor_atual_id !== $novo_fornecedor_id && !empty($items_existentes)) {
                throw new DomainException('Não é permitido trocar o fornecedor depois que os itens foram preenchidos. Revise a solicitação antes de continuar.');
            }

            if (!in_array((string)$oficio['status'], ['PENDENTE_ITENS', 'ENVIADO'], true)) {
                throw new DomainException('O fornecedor não pode ser alterado no status atual da solicitação.');
            }

            $pdo->beginTransaction();
            $stmt_lock = $pdo->prepare("SELECT fornecedor_indicado_id, status FROM oficios WHERE id = ? FOR UPDATE");
            $stmt_lock->execute([$id]);
            $lock = $stmt_lock->fetch(PDO::FETCH_ASSOC);
            if (!$lock) {
                throw new DomainException('Solicitação não encontrada.');
            }

            $pdo->prepare("UPDATE oficios SET fornecedor_indicado_id = ? WHERE id = ?")->execute([$novo_fornecedor_id, $id]);
            log_action(
                $pdo,
                'DEFINIR_FORNECEDOR_SOLICITACAO',
                'Fornecedor ' . $fornecedores_by_id[$novo_fornecedor_id]['nome'] . ' definido para a solicitação ' . $oficio['numero']
            );
            $pdo->commit();

            $_SESSION['csrf_atribuir_itens'] = bin2hex(random_bytes(32));
            flash_message('success', 'Fornecedor definido. Agora os itens e preços serão pesquisados somente no histórico desse fornecedor.');
            header('Location: atribuir_itens.php?id=' . $id, true, 303);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof DomainException ? $e->getMessage() : 'Não foi possível definir o fornecedor.';
        }
    } elseif ($acao === 'salvar_itens') {
        try {
            $fornecedor_id = (int)($oficio['fornecedor_indicado_id'] ?? 0);
            if ($fornecedor_id <= 0 || !isset($fornecedores_by_id[$fornecedor_id])) {
                throw new DomainException('Informe o fornecedor antes de adicionar os itens.');
            }

            if (!in_array((string)$oficio['status'], ['PENDENTE_ITENS', 'ENVIADO'], true)) {
                throw new DomainException('Os itens não podem ser alterados no status atual da solicitação.');
            }

            $produtos = $_POST['produtos'] ?? [];
            if (!is_array($produtos)) {
                $produtos = [];
            }

            $orcamento_esperado = (float)($oficio['valor_orcamento'] ?? 0);
            $total_calculado = 0.0;
            $itens_sanitizados = [];

            foreach ($produtos as $idx => $p) {
                if (!is_array($p)) {
                    continue;
                }

                $nome = trim((string)($p['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }

                $qtd = ai_parse_quantity($p['qtd'] ?? '', (int)$idx);
                $unidade = strtoupper(trim((string)($p['unidade'] ?? 'UN')));
                $valor_unitario = ai_parse_money($p['valor'] ?? '0');

                if ($unidade === '') {
                    $unidade = 'UN';
                }

                $total_calculado += $qtd * $valor_unitario;
                $itens_sanitizados[] = [
                    'produto' => $nome,
                    'quantidade' => $qtd,
                    'unidade' => $unidade,
                    'valor_unitario' => $valor_unitario,
                ];
            }

            if (empty($itens_sanitizados)) {
                throw new DomainException('Informe pelo menos um item para a solicitação.');
            }

            if ($orcamento_esperado > 0 && abs($total_calculado - $orcamento_esperado) > 0.02) {
                throw new DomainException(
                    'O total dos itens deve ser exatamente igual ao orçamento previsto de R$ ' . number_format($orcamento_esperado, 2, ',', '.') . '.'
                );
            }

            $pdo->beginTransaction();
            $stmt_lock = $pdo->prepare("
                SELECT status, fornecedor_indicado_id, valor_orcamento
                FROM oficios
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt_lock->execute([$id]);
            $lock = $stmt_lock->fetch(PDO::FETCH_ASSOC);

            if (!$lock) {
                throw new DomainException('Solicitação não encontrada.');
            }
            if ((int)($lock['fornecedor_indicado_id'] ?? 0) !== $fornecedor_id) {
                throw new DomainException('O fornecedor desta solicitação foi alterado por outro usuário. Atualize a página.');
            }
            if (!in_array((string)$lock['status'], ['PENDENTE_ITENS', 'ENVIADO'], true)) {
                throw new DomainException('A solicitação foi alterada por outro usuário e não aceita mais edição de itens.');
            }

            $stmt_aq_lock = $pdo->prepare("SELECT id FROM aquisicoes WHERE oficio_id = ? LIMIT 1 FOR UPDATE");
            $stmt_aq_lock->execute([$id]);
            if ($stmt_aq_lock->fetchColumn()) {
                throw new DomainException('Esta solicitação já possui aquisição e os itens não podem mais ser alterados.');
            }

            $pdo->prepare("DELETE FROM itens_oficio WHERE oficio_id = ?")->execute([$id]);
            $stmt_ins = $pdo->prepare("
                INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($itens_sanitizados as $item) {
                $stmt_ins->execute([
                    $id,
                    $item['produto'],
                    $item['quantidade'],
                    $item['unidade'],
                    $item['valor_unitario'],
                ]);
            }

            $pdo->prepare("UPDATE oficios SET status = 'ENVIADO' WHERE id = ?")->execute([$id]);
            log_action(
                $pdo,
                'ATRIBUIR_ITENS',
                count($itens_sanitizados) . ' item(ns) atribuídos à solicitação ' . $oficio['numero'] . ' usando o fornecedor ' . $oficio['fornecedor_nome']
            );
            $pdo->commit();

            $_SESSION['csrf_atribuir_itens'] = bin2hex(random_bytes(32));
            flash_message('success', 'Itens atribuídos com sucesso. A solicitação foi enviada para aprovação.');
            header('Location: oficios_lista_sefaz.php', true, 303);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof DomainException ? $e->getMessage() : 'Não foi possível salvar os itens.';
        }
    }
}

$items_form = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['acao'] ?? '') === 'salvar_itens') {
    foreach (($_POST['produtos'] ?? []) as $p) {
        if (!is_array($p)) {
            continue;
        }
        $items_form[] = [
            'produto' => (string)($p['nome'] ?? ''),
            'quantidade_input' => (string)($p['qtd'] ?? '1'),
            'unidade' => (string)($p['unidade'] ?? 'UN'),
            'valor_input' => (string)($p['valor'] ?? ''),
            'valor_unitario' => 0,
        ];
    }
}

$items = !empty($items_form)
    ? $items_form
    : (!empty($items_existentes)
        ? $items_existentes
        : [['produto' => '', 'quantidade' => 1, 'unidade' => 'UN', 'valor_unitario' => 0]]);

$page_title = 'Atribuir Itens - ' . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
.ai-toolbar{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem}.ai-supplier{border:1px solid #bfdbfe;background:#eff6ff;border-radius:14px;padding:1rem 1.15rem;margin-bottom:1.5rem;display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap}.ai-supplier strong{display:block;color:#0f172a;font-size:1rem}.ai-supplier small{color:#475569}.ai-step{display:flex;align-items:center;gap:.65rem;font-size:.82rem;font-weight:800;color:#64748b;margin-bottom:1rem}.ai-step .active{color:#0369a1}.ai-step i{font-size:.65rem}.budget-info{background:#f8fafc;padding:1.15rem;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:1.5rem;display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap}.total-calc{font-size:1.2rem;font-weight:800}.diff-warning{color:#dc2626}.diff-ok{color:#15803d}.item-count-control{display:flex;align-items:end;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px}.item-count-control .form-group{min-width:180px;margin:0}.item-row{display:grid;grid-template-columns:60px minmax(240px,2fr) 110px 110px 140px 150px 44px;gap:.75rem;align-items:end;padding:1rem;margin-bottom:.85rem;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.item-seq{text-align:center;font-weight:800;background:#f8fafc}.item-name-group{position:relative}.item-suggestions{position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:80;display:none;max-height:330px;overflow:auto;background:#fff;border:1px solid #cbd5e1;border-radius:12px;box-shadow:0 18px 45px rgba(15,23,42,.16);padding:.4rem}.item-suggestions.show{display:block}.suggestion-option{width:100%;border:0;background:transparent;text-align:left;cursor:pointer;border-radius:9px;padding:.7rem}.suggestion-option:hover,.suggestion-option.active{background:#eff6ff}.suggestion-title{display:flex;justify-content:space-between;gap:.75rem;font-weight:800}.suggestion-price{color:#15803d;white-space:nowrap}.suggestion-meta{margin-top:.35rem;font-size:.76rem;color:#64748b;font-weight:700;display:flex;gap:.5rem;flex-wrap:wrap}.suggestion-chip{background:#f1f5f9;border-radius:999px;padding:.18rem .45rem}.suggestion-empty{padding:.8rem;color:#64748b;font-size:.84rem;font-weight:700}.supplier-choice-card{max-width:760px;margin:0 auto}.supplier-choice-card .card-body{padding:1.5rem}.supplier-choice-help{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.9rem;margin-bottom:1rem;color:#475569;font-size:.9rem}.item-total{font-weight:800;color:#15803d}@media(max-width:1100px){.item-row{grid-template-columns:60px 2fr 1fr 1fr}.item-row>div:nth-child(6),.item-row>div:nth-child(7){grid-column:auto}}@media(max-width:768px){.item-row{grid-template-columns:1fr}.ai-toolbar,.budget-info,.ai-supplier{align-items:stretch;flex-direction:column}.ai-toolbar .btn{width:100%;justify-content:center}.item-count-control{display:grid;grid-template-columns:1fr}.supplier-choice-card{max-width:none}}
</style>

<div class="ai-toolbar">
    <div>
        <h3 style="margin:0;"><i class="fas fa-box-open"></i> Itens da Solicitação - <?= ai_h($oficio['numero']) ?></h3>
        <div class="ai-step" style="margin-top:.45rem;margin-bottom:0;">
            <span class="<?= empty($oficio['fornecedor_indicado_id']) ? 'active' : '' ?>">1. Fornecedor</span><i class="fas fa-chevron-right"></i><span class="<?= !empty($oficio['fornecedor_indicado_id']) ? 'active' : '' ?>">2. Itens e preços</span><i class="fas fa-chevron-right"></i><span>3. Aprovação</span>
        </div>
    </div>
    <a href="oficios_lista_sefaz.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-danger"><?= ai_h($error) ?></div>
<?php endif; ?>

<?php if (empty($oficio['fornecedor_indicado_id'])): ?>
    <div class="card supplier-choice-card">
        <div class="card-body">
            <h3 style="margin-top:0;"><i class="fas fa-truck"></i> Informe o fornecedor antes dos itens</h3>
            <div class="supplier-choice-help">
                A partir do fornecedor selecionado, a pesquisa de itens mostrará somente o histórico de produtos e preços desse fornecedor. O sistema não utilizará preço de outro fornecedor automaticamente.
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="definir_fornecedor">
                <input type="hidden" name="csrf_token" value="<?= ai_h($csrf_atribuir_itens) ?>">
                <div class="form-group">
                    <label class="form-label">Fornecedor</label>
                    <select name="fornecedor_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($fornecedores as $fornecedor): ?>
                            <option value="<?= (int)$fornecedor['id'] ?>">
                                <?= ai_h($fornecedor['nome']) ?><?= !empty($fornecedor['cnpj']) ? ' - ' . ai_h($fornecedor['cnpj']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="text-align:right;margin-top:1rem;">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-arrow-right"></i> Confirmar fornecedor e continuar</button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="ai-supplier">
        <div>
            <small>Fornecedor desta solicitação</small>
            <strong><i class="fas fa-truck" style="margin-right:.4rem;color:#0369a1;"></i><?= ai_h($oficio['fornecedor_nome']) ?></strong>
            <?php if (!empty($oficio['fornecedor_cnpj'])): ?><small>CNPJ: <?= ai_h($oficio['fornecedor_cnpj']) ?></small><?php endif; ?>
        </div>
        <?php if (empty($items_existentes) && $aquisicao_existente_id === 0 && (string)$oficio['status'] === 'PENDENTE_ITENS'): ?>
            <form method="POST" style="display:flex;gap:.5rem;align-items:end;flex-wrap:wrap;">
                <input type="hidden" name="acao" value="definir_fornecedor">
                <input type="hidden" name="csrf_token" value="<?= ai_h($csrf_atribuir_itens) ?>">
                <div class="form-group" style="margin:0;min-width:260px;">
                    <label class="form-label">Alterar fornecedor</label>
                    <select name="fornecedor_id" class="form-control">
                        <?php foreach ($fornecedores as $fornecedor): ?>
                            <option value="<?= (int)$fornecedor['id'] ?>" <?= (int)$oficio['fornecedor_indicado_id'] === (int)$fornecedor['id'] ? 'selected' : '' ?>><?= ai_h($fornecedor['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-outline btn-sm" type="submit">Atualizar</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="budget-info">
                <div><span class="text-muted">Secretaria:</span> <strong><?= ai_h($oficio['secretaria']) ?></strong><br><span class="text-muted">Orçamento previsto:</span> <strong id="orcamento-previsto" data-valor="<?= (float)($oficio['valor_orcamento'] ?? 0) ?>"><?= !empty($oficio['valor_orcamento']) ? format_money($oficio['valor_orcamento']) : 'Não informado' ?></strong></div>
                <div style="text-align:right;"><span class="text-muted">Total atual:</span><br><span id="total-itens" class="total-calc">R$ 0,00</span></div>
            </div>

            <div style="padding:.8rem 1rem;border-radius:10px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;margin-bottom:1rem;font-size:.88rem;font-weight:700;">
                <i class="fas fa-check-circle"></i> A busca abaixo usa somente preços históricos de <strong><?= ai_h($oficio['fornecedor_nome']) ?></strong>. Se um item nunca foi usado com este fornecedor, informe-o manualmente.
            </div>

            <form method="POST" id="items-form">
                <input type="hidden" name="acao" value="salvar_itens">
                <input type="hidden" name="csrf_token" value="<?= ai_h($csrf_atribuir_itens) ?>">

                <div class="item-count-control">
                    <div class="form-group"><label class="form-label">Total de itens</label><input type="number" id="item-count-input" class="form-control" min="1" max="300" value="<?= count($items) ?>"></div>
                    <button type="button" class="btn btn-primary" id="generate-items"><i class="fas fa-list-ol"></i> Gerar campos</button>
                    <small class="text-muted">Digite parte do nome do item para consultar o histórico deste fornecedor.</small>
                </div>

                <div id="items-container">
                    <?php foreach ($items as $idx => $it):
                        $qtd_value = isset($it['quantidade_input']) ? (string)$it['quantidade_input'] : (string)((float)($it['quantidade'] ?? 1));
                        $valor_unit = isset($it['valor_input']) ? ai_parse_money($it['valor_input']) : (float)($it['valor_unitario'] ?? 0);
                        $valor_value = isset($it['valor_input']) ? (string)$it['valor_input'] : number_format($valor_unit, 2, ',', '.');
                        $total_item = (float)str_replace(',', '.', $qtd_value) * $valor_unit;
                    ?>
                    <div class="item-row" data-calculation-source="unit">
                        <div class="form-group" style="margin:0;"><label class="form-label">Nº</label><input class="form-control item-seq" value="<?= $idx + 1 ?>" readonly></div>
                        <div class="form-group item-name-group" style="margin:0;"><label class="form-label">Item</label><input type="text" name="produtos[<?= $idx ?>][nome]" class="form-control item-name" autocomplete="off" required value="<?= ai_h($it['produto'] ?? '') ?>" placeholder="Digite para pesquisar..."><div class="item-suggestions" role="listbox"></div></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">Qtd.</label><input type="number" step="0.01" name="produtos[<?= $idx ?>][qtd]" class="form-control item-qtd" required value="<?= ai_h($qtd_value) ?>"></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">Unid.</label><input type="text" name="produtos[<?= $idx ?>][unidade]" class="form-control item-unidade" value="<?= ai_h($it['unidade'] ?? 'UN') ?>"></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">Preço unit.</label><input type="text" name="produtos[<?= $idx ?>][valor]" class="form-control item-valor" required value="<?= ai_h($valor_value) ?>" placeholder="0,00"></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">Total</label><input type="text" class="form-control item-total" inputmode="decimal" value="<?= number_format($total_item, 2, ',', '.') ?>"></div>
                        <div><button type="button" class="btn btn-outline btn-sm remove-item" title="Remover"><i class="fas fa-trash" style="color:#dc2626;"></i></button></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-outline" id="add-item" style="margin-bottom:1.5rem;"><i class="fas fa-plus"></i> Adicionar item</button>
                <div style="text-align:right;border-top:1px solid #e2e8f0;padding-top:1.25rem;"><button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check-double"></i> Salvar itens e enviar para aprovação</button></div>
            </form>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('items-container');
    if (!container) return;

    const oficioId = <?= (int)$id ?>;
    const totalDisplay = document.getElementById('total-itens');
    const previstoEl = document.getElementById('orcamento-previsto');
    const orcamentoPrevisto = parseFloat(previstoEl?.dataset.valor || '0') || 0;
    const countInput = document.getElementById('item-count-input');
    const timers = new WeakMap();
    const controllers = new WeakMap();

    function parseBR(value) {
        let v = String(value || '').replace(/R\$/gi, '').replace(/\s/g, '');
        if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.');
        return parseFloat(v) || 0;
    }
    function money(value) { return Number(value || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function moneyLabel(value) { return 'R$ ' + money(value); }
    function esc(value) { return String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

    function calculateRow(row, source) {
        const qtd = parseFloat(String(row.querySelector('.item-qtd')?.value || '').replace(',', '.')) || 0;
        const unit = row.querySelector('.item-valor');
        const total = row.querySelector('.item-total');
        if (!unit || !total) return;
        if (source === 'total') unit.value = money(qtd > 0 ? parseBR(total.value) / qtd : 0);
        else total.value = money(qtd * parseBR(unit.value));
    }
    function calculateTotal() {
        let total = 0;
        container.querySelectorAll('.item-row').forEach(row => total += parseBR(row.querySelector('.item-total')?.value));
        totalDisplay.textContent = moneyLabel(total);
        totalDisplay.classList.toggle('diff-ok', orcamentoPrevisto > 0 && Math.abs(total - orcamentoPrevisto) <= .02);
        totalDisplay.classList.toggle('diff-warning', orcamentoPrevisto > 0 && Math.abs(total - orcamentoPrevisto) > .02);
        return total;
    }
    function renumber() {
        container.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelector('.item-seq').value = index + 1;
            row.querySelectorAll('[name^="produtos["]').forEach(input => input.name = input.name.replace(/produtos\[\d+\]/, `produtos[${index}]`));
        });
        countInput.value = container.querySelectorAll('.item-row').length;
    }
    function createRow(index) {
        const row = document.createElement('div');
        row.className = 'item-row';
        row.dataset.calculationSource = 'unit';
        row.innerHTML = `<div class="form-group" style="margin:0"><label class="form-label">Nº</label><input class="form-control item-seq" value="${index+1}" readonly></div><div class="form-group item-name-group" style="margin:0"><label class="form-label">Item</label><input type="text" name="produtos[${index}][nome]" class="form-control item-name" autocomplete="off" required placeholder="Digite para pesquisar..."><div class="item-suggestions" role="listbox"></div></div><div class="form-group" style="margin:0"><label class="form-label">Qtd.</label><input type="number" step="0.01" name="produtos[${index}][qtd]" class="form-control item-qtd" required value="1"></div><div class="form-group" style="margin:0"><label class="form-label">Unid.</label><input type="text" name="produtos[${index}][unidade]" class="form-control item-unidade" value="UN"></div><div class="form-group" style="margin:0"><label class="form-label">Preço unit.</label><input type="text" name="produtos[${index}][valor]" class="form-control item-valor" required placeholder="0,00"></div><div class="form-group" style="margin:0"><label class="form-label">Total</label><input type="text" class="form-control item-total" inputmode="decimal" value="0,00"></div><div><button type="button" class="btn btn-outline btn-sm remove-item"><i class="fas fa-trash" style="color:#dc2626"></i></button></div>`;
        return row;
    }
    function panel(input) { return input.closest('.item-name-group')?.querySelector('.item-suggestions'); }
    function hide(input) { const p = panel(input); if (p) { p.classList.remove('show'); p.innerHTML=''; } }
    function render(input, items) {
        const p = panel(input); if (!p) return;
        input._suggestions = items;
        if (!items.length) { p.innerHTML='<div class="suggestion-empty"><i class="fas fa-search"></i> Nenhum item encontrado para este fornecedor. Cadastre manualmente se necessário.</div>'; p.classList.add('show'); return; }
        p.innerHTML = items.map((item,i)=>`<button type="button" class="suggestion-option" data-index="${i}"><div class="suggestion-title"><span>${esc(item.produto)}</span><span class="suggestion-price">${moneyLabel(item.valor_unitario)}</span></div><div class="suggestion-meta"><span class="suggestion-chip">${esc(item.unidade || 'UN')}</span><span class="suggestion-chip">${esc(item.origem || 'Histórico')}</span><span class="suggestion-chip">${Number(item.usos||0)} uso(s)</span></div></button>`).join('');
        p.classList.add('show');
    }
    function search(input) {
        const term = input.value.trim();
        const oldTimer = timers.get(input); if (oldTimer) clearTimeout(oldTimer);
        if (term.length < 2) { hide(input); return; }
        timers.set(input, setTimeout(async () => {
            const oldController = controllers.get(input); if (oldController) oldController.abort();
            const controller = new AbortController(); controllers.set(input, controller);
            const p = panel(input); if (p) { p.innerHTML='<div class="suggestion-empty"><i class="fas fa-spinner fa-spin"></i> Buscando preços deste fornecedor...</div>'; p.classList.add('show'); }
            try {
                const response = await fetch(`atribuir_itens.php?id=${encodeURIComponent(oficioId)}&ajax=sugerir_itens&q=${encodeURIComponent(term)}`, {headers:{Accept:'application/json'}, signal:controller.signal});
                const data = await response.json();
                if (!response.ok) throw new Error(data.erro || 'Falha na busca');
                if (input.value.trim() === term) render(input, Array.isArray(data) ? data : []);
            } catch (e) { if (e.name !== 'AbortError' && p) { p.innerHTML=`<div class="suggestion-empty">${esc(e.message || 'Erro ao pesquisar')}</div>`; p.classList.add('show'); } }
        }, 220));
    }
    function apply(input, item) {
        const row = input.closest('.item-row'); if (!row || !item) return;
        input.value = item.produto || '';
        row.querySelector('.item-unidade').value = item.unidade || 'UN';
        row.querySelector('.item-valor').value = money(item.valor_unitario || 0);
        row.dataset.calculationSource='unit'; calculateRow(row,'unit'); calculateTotal(); hide(input);
        row.querySelector('.item-qtd')?.focus();
    }

    container.addEventListener('input', e => {
        const row = e.target.closest('.item-row');
        if (e.target.classList.contains('item-name')) search(e.target);
        if (!row) return;
        if (e.target.classList.contains('item-valor')) { row.dataset.calculationSource='unit'; calculateRow(row,'unit'); }
        if (e.target.classList.contains('item-total')) { row.dataset.calculationSource='total'; calculateRow(row,'total'); }
        if (e.target.classList.contains('item-qtd')) calculateRow(row,row.dataset.calculationSource || 'unit');
        calculateTotal();
    });
    container.addEventListener('focusout', e => {
        if (e.target.classList.contains('item-valor') || e.target.classList.contains('item-total')) {
            e.target.value = money(parseBR(e.target.value));
            const row=e.target.closest('.item-row'); calculateRow(row,row.dataset.calculationSource || 'unit'); calculateTotal();
        }
    });
    container.addEventListener('mousedown', e => {
        const option=e.target.closest('.suggestion-option'); if (!option) return;
        e.preventDefault(); const input=option.closest('.item-name-group')?.querySelector('.item-name'); apply(input,input?._suggestions?.[Number(option.dataset.index)]);
    });
    container.addEventListener('click', e => {
        const remove=e.target.closest('.remove-item'); if (!remove) return;
        if (container.querySelectorAll('.item-row').length <= 1) return;
        remove.closest('.item-row').remove(); renumber(); calculateTotal();
    });
    document.addEventListener('click', e => { if (!e.target.closest('.item-name-group')) container.querySelectorAll('.item-name').forEach(hide); });
    document.getElementById('add-item').addEventListener('click', () => { container.appendChild(createRow(container.querySelectorAll('.item-row').length)); renumber(); });
    document.getElementById('generate-items').addEventListener('click', () => {
        const desired=Math.max(1,Math.min(300,parseInt(countInput.value,10)||1));
        while(container.querySelectorAll('.item-row').length<desired) container.appendChild(createRow(container.querySelectorAll('.item-row').length));
        while(container.querySelectorAll('.item-row').length>desired) container.lastElementChild.remove();
        renumber(); calculateTotal();
    });
    document.getElementById('items-form').addEventListener('submit', e => {
        renumber(); container.querySelectorAll('.item-row').forEach(row=>calculateRow(row,row.dataset.calculationSource || 'unit'));
        const total=calculateTotal();
        if (orcamentoPrevisto>0 && Math.abs(total-orcamentoPrevisto)>.02) { e.preventDefault(); alert('Bloqueado: o total dos itens precisa ser igual ao orçamento previsto.'); }
    });

    renumber(); container.querySelectorAll('.item-row').forEach(row=>calculateRow(row,'unit')); calculateTotal();
});
</script>
<?php endif; ?>

<?php include 'views/layout/footer.php'; ?>
