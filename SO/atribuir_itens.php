<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
sefaz_check();

$id = (int)($_GET['id'] ?? 0);

function parse_atribuicao_money($valor): float {
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
        throw new Exception("Informe um valor monetário válido.");
    }

    $valor = (float)$valor;
    if ($valor < 0) {
        throw new Exception("Valores monetários não podem ser negativos.");
    }

    return $valor;
}

function parse_atribuicao_quantity($valor, int $itemIndex): float {
    $valor = trim((string)$valor);
    $valor = str_replace(',', '.', $valor);

    if ($valor === '' || !is_numeric($valor)) {
        throw new Exception("Informe uma quantidade válida para o item " . ($itemIndex + 1) . ".");
    }

    $valor = (float)$valor;
    if ($valor <= 0) {
        throw new Exception("A quantidade do item " . ($itemIndex + 1) . " deve ser maior que zero.");
    }

    return $valor;
}

if (($_GET['ajax'] ?? '') === 'sugerir_itens') {
    header('Content-Type: application/json; charset=utf-8');

    $termo = trim((string)($_GET['q'] ?? ''));
    if (strlen($termo) < 2) {
        echo json_encode([]);
        exit;
    }

    try {
        $termo_like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo);

        $stmt_sugestoes = $pdo->prepare("
            SELECT
                produto,
                unidade,
                CAST(SUBSTRING_INDEX(GROUP_CONCAT(valor_unitario ORDER BY ultima_data DESC SEPARATOR ','), ',', 1) AS DECIMAL(15,2)) AS valor_unitario,
                COUNT(*) AS usos,
                MAX(ultima_data) AS ultima_data,
                GROUP_CONCAT(DISTINCT origem ORDER BY origem SEPARATOR ',') AS origens
            FROM (
                SELECT
                    TRIM(io.produto) AS produto,
                    COALESCE(NULLIF(TRIM(io.unidade), ''), 'UN') AS unidade,
                    COALESCE(io.valor_unitario, 0) AS valor_unitario,
                    COALESCE(o.criado_em, NOW()) AS ultima_data,
                    'oficio' AS origem
                FROM itens_oficio io
                LEFT JOIN oficios o ON o.id = io.oficio_id
                WHERE io.produto LIKE :termo_oficio ESCAPE '\\\\'

                UNION ALL

                SELECT
                    TRIM(ia.produto) AS produto,
                    COALESCE(
                        NULLIF(TRIM((
                            SELECT io2.unidade
                            FROM itens_oficio io2
                            JOIN aquisicoes aq2 ON aq2.oficio_id = io2.oficio_id
                            WHERE aq2.id = ia.aquisicao_id
                              AND (
                                  io2.id = ia.oficio_item_id
                                  OR (
                                      ia.oficio_item_id IS NULL
                                      AND TRIM(UPPER(io2.produto)) = TRIM(UPPER(ia.produto))
                                  )
                              )
                            ORDER BY io2.id ASC
                            LIMIT 1
                        )), ''),
                        'UN'
                    ) AS unidade,
                    COALESCE(ia.valor_unitario, 0) AS valor_unitario,
                    COALESCE(a.criado_em, NOW()) AS ultima_data,
                    'aquisicao' AS origem
                FROM itens_aquisicao ia
                LEFT JOIN aquisicoes a ON a.id = ia.aquisicao_id
                WHERE ia.produto LIKE :termo_aquisicao ESCAPE '\\\\'
            ) base
            WHERE produto <> ''
            GROUP BY produto, unidade
            ORDER BY
                CASE WHEN produto LIKE :termo_prefixo ESCAPE '\\\\' THEN 0 ELSE 1 END,
                usos DESC,
                ultima_data DESC,
                produto ASC
            LIMIT 12
        ");

        $stmt_sugestoes->execute([
            ':termo_oficio' => '%' . $termo_like . '%',
            ':termo_aquisicao' => '%' . $termo_like . '%',
            ':termo_prefixo' => $termo_like . '%',
        ]);

        $sugestoes = [];
        foreach ($stmt_sugestoes->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $origens = array_filter(explode(',', (string)($row['origens'] ?? '')));
            $labels = [];

            if (in_array('oficio', $origens, true)) {
                $labels[] = 'Ofícios';
            }

            if (in_array('aquisicao', $origens, true)) {
                $labels[] = 'Aquisições';
            }

            $sugestoes[] = [
                'produto' => (string)$row['produto'],
                'unidade' => (string)($row['unidade'] ?: 'UN'),
                'valor_unitario' => (float)($row['valor_unitario'] ?? 0),
                'usos' => (int)($row['usos'] ?? 0),
                'origem' => !empty($labels) ? implode(' + ', $labels) : 'Histórico',
            ];
        }

        echo json_encode($sugestoes, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Não foi possível buscar sugestões de itens.'], JSON_UNESCAPED_UNICODE);
    }

    exit;
}

$stmt = $pdo->prepare("
    SELECT o.*, s.nome as secretaria 
    FROM oficios o 
    JOIN secretarias s ON o.secretaria_id = s.id 
    WHERE o.id = ?
");
$stmt->execute([$id]);
$oficio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$oficio) {
    die("Solicitação não encontrada.");
}

// Buscar itens existentes
$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC");
$stmt_items->execute([$id]);
$items_existentes = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Resumo por produto
$stmt_resumo = $pdo->prepare("
    SELECT
        TRIM(produto) AS produto,
        COALESCE(NULLIF(TRIM(unidade), ''), 'UN') AS unidade,
        COUNT(*) AS total_registros,
        SUM(quantidade) AS quantidade_total,
        SUM(quantidade * valor_unitario) AS valor_total_produto
    FROM itens_oficio
    WHERE oficio_id = ?
    GROUP BY TRIM(produto), COALESCE(NULLIF(TRIM(unidade), ''), 'UN')
    ORDER BY produto ASC
");
$stmt_resumo->execute([$id]);
$resumo_produtos = $stmt_resumo->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produtos = $_POST['produtos'] ?? [];

    try {
        $orcamento_esperado = (float)($oficio['valor_orcamento'] ?? 0);
        $total_calculado = 0;
        $itens_sanitizados = [];

        foreach ($produtos as $idx => $p) {
            $nome = trim((string)($p['nome'] ?? ''));

            if ($nome === '') {
                continue;
            }

            $qtd = parse_atribuicao_quantity($p['qtd'] ?? '', (int)$idx);
            $unidade = trim((string)($p['unidade'] ?? 'UN'));
            $valor_unitario = parse_atribuicao_money($p['valor'] ?? '0');

            if ($unidade === '') {
                $unidade = 'UN';
            }

            $total_calculado += ($valor_unitario * $qtd);

            $itens_sanitizados[] = [
                'produto' => $nome,
                'quantidade' => $qtd,
                'unidade' => $unidade,
                'valor_unitario' => $valor_unitario,
            ];
        }

        if (empty($itens_sanitizados)) {
            throw new Exception("Informe pelo menos um item para a solicitação.");
        }

        if ($orcamento_esperado > 0 && abs($total_calculado - $orcamento_esperado) > 0.02) {
            throw new Exception("O valor total dos itens deve ser exatamente igual ao orçamento previsto de R$ " . number_format($orcamento_esperado, 2, ',', '.'));
        }

        $pdo->beginTransaction();

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
                $item['valor_unitario']
            ]);
        }

        $pdo->prepare("UPDATE oficios SET status = 'ENVIADO' WHERE id = ?")->execute([$id]);

        log_action($pdo, "ATRIBUIR_ITENS", "Itens atribuídos ao ofício {$oficio['numero']}");
        $pdo->commit();

        flash_message('success', "Itens atribuídos com sucesso à solicitação {$oficio['numero']}!");
        header("Location: oficios_lista_sefaz.php");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Erro ao salvar itens: " . $e->getMessage();
    }
}

$items_form = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (($_POST['produtos'] ?? []) as $p) {
        $items_form[] = [
            'produto' => (string)($p['nome'] ?? ''),
            'quantidade_input' => (string)($p['qtd'] ?? '1'),
            'unidade' => (string)($p['unidade'] ?? 'UN'),
            'valor_input' => (string)($p['valor'] ?? ''),
            'valor_unitario' => 0,
        ];
    }
}

$page_title = "Atribuir Itens - " . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
    .item-row {
        display: grid;
        grid-template-columns: 80px 2fr 1fr 1fr 1fr 1.2fr auto;
        gap: 1rem;
        margin-bottom: 1rem;
        align-items: end;
        padding: 1rem;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: #fff;
    }

    .budget-info {
        background: #f1f5f9;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .total-calc {
        font-size: 1.25rem;
        font-weight: 700;
    }

    .diff-warning {
        color: #dc3545;
    }

    .diff-ok {
        color: #198754;
    }

    .item-seq {
        text-align: center;
        font-weight: 800;
        background: #f8fafc;
    }

    .item-total {
        background: #f8fafc;
        font-weight: 800;
        color: #198754;
        text-align: right;
    }

    .planilha-import {
        margin-bottom: 1.5rem;
        padding: 1rem;
        border: 1px dashed #b8c6d8;
        border-radius: 12px;
        background: #f8fafc;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .planilha-import-title {
        margin: 0 0 .25rem;
        font-size: 1rem;
        color: #0f172a;
    }

    .planilha-import-text {
        margin: 0;
        color: #64748b;
        font-size: .88rem;
        font-weight: 600;
    }

    .planilha-import-status {
        width: 100%;
        display: none;
        padding: .75rem .85rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: .88rem;
    }

    .planilha-import-status.show {
        display: block;
    }

    .planilha-import-status.success {
        background: #dcfce7;
        color: #166534;
    }

    .planilha-import-status.warning {
        background: #fef3c7;
        color: #92400e;
    }

    .planilha-import-status.error {
        background: #fee2e2;
        color: #991b1b;
    }

    .item-name-group {
        position: relative;
    }

    .item-suggestions {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        z-index: 40;
        display: none;
        max-height: 320px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
        padding: .45rem;
    }

    .item-suggestions.show {
        display: block;
    }

    .suggestion-option {
        width: 100%;
        border: 0;
        background: transparent;
        text-align: left;
        cursor: pointer;
        border-radius: 10px;
        padding: .72rem .78rem;
        display: block;
        color: #0f172a;
        transition: background .16s ease, transform .16s ease;
    }

    .suggestion-option:hover,
    .suggestion-option.active {
        background: #eef6ff;
    }

    .suggestion-option:active {
        transform: scale(.99);
    }

    .suggestion-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .suggestion-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .suggestion-price {
        flex-shrink: 0;
        color: #157347;
        font-weight: 900;
        white-space: nowrap;
    }

    .suggestion-meta {
        margin-top: .4rem;
        display: flex;
        align-items: center;
        gap: .4rem;
        flex-wrap: wrap;
        color: #64748b;
        font-size: .76rem;
        font-weight: 700;
    }

    .suggestion-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        border-radius: 999px;
        background: #f1f5f9;
        color: #334155;
        padding: .22rem .5rem;
    }

    .suggestion-empty,
    .suggestion-loading {
        padding: .85rem .9rem;
        color: #64748b;
        font-weight: 700;
        font-size: .85rem;
    }

    @media (max-width: 1200px) {
        .item-row {
            grid-template-columns: 70px 1.8fr 1fr 1fr 1fr 1fr auto;
        }
    }

    @media (max-width: 992px) {
        .item-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3><i class="fas fa-box-open"></i> Atribuição de Itens - <?php echo htmlspecialchars($oficio['numero']); ?></h3>
            <a href="oficios_lista_sefaz.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="budget-info">
            <div>
                <span class="text-muted">Secretaria:</span>
                <strong><?php echo htmlspecialchars($oficio['secretaria']); ?></strong><br>

                <span class="text-muted">Orçamento Previsto:</span>
                <strong id="orcamento-previsto" data-valor="<?php echo (float)($oficio['valor_orcamento'] ?? 0); ?>">
                    <?php echo !empty($oficio['valor_orcamento']) ? format_money($oficio['valor_orcamento']) : 'Não informado'; ?>
                </strong>
            </div>

            <div style="text-align: right;">
                <span class="text-muted">Total Atual dos Itens:</span><br>
                <span id="total-itens" class="total-calc">R$ 0,00</span>
            </div>
        </div>

        <div class="planilha-import">
            <div>
                <h4 class="planilha-import-title"><i class="fas fa-file-import"></i> Importar orçamento ou ofício</h4>
                <p class="planilha-import-text">Aceita um ou vários PDF, DOCX, TXT, CSV e imagens. Cada importação adiciona itens à lista atual e limita o nome importado a 10 palavras.</p>
            </div>
            <div>
                <input type="file" id="planilha-pdf-input" accept=".pdf,.docx,.txt,.csv,.jpg,.jpeg,.png,.webp,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/csv,image/*" multiple hidden>
                <button type="button" class="btn btn-outline" id="planilha-pdf-btn">
                    <i class="fas fa-upload"></i> Selecionar arquivo
                </button>
            </div>
            <div id="planilha-import-status" class="planilha-import-status" aria-live="polite"></div>
        </div>

        <?php if (!empty($resumo_produtos)): ?>
            <div class="card" style="margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                <div class="card-body">
                    <h4 style="margin-bottom: 1rem;">
                        <i class="fas fa-chart-bar"></i> Resumo dos Produtos
                    </h4>

                    <div style="overflow-x:auto;">
                        <table class="table" style="width:100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="padding: 12px; text-align:left;">Produto</th>
                                    <th style="padding: 12px; text-align:center;">Unidade</th>
                                    <th style="padding: 12px; text-align:center;">Qtd. de Lançamentos</th>
                                    <th style="padding: 12px; text-align:center;">Quantidade Total</th>
                                    <th style="padding: 12px; text-align:right;">Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumo_produtos as $rp): ?>
                                    <tr style="border-top:1px solid #e5e7eb;">
                                        <td style="padding: 12px; font-weight:600;">
                                            <?php echo htmlspecialchars($rp['produto']); ?>
                                        </td>
                                        <td style="padding: 12px; text-align:center;">
                                            <?php echo htmlspecialchars($rp['unidade']); ?>
                                        </td>
                                        <td style="padding: 12px; text-align:center; font-weight:700;">
                                            <?php echo (int)$rp['total_registros']; ?>
                                        </td>
                                        <td style="padding: 12px; text-align:center; font-weight:700;">
                                            <?php echo number_format((float)$rp['quantidade_total'], 2, ',', '.'); ?>
                                        </td>
                                        <td style="padding: 12px; text-align:right; font-weight:700; color:#198754;">
                                            <?php echo format_money((float)$rp['valor_total_produto']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="items-form">
            <div id="items-container">
                <?php
                $items = !empty($items_form)
                    ? $items_form
                    : (!empty($items_existentes)
                        ? $items_existentes
                        : [['produto' => '', 'quantidade' => 1, 'unidade' => 'UN', 'valor_unitario' => 0]]);

                foreach ($items as $idx => $it):
                    $qtd_value = isset($it['quantidade_input']) ? (string)$it['quantidade_input'] : (string)((float)($it['quantidade'] ?? 0));
                    $qtd_item = (float)str_replace(',', '.', $qtd_value);
                    $valor_unit_item = (float)($it['valor_unitario'] ?? 0);
                    $valor_value = isset($it['valor_input']) ? (string)$it['valor_input'] : number_format($valor_unit_item, 2, ',', '.');
                    $valor_total_item = $qtd_item * $valor_unit_item;
                ?>
                    <div class="item-row">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nº</label>
                            <input type="text" class="form-control item-seq" value="<?php echo $idx + 1; ?>" readonly>
                        </div>

                        <div class="form-group item-name-group" style="margin:0;">
                            <label class="form-label">Nome do Item</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][nome]"
                                class="form-control item-name"
                                required
                                autocomplete="off"
                                placeholder="Ex: Papel A4"
                                value="<?php echo htmlspecialchars($it['produto']); ?>">
                            <div class="item-suggestions" role="listbox"></div>
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Quantidade</label>
                            <input
                                type="number"
                                step="0.01"
                                name="produtos[<?php echo $idx; ?>][qtd]"
                                class="form-control item-qtd"
                                required
                                value="<?php echo htmlspecialchars($qtd_value, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Unidade</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][unidade]"
                                class="form-control"
                                value="<?php echo htmlspecialchars($it['unidade'] ?? 'UN'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Valor Unitário</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][valor]"
                                class="form-control item-valor"
                                required
                                placeholder="0,00"
                                value="<?php echo htmlspecialchars($valor_value, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Total do Item</label>
                            <input
                                type="text"
                                class="form-control item-total"
                                value="R$ <?php echo number_format($valor_total_item, 2, ',', '.'); ?>"
                                readonly>
                        </div>

                        <div style="margin-bottom: 5px;">
                            <button type="button" class="btn btn-outline btn-sm remove-item" style="color:red; border-color:#ff000033;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-outline" id="add-item" style="margin-bottom: 2rem;">
                <i class="fas fa-plus"></i> Adicionar Mais Itens
            </button>

            <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 2rem;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-double"></i> Finalizar Atribuição e Enviar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/vendor/pdfjs/pdf.min.js"></script>
<script src="assets/js/vendor/mammoth/mammoth.browser.min.js"></script>
<script src="assets/js/vendor/tesseract/tesseract.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('items-container');
    const totalDisplay = document.getElementById('total-itens');
    const orcamentoPrevisto = parseFloat(document.getElementById('orcamento-previsto').dataset.valor) || 0;
    const oficioId = <?php echo (int)$id; ?>;
    const planilhaInput = document.getElementById('planilha-pdf-input');
    const planilhaBtn = document.getElementById('planilha-pdf-btn');
    const planilhaStatus = document.getElementById('planilha-import-status');
    const autocompleteTimers = new WeakMap();
    const autocompleteControllers = new WeakMap();
    const pdfWorkerUrl = 'assets/js/vendor/pdfjs/pdf.worker.min.js';

    if (window.pdfjsLib) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
    }

    function parseValorBR(valor) {
        if (!valor) return 0;
        let v = String(valor).trim();
        v = v.replace(/\s/g, '');
        v = v.replace(/\./g, '');
        v = v.replace(',', '.');
        return parseFloat(v) || 0;
    }

    function formatMoneyBR(valor) {
        return 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatInputMoneyBR(valor) {
        return Number(valor || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setPlanilhaStatus(message, type) {
        if (!planilhaStatus) return;

        planilhaStatus.textContent = message;
        planilhaStatus.className = `planilha-import-status show ${type || 'success'}`;
    }

    function parsePlanilhaMoney(valor) {
        const normalized = String(valor || '')
            .replace(/R\$|RS/gi, '')
            .replace(/\s/g, '')
            .replace(/\./g, '')
            .replace(',', '.');

        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function normalizeToken(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^\w]/g, '')
            .toUpperCase();
    }

    function normalizeUnitLabel(value) {
        const raw = String(value || '').trim().replace(/\.$/, '');
        const key = normalizeToken(raw);
        const units = {
            UN: 'UN',
            UND: 'UN',
            UNID: 'UN',
            UNIDADE: 'UN',
            PAR: 'PAR',
            PARES: 'PAR',
            PC: 'PÇ',
            PECA: 'PÇ',
            PECAS: 'PÇ',
            PCT: 'PCT',
            PACOTE: 'PCT',
            CX: 'CX',
            CAIXA: 'CX',
            KG: 'Kg',
            KILO: 'Kg',
            QUILO: 'Kg',
            G: 'g',
            GRAMA: 'g',
            LT: 'L',
            L: 'L',
            LITRO: 'L',
            M: 'm',
            MT: 'm',
            METRO: 'm',
            M2: 'm2',
            M3: 'm3',
            ROLO: 'rolo',
            RL: 'rolo',
            SERV: 'SERV',
            SV: 'SERV'
        };

        return units[key] || raw.toUpperCase() || 'UN';
    }

    function splitWords(value) {
        return String(value || '').trim().split(/\s+/).filter(Boolean);
    }

    function parseImportedQuantity(value) {
        const normalized = String(value ?? '')
            .replace(/\s/g, '')
            .trim();

        if (!normalized) {
            return 0;
        }

        const decimalNormalized = /^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/u.test(normalized)
            ? normalized.replace(/\./g, '').replace(',', '.')
            : normalized.replace(',', '.');
        const parsed = Number(decimalNormalized);

        return Number.isFinite(parsed) ? parsed : 0;
    }

    function limitImportedProductName(value, maxWords = 10) {
        const words = splitWords(String(value || '').replace(/\s+/g, ' ').trim());
        return words.length > maxWords ? words.slice(0, maxWords).join(' ') : words.join(' ');
    }

    function getCommonPrefixTokens(descriptions) {
        if (descriptions.length < 2) {
            return [];
        }

        const tokenRows = descriptions.map(splitWords);
        const maxPrefixLength = Math.min(6, ...tokenRows.map(tokens => Math.max(tokens.length - 1, 0)));
        const prefix = [];

        for (let index = 0; index < maxPrefixLength; index++) {
            const candidate = normalizeToken(tokenRows[0][index]);
            if (!candidate) {
                break;
            }

            const matchesAllRows = tokenRows.every(tokens => normalizeToken(tokens[index]) === candidate);
            if (!matchesAllRows) {
                break;
            }

            prefix.push(tokenRows[0][index]);
        }

        return prefix.length >= 2 ? prefix : [];
    }

    function stripPrefixTokens(value, prefixTokens) {
        const tokens = splitWords(value);

        if (!prefixTokens.length || tokens.length <= prefixTokens.length) {
            return String(value || '').trim();
        }

        const hasPrefix = prefixTokens.every((token, index) => normalizeToken(tokens[index]) === normalizeToken(token));
        return hasPrefix ? tokens.slice(prefixTokens.length).join(' ') : String(value || '').trim();
    }

    function buildBudgetParseResult(items, totalPdf = 0) {
        const parsedItems = items
            .map(item => {
                const quantidadeRaw = typeof item.quantidade === 'string'
                    ? parseImportedQuantity(item.quantidade)
                    : Number(item.quantidade || 0);
                const quantidade = Number.isFinite(quantidadeRaw) ? quantidadeRaw : 0;
                const valorUnitarioRaw = Number(item.valor_unitario ?? 0);
                const valorUnitario = Number.isFinite(valorUnitarioRaw) ? valorUnitarioRaw : 0;
                const valorTotalRaw = Number(item.valor_total ?? (quantidade * valorUnitario));
                const valorTotal = Number.isFinite(valorTotalRaw) ? valorTotalRaw : 0;

                return {
                    ...item,
                    produto: limitImportedProductName(item.produto, 10),
                    unidade: normalizeUnitLabel(item.unidade || 'UN'),
                    quantidade,
                    valor_unitario: valorUnitario,
                    valor_total: valorTotal
                };
            })
            .filter(item => item.produto && item.quantidade > 0);

        if (!parsedItems.length) {
            return null;
        }

        const totalItens = parsedItems.reduce((sum, item) => sum + (item.quantidade * item.valor_unitario), 0);
        const totalLinhas = parsedItems.reduce((sum, item) => sum + Number(item.valor_total || 0), 0);

        return {
            items: parsedItems,
            totalPdf: totalPdf > 0 ? totalPdf : totalLinhas,
            totalItens
        };
    }

    function getRowText(row) {
        return (row?.items || [])
            .slice()
            .sort((a, b) => a.x - b.x)
            .map(item => item.text)
            .join(' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function findHeaderColumnX(headerItems, pattern) {
        const item = headerItems.find(headerItem => pattern.test(normalizeToken(headerItem.text)));
        return item ? item.x : null;
    }

    function collectColumnText(items, left, right) {
        return items
            .filter(item => item.x >= left && item.x < right)
            .sort((a, b) => a.x - b.x)
            .map(item => item.text)
            .join(' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function firstCurrencyFromText(value) {
        const match = String(value || '').match(/[\d.]+,\d{2}/);
        return match ? parsePlanilhaMoney(match[0]) : 0;
    }

    function firstQuantityFromText(value) {
        const match = String(value || '').match(/\d+(?:[,.]\d+)?/);
        return match ? Number(match[0].replace(',', '.')) : 0;
    }

    function normalizeHeaderKey(value) {
        return normalizeToken(value)
            .replace(/^VALOR/, 'VLR')
            .replace(/^PRECO/, 'VLR')
            .replace(/^QUANTIDADE$/, 'QTD')
            .replace(/^QTDE$/, 'QTD')
            .replace(/^UNIDADE$/, 'UND')
            .replace(/^UNID$/, 'UND');
    }

    function findHeaderIndex(headers, aliases) {
        const normalizedAliases = aliases.map(normalizeHeaderKey);

        return headers.findIndex(header => {
            const key = normalizeHeaderKey(header);
            return normalizedAliases.some(alias => key === alias || key.includes(alias) || (key.length >= 4 && alias.includes(key)));
        });
    }

    function splitDelimitedLine(line) {
        const raw = String(line || '').trim();

        if (raw.includes(';')) {
            return raw.split(';');
        }

        if (raw.includes('\t')) {
            return raw.split('\t');
        }

        if (raw.includes('|')) {
            return raw.split('|');
        }

        return null;
    }

    function parseDelimitedBudget(text) {
        const lines = String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .map(line => line.trim())
            .filter(Boolean);
        const items = [];

        for (let index = 0; index < lines.length; index++) {
            const headerParts = splitDelimitedLine(lines[index]);
            if (!headerParts || headerParts.length < 3) {
                continue;
            }

            const headers = headerParts.map(part => part.trim());
            const productIndex = findHeaderIndex(headers, ['descri', 'descricao', 'produto', 'item', 'material', 'servico']);
            const quantityIndex = findHeaderIndex(headers, ['qtd', 'quantidade', 'qtde']);
            const unitIndex = findHeaderIndex(headers, ['und', 'unidade', 'unid']);
            const unitValueIndex = findHeaderIndex(headers, ['unit', 'valor unitario', 'vlr unitario', 'v unit', 'preco unitario', 'unitario']);
            const totalIndex = findHeaderIndex(headers, ['valor total', 'vlr total', 'total', 'subtotal']);

            if (productIndex < 0 || quantityIndex < 0) {
                continue;
            }

            for (const line of lines.slice(index + 1)) {
                if (/^\s*(total|subtotal|desconto|pagamento)\b/iu.test(line)) {
                    break;
                }

                const parts = splitDelimitedLine(line);
                if (!parts || parts.length < headers.length - 1) {
                    continue;
                }

                const produto = String(parts[productIndex] || '').trim();
                const quantidade = firstQuantityFromText(parts[quantityIndex] || '');
                const valorUnitario = unitValueIndex >= 0 ? parsePlanilhaMoney(parts[unitValueIndex] || '') : 0;
                const valorTotal = totalIndex >= 0 ? parsePlanilhaMoney(parts[totalIndex] || '') : quantidade * valorUnitario;

                if (!produto || quantidade <= 0) {
                    continue;
                }

                items.push({
                    produto,
                    unidade: unitIndex >= 0 ? normalizeUnitLabel(parts[unitIndex] || 'UN') : 'UN',
                    quantidade,
                    valor_unitario: valorUnitario,
                    valor_total: valorTotal
                });
            }

            if (items.length) {
                break;
            }
        }

        return buildBudgetParseResult(items);
    }

    function cleanImportedLine(line) {
        return String(line || '')
            .normalize('NFC')
            .replace(/[|¦]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function officeUnitPattern() {
        return '(?:UNID\\.?|UND\\.?|UNIDADE|UN\\.?|PAR(?:ES)?|PÇ|PC|PE[ÇC]A(?:S)?|PCT|PACOTE|CX|CAIXA|KG|KILO|QUILO|G|LT|L|LITRO|M2|M3|MT|M|METRO|ROLO|RL|SERV|SV)';
    }

    function cleanOfficeProductName(value) {
        return String(value || '')
            .replace(/\bQUANT\.?\s*\/\s*COMPRADA\b.*$/iu, '')
            .replace(/\bOBS\.?\b.*$/iu, '')
            .replace(/\bP\s+VC\b/giu, 'PVC')
            .replace(/\bARAM\s+E\b/giu, 'ARAME')
            .replace(/\bG&!\s+LO\s+GRANDE\b/giu, 'GRANDE')
            .replace(/\bDE\s+SM\b/giu, 'DE 5m')
            .replace(/\bCADEADO\s*M[ÉE]DIO\b/giu, 'CADEADO MEDIO')
            .replace(/\bCADEADO\s+MT\s+CADEADO\s+MEDIO\b/giu, 'CADEADO MEDIO')
            .replace(/^[\s.,;:-]+|[\s.,;:-]+$/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function isOfficeListTerminator(line) {
        const normalized = normalizeToken(line);

        return normalized.includes('ASSINATURA')
            || normalized.includes('ESTRADADOAEROPORTO')
            || normalized.includes('RODAPE')
            || (normalized.includes('CEP') && normalized.includes('COARI'));
    }

    function isIgnorableOfficeLine(line) {
        const clean = cleanImportedLine(line);
        const normalized = normalizeToken(clean);

        if (!normalized) {
            return true;
        }

        if (/^\d{1,3}\s*[\).:-]?\s+/u.test(clean)) {
            return false;
        }

        if (isOfficeListTerminator(clean)) {
            return true;
        }

        if (
            (normalized.includes('DESCRICAO') && normalized.includes('QUANT'))
            || (normalized.includes('ITENS') && normalized.includes('UNID'))
            || (normalized.includes('ITEM') && normalized.includes('QTD'))
        ) {
            return true;
        }

        return [
            'PREFEITURA',
            'ESTADO',
            'SECRETARIA',
            'MEMORANDO',
            'PROTOCOLO',
            'DEPARTAMENTO',
            'TECNICO',
            'SADT',
            'COARI',
            'AMAZONAS',
            'RAMISSES',
            'DIRETOR',
            'SENHOR',
            'CUMPRIMENTO',
            'SOLICITAR',
            'SOLICITA',
            'SERVICOS',
            'REFORMA',
            'INFRAESTRUTURA',
            'COMUNIDADE',
            'ESTRADA',
            'AEROPORTO',
            'BAIRRO',
            'CEP',
            'HORA',
            'DATA'
        ].some(token => normalized.includes(token));
    }

    function isValidOfficeProduct(produto, quantidade) {
        const normalized = normalizeToken(produto);
        const words = String(produto || '').split(/\s+/).filter(Boolean);
        const singleLetterWords = words.filter(word => /^[A-ZÁÉÍÓÚÂÊÔÃÕÇa-záéíóúâêôãõç]$/u.test(word)).length;
        const symbolCount = (String(produto || '').match(/[^\w\sÁÉÍÓÚÂÊÔÃÕÇáéíóúâêôãõç.,/ºª°-]/gu) || []).length;

        if (!normalized || normalized.length < 2 || quantidade <= 0) {
            return false;
        }

        if ((words.length === 1 && normalized.length < 5) || (words.length <= 2 && normalized.length < 4)) {
            return false;
        }

        if (words.length >= 5 && singleLetterWords / words.length >= 0.35) {
            return false;
        }

        if (String(produto || '').length >= 12 && symbolCount >= 3) {
            return false;
        }

        return ![
            'DESCRICAO',
            'QUANT',
            'COMPRADA',
            'OBS',
            'UNID',
            'ITEM'
        ].some(token => normalized === token || normalized.includes(`${token}${token}`));
    }

    function parseOfficeMaterialPayload(body, options = {}) {
        const clean = cleanImportedLine(body);
        const currencyMatches = clean.match(/(?:R\$\s*)?[\d.]+,\d{2}/giu) || [];

        if (!clean || currencyMatches.length >= 2) {
            return null;
        }

        const unitPattern = officeUnitPattern();
        const normalPattern = new RegExp(`^(.+)\\s+(${unitPattern})\\s+(\\d+(?:[,.]\\d+)?)(?:\\s+.*)?$`, 'iu');
        const invertedPattern = new RegExp(`^(.+)\\s+(\\d+(?:[,.]\\d+)?)\\s+(${unitPattern})(?:\\s+.*)?$`, 'iu');
        const matches = [
            clean.match(normalPattern),
            clean.match(invertedPattern)
        ];

        for (const match of matches) {
            if (!match) {
                continue;
            }

            const inverted = match === matches[1];
            const produto = cleanOfficeProductName(match[1]);
            const unidade = normalizeUnitLabel(inverted ? match[3] : match[2]);
            const quantidade = Number(String(inverted ? match[2] : match[3]).replace(',', '.'));

            if (!isValidOfficeProduct(produto, quantidade)) {
                continue;
            }

            return {
                produto,
                unidade,
                quantidade,
                valor_unitario: 0,
                valor_total: 0
            };
        }

        if (!options.allowMissingUnit) {
            return null;
        }

        const fallback = clean.match(/^(.+?)\s+(\d+(?:[,.]\d+)?)$/u);
        if (!fallback || currencyMatches.length > 0) {
            return null;
        }

        const produto = cleanOfficeProductName(fallback[1]);
        const quantidade = Number(String(fallback[2]).replace(',', '.'));

        if (!isValidOfficeProduct(produto, quantidade)) {
            return null;
        }

        return {
            produto,
            unidade: 'UN',
            quantidade,
            valor_unitario: 0,
            valor_total: 0
        };
    }

    function parseOfficeItemBuffer(buffer) {
        const match = cleanImportedLine(buffer).match(/^(\d{1,3})\s*[\).:-]?\s+(.+)$/u);

        if (!match) {
            return null;
        }

        const ordem = Number(match[1]);
        if (!Number.isFinite(ordem) || ordem <= 0 || ordem > 300) {
            return null;
        }

        return parseOfficeMaterialPayload(match[2], { allowMissingUnit: true });
    }

    function parseOfficeItemsTableText(text) {
        const lines = String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .map(cleanImportedLine)
            .filter(Boolean);
        const items = [];
        const seen = new Set();
        let buffer = '';

        function addItem(item) {
            if (!item) {
                return;
            }

            const key = `${normalizeToken(item.produto)}|${normalizeUnitLabel(item.unidade)}|${item.quantidade}`;
            if (seen.has(key)) {
                return;
            }

            seen.add(key);
            items.push(item);
        }

        function flushBuffer() {
            if (!buffer) {
                return;
            }

            addItem(parseOfficeItemBuffer(buffer));
            buffer = '';
        }

        for (const line of lines) {
            const startsNumberedRow = /^\d{1,3}\s*[\).:-]?\s+/u.test(line);
            const isBareRowNumber = /^\d{1,3}\s*[\).:-]?\s*$/u.test(line);

            if (startsNumberedRow || (isBareRowNumber && !buffer)) {
                flushBuffer();
                buffer = line;
                continue;
            }

            if (buffer) {
                if (isOfficeListTerminator(line)) {
                    flushBuffer();
                    continue;
                }

                const currentItem = parseOfficeItemBuffer(buffer);
                const standaloneItem = isIgnorableOfficeLine(line) ? null : parseOfficeMaterialPayload(line);

                if (isBareRowNumber && currentItem) {
                    flushBuffer();
                    buffer = line;
                    continue;
                }

                if (currentItem && standaloneItem) {
                    flushBuffer();
                    addItem(standaloneItem);
                    continue;
                }

                if (!isIgnorableOfficeLine(line)) {
                    buffer = `${buffer} ${line}`;
                }

                continue;
            }

            if (!isIgnorableOfficeLine(line)) {
                addItem(parseOfficeMaterialPayload(line));
            }
        }

        flushBuffer();

        return buildBudgetParseResult(items);
    }

    function isOfficeColumnNoise(line) {
        const normalized = normalizeToken(line);

        return !normalized
            || normalized.startsWith('DESCRI')
            || normalized === 'DESCRICAO'
            || normalized === 'ITENS'
            || normalized === 'ITEM'
            || normalized === 'UNID'
            || normalized === 'QUANT'
            || normalized.includes('QUANTCOMPRADA')
            || normalized.includes('RROCRY');
    }

    function isOfficeDescriptionContinuation(previous, current) {
        const currentNormalized = normalizeToken(current);
        const previousText = String(previous || '').trim();

        return [
            'ACOPLADA',
            'COLUNA',
            'COLU',
            'NA',
            'DA',
            'DO',
            '100MM',
            '6M',
            'ERECOZIDO',
            'ELETRICO25MM',
            'VCBRANCO',
            'LOGRANDE'
        ].includes(currentNormalized)
            || (currentNormalized.length <= 3 && normalizeToken(previous).length >= 6)
            || (normalizeToken(previous).includes('LOU') && ['COLU', 'NA', 'COLUNA'].includes(currentNormalized))
            || (/\b(C\/?|DE)$/iu.test(previousText) && currentNormalized.length <= 16);
    }

    function parseOfficeDescriptionColumn(text) {
        const descriptions = [];
        let current = '';

        function pushCurrent() {
            const produto = cleanOfficeProductName(current);
            if (produto && isValidOfficeProduct(produto, 1)) {
                descriptions.push(produto);
            }
            current = '';
        }

        String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .map(cleanImportedLine)
            .forEach(line => {
                const clean = cleanOfficeProductName(line.replace(/[|\[\]_=]+/g, ' '));
                if (isOfficeColumnNoise(clean)) {
                    return;
                }

                if (!current) {
                    current = clean;
                    return;
                }

                if (isOfficeDescriptionContinuation(current, clean)) {
                    current = `${current} ${clean}`;
                    return;
                }

                pushCurrent();
                current = clean;
            });

        pushCurrent();

        return descriptions;
    }

    function parseOfficeUnitQuantityLine(line) {
        const clean = cleanImportedLine(line).replace(/[|\[\]_=]+/g, ' ');
        const normalized = normalizeToken(clean);

        if (!normalized || normalized.includes('UNIDQUANT') || normalized === 'QUANT') {
            return null;
        }

        const unitMatch = clean.match(new RegExp(`(?:^|\\s)(${officeUnitPattern()})(?=\\s|\\d|$)`, 'iu'));
        const numberMatches = clean.match(/\d{1,5}(?:[,.]\d+)?/gu) || [];
        const rawQuantity = numberMatches.length ? numberMatches[numberMatches.length - 1] : '';
        const quantidade = rawQuantity ? Number(rawQuantity.replace(',', '.')) : 0;

        return {
            unidade: unitMatch ? normalizeUnitLabel(unitMatch[1]) : 'UN',
            quantidade: Number.isFinite(quantidade) && quantidade > 0 ? quantidade : 0
        };
    }

    function parseOfficeUnitQuantityColumn(text) {
        return String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .map(parseOfficeUnitQuantityLine)
            .filter(Boolean);
    }

    function buildOfficeRowsFromColumnOcr(descriptionText, unitQuantityText) {
        const descriptions = parseOfficeDescriptionColumn(descriptionText);
        const unitsAndQuantities = parseOfficeUnitQuantityColumn(unitQuantityText);

        if (descriptions.length < 4) {
            return '';
        }

        let missingQuantities = 0;
        const rows = descriptions.map((produto, index) => {
            const parsed = unitsAndQuantities[index] || {};
            const unidade = parsed.unidade || 'UN';
            const quantidade = parsed.quantidade > 0 ? parsed.quantidade : 1;

            if (!parsed.quantidade) {
                missingQuantities++;
            }

            return `${index + 1}. ${produto} ${unidade} ${quantidade}`;
        });

        const warning = missingQuantities > 0
            ? `@@OCR_QTY_WARNING:${missingQuantities}@@`
            : '';

        return [warning, ...rows].filter(Boolean).join('\n');
    }

    function parseGenericTextBudget(text) {
        const lines = String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .map(line => line.replace(/\s+/g, ' ').trim())
            .filter(Boolean);
        const units = '(UN|UND|UNID|UNIDADE|CX|CAIXA|PCT|PACOTE|FARDO|DZ|DUZIA|DÚZIA|KG|G|LT|L|MT|M|M2|M3|PAR|PC|PÇ|SERV|SV)';
        const patterns = [
            new RegExp(`^(?:\\d{1,8}\\s+)?(.+?)\\s+${units}\\s+(\\d+(?:[,.]\\d+)?)\\s+(?:R\\$\\s*)?([\\d.]+,\\d{2})\\s+(?:R\\$\\s*)?([\\d.]+,\\d{2})$`, 'iu'),
            new RegExp(`^(?:\\d{1,8}\\s+)?(\\d+(?:[,.]\\d+)?)\\s+${units}\\s+(.+?)\\s+(?:R\\$\\s*)?([\\d.]+,\\d{2})\\s+(?:R\\$\\s*)?([\\d.]+,\\d{2})$`, 'iu'),
            /^(.+?)\s+(\d+(?:[,.]\d+)?)\s+(?:R\$\s*)?([\d.]+,\d{2})\s+(?:R\$\s*)?([\d.]+,\d{2})$/iu
        ];
        const items = [];

        for (const line of lines) {
            const normalized = normalizeToken(line);
            if (
                normalized.includes('CNPJ')
                || normalized.includes('CLIENTE')
                || normalized.includes('VENDEDOR')
                || normalized.includes('TOTAL')
                || normalized.includes('SUBTOTAL')
                || normalized.includes('PAGAMENTO')
                || normalized.includes('OBSERVACAO')
                || normalized.includes('DESCONTO')
            ) {
                continue;
            }

            let match = line.match(patterns[0]);
            if (match) {
                items.push({
                    produto: match[1].trim(),
                    unidade: match[2].trim().toUpperCase(),
                    quantidade: Number(match[3].replace(',', '.')),
                    valor_unitario: parsePlanilhaMoney(match[4]),
                    valor_total: parsePlanilhaMoney(match[5])
                });
                continue;
            }

            match = line.match(patterns[1]);
            if (match) {
                items.push({
                    produto: match[3].trim(),
                    unidade: match[2].trim().toUpperCase(),
                    quantidade: Number(match[1].replace(',', '.')),
                    valor_unitario: parsePlanilhaMoney(match[4]),
                    valor_total: parsePlanilhaMoney(match[5])
                });
                continue;
            }

            match = line.match(patterns[2]);
            if (match) {
                items.push({
                    produto: match[1].trim(),
                    unidade: 'UN',
                    quantidade: Number(match[2].replace(',', '.')),
                    valor_unitario: parsePlanilhaMoney(match[3]),
                    valor_total: parsePlanilhaMoney(match[4])
                });
            }
        }

        return buildBudgetParseResult(items);
    }

    function budgetMoneyPattern() {
        return '(?:R\\$|RS|R\\s*\\$)?\\s*(?:\\d{1,3}(?:[.\\s]\\d{3})+|\\d+),\\d{2}\\s*(?:R\\$)?';
    }

    function findBudgetMoneyMatches(text) {
        return Array.from(String(text || '').matchAll(new RegExp(budgetMoneyPattern(), 'giu')));
    }

    function cleanServiceBudgetProduct(value) {
        let clean = cleanImportedLine(value)
            .replace(/\b(DESCRI[CÇ][AÃ]O\s+DO\s+SERVI[CÇ]O|DESCRI[CÇ][AÃ]O|SERVI[CÇ]OS?\s+A\s+SER\s+REALIZADO|VLR\s+UNIT|VLR\s+TOTAL|VALOR\s+UNIT[AÁ]RIO|VALOR\s+TOTAL|QUANT\.?|ITEM)\b/giu, ' ')
            .replace(/[|_\[\]]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        const caracteristicasIndex = clean.search(/\bCaracter\S{0,3}sticas?\s*:/iu);

        if (caracteristicasIndex > 6) {
            clean = clean.slice(0, caracteristicasIndex).trim();
        }

        return clean
            .replace(/^\d{1,5}\s*[\).:-]?\s+/u, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function parsePricedServiceBudgetBlock(block) {
        const normalized = cleanImportedLine(block)
            .replace(/\bRS\b/gi, 'R$')
            .replace(/\s+/g, ' ')
            .trim();
        const moneyMatches = findBudgetMoneyMatches(normalized);

        if (moneyMatches.length < 2) {
            return null;
        }

        const unitMoneyMatch = moneyMatches[moneyMatches.length - 2];
        const totalMoneyMatch = moneyMatches[moneyMatches.length - 1];
        const beforeUnitMoney = normalized.slice(0, unitMoneyMatch.index).trim();
        const qtyMatch = beforeUnitMoney.match(/(?:^|\s)(\d{1,5}(?:[,.]\d+)?)\s*$/u);

        if (!qtyMatch) {
            return null;
        }

        const qtyIndex = qtyMatch.index ?? beforeUnitMoney.lastIndexOf(qtyMatch[1]);
        const produto = cleanServiceBudgetProduct(beforeUnitMoney.slice(0, qtyIndex));
        const quantidade = parseImportedQuantity(qtyMatch[1]);
        const valorUnitario = parsePlanilhaMoney(unitMoneyMatch[0]);
        const valorTotal = parsePlanilhaMoney(totalMoneyMatch[0]);

        if (!produto || normalizeToken(produto).length < 4 || quantidade <= 0 || valorUnitario <= 0 || valorTotal <= 0) {
            return null;
        }

        return {
            produto,
            unidade: 'SERV',
            quantidade,
            valor_unitario: valorUnitario,
            valor_total: valorTotal
        };
    }

    function isPricedServiceRowStart(line) {
        const normalized = normalizeToken(line);

        return /^\d{1,5}\s*[\).:-]?\s+/u.test(line)
            || /^(INSTALA[CÇ][AÃ]O|MANUTEN[CÇ][AÃ]O|CARGA|HIGIENIZA[CÇ][AÃ]O|LIMPEZA|SERVI[CÇ]O|TROCA|CONSERTO|REPARO|FORNECIMENTO)\b/iu.test(line)
            || normalized.includes('ARCONDICIONADO')
            || normalized.includes('SPLIT');
    }

    function parsePricedServiceBudgetText(text) {
        const lines = String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .map(line => line.replace(/[|_]+/g, ' ').replace(/\s+/g, ' ').trim())
            .filter(Boolean);
        const items = [];
        let buffer = '';

        function flushBuffer() {
            if (!buffer) {
                return;
            }

            const parsed = parsePricedServiceBudgetBlock(buffer);
            if (parsed) {
                items.push(parsed);
            }

            buffer = '';
        }

        for (const line of lines) {
            const normalized = normalizeToken(line);
            if (
                (normalized.includes('VALORTOTAL') && !findBudgetMoneyMatches(line).length)
                || normalized.includes('CNPJ')
                || normalized.includes('CPF')
                || normalized.includes('ENDERECO')
                || normalized.includes('AUTORIZACAO')
            ) {
                flushBuffer();
                continue;
            }

            if (isPricedServiceRowStart(line) && buffer) {
                const parsed = parsePricedServiceBudgetBlock(buffer);
                if (parsed) {
                    items.push(parsed);
                    buffer = '';
                }
            }

            buffer = `${buffer} ${line}`.trim();

            if (findBudgetMoneyMatches(buffer).length >= 2) {
                flushBuffer();
            }
        }

        flushBuffer();

        return buildBudgetParseResult(items);
    }

    function parseSingleLineBudgetSummary(text) {
        const normalized = String(text || '')
            .normalize('NFC')
            .replace(/\s+/g, ' ')
            .trim();
        const quantityMatch = normalized.match(/\bQuantidade\b\s+(\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:[,.]\d+)?)/iu);
        const tableMatch = normalized.match(/Produto\s+Utilizado\s+ou\s+Servi[cç?]o\s+Prestado\s+Valor\s+Unit[aá?]rio\s+Valor\s+Total\s+(.+?)(?=\s+(?:ENDERE[CÇ?]O|CPF\/CNPJ|OR[CÇ?]AMENTO|DATA|NOME\s+CLIENTE)\b|$)/iu);

        if (!tableMatch) {
            return null;
        }

        const rowText = cleanImportedLine(tableMatch[1]).replace(/\bRS\b/gi, 'R$');
        const moneyMatches = findBudgetMoneyMatches(rowText);
        if (moneyMatches.length < 2) {
            return null;
        }

        const unitMoneyMatch = moneyMatches[moneyMatches.length - 2];
        const totalMoneyMatch = moneyMatches[moneyMatches.length - 1];
        const produto = cleanServiceBudgetProduct(rowText.slice(0, unitMoneyMatch.index));
        const valorUnitario = parsePlanilhaMoney(unitMoneyMatch[0]);
        const valorTotal = parsePlanilhaMoney(totalMoneyMatch[0]);
        const quantidade = quantityMatch
            ? parseImportedQuantity(quantityMatch[1])
            : (valorUnitario > 0 ? valorTotal / valorUnitario : 0);

        if (!produto || quantidade <= 0 || valorUnitario <= 0 || valorTotal <= 0) {
            return null;
        }

        return buildBudgetParseResult([{
            produto,
            unidade: 'UN',
            quantidade,
            valor_unitario: valorUnitario,
            valor_total: valorTotal
        }], valorTotal);
    }

    function parseSiahPositionedBudget(rows) {
        if (!Array.isArray(rows) || !rows.length) {
            return null;
        }

        const items = [];
        const rowsByPage = new Map();

        rows.forEach(row => {
            const pageRows = rowsByPage.get(row.page) || [];
            pageRows.push(row);
            rowsByPage.set(row.page, pageRows);
        });

        rowsByPage.forEach(pageRows => {
            const orderedRows = pageRows
                .slice()
                .sort((a, b) => b.y - a.y);
            const headerIndex = orderedRows.findIndex(row => {
                const text = normalizeToken(getRowText(row));
                return (text.includes('CODIGO') || text.includes('DIGO'))
                    && text.includes('UND')
                    && text.includes('DESCRI')
                    && text.includes('QTDE')
                    && text.includes('TOTAL');
            });

            if (headerIndex < 0) {
                return;
            }

            const headerItems = orderedRows[headerIndex].items.slice().sort((a, b) => a.x - b.x);
            const codeX = findHeaderColumnX(headerItems, /DIGO$/i);
            const unitX = findHeaderColumnX(headerItems, /^UND$/i);
            const descX = findHeaderColumnX(headerItems, /^DESCRI/i);
            const fabX = findHeaderColumnX(headerItems, /^FABRICANTE$/i);
            const pesoX = findHeaderColumnX(headerItems, /^PESO/i);
            const qtdeX = findHeaderColumnX(headerItems, /^QTDE$/i);
            const valorX = findHeaderColumnX(headerItems, /^VUNIT$/i);
            const totalX = findHeaderColumnX(headerItems, /^TOTAL$/i);

            if (descX === null || fabX === null || qtdeX === null || valorX === null || totalX === null) {
                return;
            }

            const dataRows = orderedRows.slice(headerIndex + 1);
            for (const row of dataRows) {
                const rowText = getRowText(row);
                const normalizedRow = normalizeToken(rowText);

                if (normalizedRow.includes('SUBTOTAL') || normalizedRow.includes('PAGAMENTO') || normalizedRow.includes('OBSERVACAO')) {
                    break;
                }

                const rowItems = row.items.slice().sort((a, b) => a.x - b.x);
                const codeText = codeX === null ? rowText : collectColumnText(rowItems, codeX - 4, unitX ?? descX);
                if (!/^\d{1,8}/.test(codeText.trim())) {
                    continue;
                }

                const produto = collectColumnText(rowItems, descX - 2, fabX - 2);
                const unidade = unitX === null ? 'UN' : collectColumnText(rowItems, unitX - 2, descX - 2);
                const quantidade = firstQuantityFromText(collectColumnText(rowItems, qtdeX - 2, valorX - 2));
                const valorUnitario = firstCurrencyFromText(collectColumnText(rowItems, valorX - 2, totalX - 2));
                const valorTotal = firstCurrencyFromText(collectColumnText(rowItems, totalX - 2, Number.POSITIVE_INFINITY));

                if (!produto || quantidade <= 0) {
                    continue;
                }

                items.push({
                    produto,
                    unidade: unidade || 'UN',
                    quantidade,
                    valor_unitario: valorUnitario,
                    valor_total: valorTotal
                });
            }
        });

        return buildBudgetParseResult(items);
    }

    function parseCurrencyColumnsBudget(normalizedText) {
        const normalized = String(normalizedText || '').normalize('NFC').replace(/\s+/g, ' ').trim();
        const headerMatch = normalized.match(/\bORD\b.*?\bVALOR\s+L[ÍI]QUIDO\b/iu);
        const tableText = headerMatch
            ? normalized.slice((headerMatch.index || 0) + headerMatch[0].length)
            : normalized;
        const hasEmpresaColumn = /\bEMPRESA\b/iu.test(headerMatch?.[0] || '');
        const rowPattern = /(?:^|\s)(\d{1,4})\s+(.+?\s+R\$\s*[\d.]+,\d{2}\s+R\$\s*[\d.]+,\d{2})(?=\s+\d{1,4}\s+|\s+TOTAL\s+R\$|$)/giu;
        const itemPattern = /^(.+?)\s+(\S+)\s+(\d+(?:[,.]\d+)?)\s+R\$\s*([\d.]+,\d{2})\s+R\$\s*([\d.]+,\d{2})$/iu;
        const parsedRows = [];

        for (const match of tableText.matchAll(rowPattern)) {
            const rowText = String(match[2] || '').trim();
            const itemMatch = rowText.match(itemPattern);

            if (!itemMatch) {
                continue;
            }

            parsedRows.push({
                ordem: Number(match[1]),
                produtoBruto: itemMatch[1].trim(),
                unidade: itemMatch[2].trim().toUpperCase(),
                quantidade: Number(String(itemMatch[3]).replace(',', '.')),
                valor_unitario: parsePlanilhaMoney(itemMatch[4]),
                valor_total: parsePlanilhaMoney(itemMatch[5])
            });
        }

        if (!parsedRows.length) {
            return null;
        }

        const prefixTokens = hasEmpresaColumn
            ? getCommonPrefixTokens(parsedRows.map(row => row.produtoBruto))
            : [];

        const items = parsedRows.map(row => {
            const produto = stripPrefixTokens(row.produtoBruto, prefixTokens)
                .replace(/^(G\.?\s+RODRIGUES(?:\s+DE\s+OLIVEIRA\s+LTDA)?|G\s+RODRIGUES)\s+/iu, '')
                .trim();

            return {
                produto,
                unidade: row.unidade || 'UN',
                quantidade: row.quantidade,
                valor_unitario: row.valor_unitario,
                valor_total: row.valor_total
            };
        });

        const totalMatch = tableText.match(/\bTOTAL\s+R\$\s*([\d.]+,\d{2})/iu);
        const totalPdf = totalMatch ? parsePlanilhaMoney(totalMatch[1]) : 0;

        return buildBudgetParseResult(items, totalPdf);
    }

    function parseSiahVisualLinesBudget(text) {
        const lines = String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .map(line => line.replace(/\s+/g, ' ').trim())
            .filter(Boolean);
        const items = [];
        const rowPattern = /^(\d{1,8})\s+([A-ZÁÉÍÓÚÂÊÔÃÕÇ]{1,12})\s+(.+)\s+([A-ZÁÉÍÓÚÂÊÔÃÕÇ0-9.\/-]{2,}(?:\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ0-9.\/-]{2,}){0,2})\s+[\d.,]+\s+(\d+(?:[,.]\d+)?)\s+([\d.]+,\d{2})\s+([\d.]+,\d{2})$/iu;

        for (const line of lines) {
            const match = line.match(rowPattern);
            if (!match) {
                continue;
            }

            items.push({
                produto: match[3].trim(),
                unidade: match[2].trim().toUpperCase(),
                quantidade: Number(match[5].replace(',', '.')),
                valor_unitario: parsePlanilhaMoney(match[6]),
                valor_total: parsePlanilhaMoney(match[7])
            });
        }

        return buildBudgetParseResult(items);
    }

    function parseSiahBudget(text) {
        const rawText = String(text || '').normalize('NFC');
        const rowPattern = /(?:^|\s)(\d{1,8})\s+(\d+(?:[,.]\d+)?)([A-ZÁÉÍÓÚÂÊÔÃÕÇ]{1,12})\s+[\d.,]+\s+([\d.]+,\d{2})([A-ZÁÉÍÓÚÂÊÔÃÕÇ0-9 .\/-]*?)\s+([\d.]+,\d{2})\s*(.+?)(?=\s+\d{1,8}\s+\d+(?:[,.]\d+)?[A-ZÁÉÍÓÚÂÊÔÃÕÇ]{1,12}\s+[\d.,]+\s+[\d.]+,\d{2}[A-ZÁÉÍÓÚÂÊÔÃÕÇ]|\s+Subtotal:|\s+Desconto Comercial:|\s+Total\s+R\$:|$)/giu;
        const normalized = rawText.replace(/\s+/g, ' ').trim();
        const items = [];

        for (const match of normalized.matchAll(rowPattern)) {
            const produto = String(match[7] || '').trim();
            const quantidade = Number(String(match[2]).replace(',', '.'));
            const valorUnitario = parsePlanilhaMoney(match[4]);
            const valorTotal = parsePlanilhaMoney(match[6]);

            if (!produto || quantidade <= 0 || valorUnitario < 0) {
                continue;
            }

            items.push({
                produto,
                unidade: String(match[3] || 'UN').trim().toUpperCase(),
                quantidade,
                valor_unitario: valorUnitario,
                valor_total: valorTotal
            });
        }

        return buildBudgetParseResult(items);
    }

    function parsePlanilhaPdfText(input) {
        const text = typeof input === 'string' ? input : String(input?.text || '');
        const rows = typeof input === 'string' ? [] : (input?.rows || []);
        const source = typeof input === 'string' ? '' : String(input?.source || '');
        const normalized = String(text || '').normalize('NFC').replace(/\s+/g, ' ').trim();
        const qtyWarningMatch = text.match(/@@OCR_QTY_WARNING:(\d+)@@/u);

        const parsers = [
            () => parseSiahPositionedBudget(rows),
            () => parseDelimitedBudget(text),
            () => parseSingleLineBudgetSummary(text),
            () => parsePricedServiceBudgetText(text),
            () => parseOfficeItemsTableText(text),
            () => parseCurrencyColumnsBudget(normalized),
            () => parseSiahVisualLinesBudget(text),
            () => parseSiahBudget(text),
            () => parseGenericTextBudget(text)
        ];

        for (const parser of parsers) {
            const result = parser();
            if (result && result.items.length) {
                const hasValues = result.items.some(item => Number(item.valor_unitario || 0) > 0 || Number(item.valor_total || 0) > 0);
                if (source === 'image' && result.items.length === 1 && !hasValues) {
                    throw new Error('O OCR da imagem não encontrou uma lista de itens confiável. Tire a foto mais próxima da tabela, com a folha reta e boa iluminação, e tente novamente.');
                }

                if (qtyWarningMatch) {
                    result.warning = `${qtyWarningMatch[1]} quantidades não foram lidas com segurança e foram preenchidas com 1 para conferência.`;
                }

                return result;
            }
        }

        throw new Error('Não encontrei itens no arquivo. Use um orçamento com quantidade, unidade e valores ou um ofício/lista de materiais com descrição e quantidade legíveis.');
    }

    async function extractPdfText(file) {
        if (!window.pdfjsLib) {
            throw new Error('Não foi possível carregar o leitor de PDF. Verifique a conexão e tente novamente.');
        }

        const buffer = await file.arrayBuffer();
        const pdf = await window.pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
        const pages = [];
        const rows = [];

        function groupPageItemsByRow(items, pageNumber) {
            const groupedRows = [];

            items
                .slice()
                .sort((a, b) => Math.abs(b.y - a.y) > 2 ? b.y - a.y : a.x - b.x)
                .forEach(item => {
                    let row = groupedRows.find(candidate => Math.abs(candidate.y - item.y) <= 3);

                    if (!row) {
                        row = { page: pageNumber, y: item.y, items: [] };
                        groupedRows.push(row);
                    }

                    row.items.push(item);
                    row.y = row.items.reduce((sum, rowItem) => sum + rowItem.y, 0) / row.items.length;
                });

            return groupedRows.map(row => ({
                page: row.page,
                y: row.y,
                items: row.items
                    .slice()
                    .sort((a, b) => a.x - b.x)
                    .map(item => ({
                        text: item.text,
                        x: item.x,
                        y: item.y
                    }))
            }));
        }

        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
            const page = await pdf.getPage(pageNumber);
            const content = await page.getTextContent();
            const pageItems = content.items
                .map(item => ({
                    text: String(item.str || '').trim(),
                    x: Number(item.transform?.[4] || 0),
                    y: Number(item.transform?.[5] || 0),
                    hasEOL: Boolean(item.hasEOL)
                }))
                .filter(item => item.text !== '');
            const pageText = content.items.map(item => `${item.str || ''}${item.hasEOL ? '\n' : ' '}`).join('');

            pages.push(pageText);
            rows.push(...groupPageItemsByRow(pageItems, pageNumber));
        }

        if (!pages.join(' ').replace(/\s+/g, '') && !rows.length) {
            const ocrPages = [];

            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                const page = await pdf.getPage(pageNumber);
                const viewport = page.getViewport({ scale: 2 });
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d', { willReadFrequently: true });

                if (!context) {
                    continue;
                }

                setPlanilhaStatus(`PDF escaneado: aplicando OCR local na página ${pageNumber} de ${pdf.numPages}...`, 'warning');
                canvas.width = Math.round(viewport.width);
                canvas.height = Math.round(viewport.height);
                await page.render({ canvasContext: context, viewport }).promise;

                const blob = await new Promise(resolve => {
                    canvas.toBlob(imageBlob => resolve(imageBlob), 'image/png');
                });

                if (!blob) {
                    continue;
                }

                const pageText = await extractImageText(blob);
                ocrPages.push(pageText?.text || '');
            }

            return {
                text: ocrPages.join('\n'),
                rows: [],
                source: 'image'
            };
        }

        return {
            text: pages.join(' '),
            rows
        };
    }

    async function extractDocxText(file) {
        if (!window.mammoth?.extractRawText) {
            throw new Error('Não foi possível carregar o leitor de Word local.');
        }

        const result = await window.mammoth.extractRawText({ arrayBuffer: await file.arrayBuffer() });
        return {
            text: result.value || '',
            rows: []
        };
    }

    function loadImageForOcr(file) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            const url = URL.createObjectURL(file);

            image.onload = () => {
                URL.revokeObjectURL(url);
                resolve(image);
            };

            image.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Não foi possível abrir a imagem para OCR.'));
            };

            image.src = url;
        });
    }

    async function prepareImageForOcr(file, options = {}) {
        try {
            const image = await loadImageForOcr(file);
            const width = image.naturalWidth || image.width;
            const height = image.naturalHeight || image.height;

            if (!width || !height) {
                return file;
            }

            const rotation = ((Number(options.rotate || 0) % 360) + 360) % 360;
            const rotatedWidth = rotation === 90 || rotation === 270 ? height : width;
            const rotatedHeight = rotation === 90 || rotation === 270 ? width : height;
            const crop = options.crop || { x: 0, y: 0, width: 1, height: 1 };
            const sourceX = Math.max(0, Math.round(rotatedWidth * crop.x));
            const sourceY = Math.max(0, Math.round(rotatedHeight * crop.y));
            const sourceWidth = Math.max(1, Math.min(rotatedWidth - sourceX, Math.round(rotatedWidth * crop.width)));
            const sourceHeight = Math.max(1, Math.min(rotatedHeight - sourceY, Math.round(rotatedHeight * crop.height)));
            const longSide = Math.max(sourceWidth, sourceHeight);
            const targetLongSide = options.targetLongSide || (longSide < 1600 ? 1600 : Math.min(longSide, 2200));
            const scale = Math.min(options.maxScale || 4, targetLongSide / longSide);
            const rotatedCanvas = document.createElement('canvas');
            const canvas = document.createElement('canvas');

            rotatedCanvas.width = rotatedWidth;
            rotatedCanvas.height = rotatedHeight;
            canvas.width = Math.max(1, Math.round(sourceWidth * scale));
            canvas.height = Math.max(1, Math.round(sourceHeight * scale));

            const rotatedContext = rotatedCanvas.getContext('2d', { willReadFrequently: true });
            const context = canvas.getContext('2d', { willReadFrequently: true });
            if (!rotatedContext || !context) {
                return file;
            }

            rotatedContext.save();
            if (rotation === 90) {
                rotatedContext.translate(rotatedWidth, 0);
                rotatedContext.rotate(Math.PI / 2);
            } else if (rotation === 180) {
                rotatedContext.translate(rotatedWidth, rotatedHeight);
                rotatedContext.rotate(Math.PI);
            } else if (rotation === 270) {
                rotatedContext.translate(0, rotatedHeight);
                rotatedContext.rotate(-Math.PI / 2);
            }
            rotatedContext.drawImage(image, 0, 0);
            rotatedContext.restore();

            context.drawImage(
                rotatedCanvas,
                sourceX,
                sourceY,
                sourceWidth,
                sourceHeight,
                0,
                0,
                canvas.width,
                canvas.height
            );

            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
            const pixels = imageData.data;

            for (let index = 0; index < pixels.length; index += 4) {
                const gray = (pixels[index] * 0.299) + (pixels[index + 1] * 0.587) + (pixels[index + 2] * 0.114);
                const adjusted = Math.max(0, Math.min(255, ((gray - 128) * 1.35) + 128));

                pixels[index] = adjusted;
                pixels[index + 1] = adjusted;
                pixels[index + 2] = adjusted;
            }

            if (options.removeLines) {
                const widthPixels = canvas.width;
                const heightPixels = canvas.height;
                const rowsToClear = new Set();
                const colsToClear = new Set();

                for (let y = 0; y < heightPixels; y++) {
                    let darkPixels = 0;
                    for (let x = 0; x < widthPixels; x++) {
                        if (pixels[((y * widthPixels) + x) * 4] < 115) {
                            darkPixels++;
                        }
                    }

                    if (darkPixels > widthPixels * 0.42) {
                        for (let offset = -1; offset <= 1; offset++) {
                            const targetY = y + offset;
                            if (targetY >= 0 && targetY < heightPixels) {
                                rowsToClear.add(targetY);
                            }
                        }
                    }
                }

                for (let x = 0; x < widthPixels; x++) {
                    let darkPixels = 0;
                    for (let y = 0; y < heightPixels; y++) {
                        if (pixels[((y * widthPixels) + x) * 4] < 115) {
                            darkPixels++;
                        }
                    }

                    if (darkPixels > heightPixels * 0.35) {
                        for (let offset = -1; offset <= 1; offset++) {
                            const targetX = x + offset;
                            if (targetX >= 0 && targetX < widthPixels) {
                                colsToClear.add(targetX);
                            }
                        }
                    }
                }

                rowsToClear.forEach(y => {
                    for (let x = 0; x < widthPixels; x++) {
                        const pixelIndex = ((y * widthPixels) + x) * 4;
                        pixels[pixelIndex] = 255;
                        pixels[pixelIndex + 1] = 255;
                        pixels[pixelIndex + 2] = 255;
                    }
                });

                colsToClear.forEach(x => {
                    for (let y = 0; y < heightPixels; y++) {
                        const pixelIndex = ((y * widthPixels) + x) * 4;
                        pixels[pixelIndex] = 255;
                        pixels[pixelIndex + 1] = 255;
                        pixels[pixelIndex + 2] = 255;
                    }
                });
            }

            context.putImageData(imageData, 0, 0);

            return await new Promise(resolve => {
                canvas.toBlob(blob => resolve(blob || file), 'image/png');
            });
        } catch (error) {
            console.warn(error);
            return file;
        }
    }

    function isBudgetColumnNoise(line) {
        const normalized = normalizeToken(line);

        return !normalized
            || normalized === 'ITEM'
            || normalized === 'QUANT'
            || normalized.includes('DESCRICAO')
            || normalized.includes('SERVICO')
            || normalized.includes('VLRUNIT')
            || normalized.includes('VLRTOTAL')
            || normalized.includes('VALORUNITARIO')
            || normalized.includes('VALORTOTAL')
            || normalized.includes('CARACTERISTICAS')
            || normalized.includes('CNPJ')
            || normalized.includes('ENDERECO');
    }

    function parseServiceDescriptionColumn(text) {
        const descriptions = [];
        let current = '';

        function pushCurrent() {
            const produto = cleanServiceBudgetProduct(current);
            if (produto && normalizeToken(produto).length >= 4) {
                descriptions.push(produto);
            }
            current = '';
        }

        String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .map(line => line.replace(/[|_\[\]]+/g, ' ').replace(/\s+/g, ' ').trim())
            .filter(Boolean)
            .forEach(line => {
                if (isBudgetColumnNoise(line) || findBudgetMoneyMatches(line).length) {
                    return;
                }

                const startsRow = isPricedServiceRowStart(line);

                if (startsRow && current) {
                    pushCurrent();
                }

                current = `${current} ${line}`.trim();
            });

        pushCurrent();

        return descriptions;
    }

    function parseBudgetQuantityColumn(text) {
        return String(text || '')
            .normalize('NFC')
            .split(/\r?\n/)
            .flatMap(line => line.match(/\d{1,5}(?:[,.]\d+)?/gu) || [])
            .map(parseImportedQuantity)
            .filter(value => value > 0 && value <= 99999);
    }

    function parseBudgetMoneyColumn(text) {
        return findBudgetMoneyMatches(String(text || '').replace(/\bRS\b/gi, 'R$'))
            .map(match => parsePlanilhaMoney(match[0]))
            .filter(value => value > 0);
    }

    function buildPricedRowsFromColumnOcr(descriptionText, quantityText, unitValueText, totalValueText) {
        const descriptions = parseServiceDescriptionColumn(descriptionText);
        const quantities = parseBudgetQuantityColumn(quantityText);
        const unitValues = parseBudgetMoneyColumn(unitValueText);
        const totalValues = parseBudgetMoneyColumn(totalValueText);
        const count = Math.min(descriptions.length, quantities.length, unitValues.length, totalValues.length);

        if (count < 1) {
            return '';
        }

        return descriptions
            .slice(0, count)
            .map((produto, index) => `${index + 1}. ${produto} ${quantities[index]} ${formatInputMoneyBR(unitValues[index])} ${formatInputMoneyBR(totalValues[index])}`)
            .join('\n');
    }

    function scorePricedBudgetRows(text) {
        const parsed = parsePricedServiceBudgetText(text);
        return parsed?.items?.length || 0;
    }

    async function extractImageText(file) {
        if (!window.Tesseract?.createWorker) {
            throw new Error('OCR local não está disponível neste navegador.');
        }

        setPlanilhaStatus('Lendo imagem com OCR local. Isso pode levar alguns segundos...', 'warning');

        const worker = await window.Tesseract.createWorker('por', 1, {
            workerPath: 'assets/js/vendor/tesseract/worker.min.js',
            corePath: 'assets/js/vendor/tesseract-core',
            langPath: 'assets/js/vendor/tessdata',
            gzip: true,
            logger: message => {
                if (!message?.status) return;

                const progress = message.progress
                    ? ` ${Math.round(message.progress * 100)}%`
                    : '';
                setPlanilhaStatus(`OCR local: ${message.status}${progress}`, 'warning');
            }
        });

        try {
            function scoreOfficeRows(text) {
                const parsed = parseOfficeItemsTableText(text);
                if (!parsed?.items?.length) {
                    return 0;
                }

                const missingMatch = String(text || '').match(/@@OCR_QTY_WARNING:(\d+)@@/u);
                const missing = missingMatch ? Number(missingMatch[1]) : 0;
                return parsed.items.length - (missing * 0.12);
            }

            async function recognizeRegion(label, psm, crop, options = {}) {
                const rotateLabel = options.rotate ? ` rotação ${options.rotate}°` : '';
                setPlanilhaStatus(`OCR local: analisando ${label}${rotateLabel}...`, 'warning');

                if (worker.setParameters) {
                    await worker.setParameters({
                        preserve_interword_spaces: '1',
                        tessedit_pageseg_mode: String(psm)
                    });
                }

                const imageForOcr = await prepareImageForOcr(file, {
                    crop,
                    rotate: options.rotate || 0,
                    removeLines: Boolean(options.removeLines),
                    targetLongSide: options.targetLongSide || 2600,
                    maxScale: 5
                });
                const result = await worker.recognize(imageForOcr);
                return result?.data?.text || '';
            }

            const image = await loadImageForOcr(file);
            const imageWidth = image.naturalWidth || image.width || 0;
            const imageHeight = image.naturalHeight || image.height || 0;
            const rotations = imageWidth > imageHeight
                ? [90, 270, 0, 180]
                : [0, 90, 270, 180];
            const pricedRotations = imageWidth > imageHeight
                ? [90, 270]
                : [0, 180];
            let bestPricedRows = { text: '', score: 0, rotate: 0 };

            for (const rotate of pricedRotations) {
                const tableText = await recognizeRegion('tabela com valores', 6, {
                    x: 0.07,
                    y: 0.12,
                    width: 0.91,
                    height: 0.82
                }, { rotate, removeLines: true, targetLongSide: 3000 });
                const directScore = scorePricedBudgetRows(tableText);

                if (directScore > bestPricedRows.score) {
                    bestPricedRows = { text: tableText, score: directScore, rotate };
                }

                if (directScore >= 1) {
                    break;
                }

                if (findBudgetMoneyMatches(tableText).length < 2) {
                    continue;
                }

                const columnPresets = [
                    { label: 'principal', y: 0.24, height: 0.68 },
                    { label: 'continuação', y: 0.12, height: 0.78 }
                ];

                for (const preset of columnPresets) {
                    const serviceDescriptionText = await recognizeRegion(`descrição da tabela de valores ${preset.label}`, 4, {
                        x: 0.18,
                        y: preset.y,
                        width: 0.43,
                        height: preset.height
                    }, { rotate, removeLines: true, targetLongSide: 2800 });
                    const serviceQuantityText = await recognizeRegion(`quantidade da tabela de valores ${preset.label}`, 6, {
                        x: 0.60,
                        y: preset.y,
                        width: 0.10,
                        height: preset.height
                    }, { rotate, removeLines: true, targetLongSide: 2200 });
                    const serviceUnitValueText = await recognizeRegion(`valor unitário ${preset.label}`, 6, {
                        x: 0.69,
                        y: preset.y,
                        width: 0.15,
                        height: preset.height
                    }, { rotate, removeLines: true, targetLongSide: 2200 });
                    const serviceTotalValueText = await recognizeRegion(`valor total ${preset.label}`, 6, {
                        x: 0.83,
                        y: preset.y,
                        width: 0.15,
                        height: preset.height
                    }, { rotate, removeLines: true, targetLongSide: 2200 });
                    const serviceRowsText = buildPricedRowsFromColumnOcr(
                        serviceDescriptionText,
                        serviceQuantityText,
                        serviceUnitValueText,
                        serviceTotalValueText
                    );
                    const score = scorePricedBudgetRows(serviceRowsText);

                    if (score > bestPricedRows.score) {
                        bestPricedRows = { text: serviceRowsText, score, rotate };
                    }

                    if (score >= 1) {
                        break;
                    }
                }

                if (bestPricedRows.score >= 1) {
                    break;
                }
            }

            if (bestPricedRows.score >= 1) {
                return {
                    text: bestPricedRows.text,
                    rows: [],
                    source: 'image'
                };
            }

            let bestOfficeRows = { text: '', score: 0, rotate: 0 };

            for (const rotate of rotations) {
                const officeDescText = await recognizeRegion('coluna de descrição', 4, {
                    x: 0.19,
                    y: 0.42,
                    width: 0.31,
                    height: 0.50
                }, { rotate, removeLines: true });
                const officeUnitQuantityText = await recognizeRegion('coluna de unidade e quantidade', 6, {
                    x: 0.49,
                    y: 0.42,
                    width: 0.16,
                    height: 0.50
                }, { rotate, removeLines: true });
                const officeRowsText = buildOfficeRowsFromColumnOcr(officeDescText, officeUnitQuantityText);
                const score = scoreOfficeRows(officeRowsText);

                if (score > bestOfficeRows.score) {
                    bestOfficeRows = { text: officeRowsText, score, rotate };
                }

                if (score >= 18) {
                    break;
                }
            }

            if (bestOfficeRows.score >= 4) {
                return {
                    text: bestOfficeRows.text,
                    rows: [],
                    source: 'image'
                };
            }

            const fallbackRotate = bestOfficeRows.rotate || rotations[0] || 0;
            const tableText = await recognizeRegion('tabela do ofício', 6, {
                x: 0.129,
                y: 0.40625,
                width: 0.51,
                height: 0.508
            }, { rotate: fallbackRotate, removeLines: true });
            const fullText = await recognizeRegion('página inteira', 6, { x: 0, y: 0, width: 1, height: 1 }, {
                rotate: fallbackRotate,
                targetLongSide: 2200
            });

            return {
                text: [
                    tableText,
                    bestOfficeRows.text,
                    fullText
                ].filter(Boolean).join('\n'),
                rows: [],
                source: 'image'
            };
        } finally {
            await worker.terminate();
        }
    }

    async function extractBudgetFile(file) {
        const extension = file.name.split('.').pop()?.toLowerCase() || '';
        const mime = String(file.type || '').toLowerCase();

        if (extension === 'pdf' || mime === 'application/pdf') {
            return extractPdfText(file);
        }

        if (extension === 'docx' || mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return extractDocxText(file);
        }

        if (['txt', 'csv'].includes(extension) || mime.startsWith('text/')) {
            return {
                text: await file.text(),
                rows: []
            };
        }

        if (mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'webp'].includes(extension)) {
            return extractImageText(file);
        }

        throw new Error('Formato não suportado. Use PDF, DOCX, TXT, CSV, JPG, PNG ou WEBP.');
    }

    function getSuggestionPanel(input) {
        return input.closest('.item-name-group')?.querySelector('.item-suggestions') || null;
    }

    function hideSuggestions(input) {
        const panel = getSuggestionPanel(input);
        if (!panel) return;

        panel.classList.remove('show');
        panel.innerHTML = '';
        input.dataset.activeSuggestion = '-1';
    }

    function hideAllSuggestions() {
        container.querySelectorAll('.item-name').forEach(input => hideSuggestions(input));
    }

    function setActiveSuggestion(input, nextIndex) {
        const panel = getSuggestionPanel(input);
        if (!panel) return;

        const options = Array.from(panel.querySelectorAll('.suggestion-option'));
        if (!options.length) return;

        const safeIndex = Math.max(0, Math.min(nextIndex, options.length - 1));
        input.dataset.activeSuggestion = String(safeIndex);

        options.forEach((option, index) => {
            option.classList.toggle('active', index === safeIndex);
        });

        options[safeIndex].scrollIntoView({ block: 'nearest' });
    }

    function showSuggestionMessage(input, message, iconClass) {
        const panel = getSuggestionPanel(input);
        if (!panel) return;

        panel.innerHTML = `
            <div class="suggestion-loading">
                <i class="${iconClass}"></i> ${escapeHtml(message)}
            </div>
        `;
        panel.classList.add('show');
        input.dataset.activeSuggestion = '-1';
    }

    function renderSuggestions(input, items) {
        const panel = getSuggestionPanel(input);
        if (!panel) return;

        input._itemSuggestions = items;
        input.dataset.activeSuggestion = '-1';

        if (!items.length) {
            panel.innerHTML = `
                <div class="suggestion-empty">
                    <i class="fas fa-search"></i> Nenhum item encontrado no histórico.
                </div>
            `;
            panel.classList.add('show');
            return;
        }

        panel.innerHTML = items.map((item, index) => {
            const valor = Number(item.valor_unitario || 0);
            const valorLabel = valor > 0 ? formatMoneyBR(valor) : 'Sem valor';
            const usos = Number(item.usos || 0);

            return `
                <button type="button" class="suggestion-option" data-index="${index}" role="option">
                    <div class="suggestion-title">
                        <span class="suggestion-name">${escapeHtml(item.produto)}</span>
                        <span class="suggestion-price">${escapeHtml(valorLabel)}</span>
                    </div>
                    <div class="suggestion-meta">
                        <span class="suggestion-chip"><i class="fas fa-ruler-combined"></i> ${escapeHtml(item.unidade || 'UN')}</span>
                        <span class="suggestion-chip"><i class="fas fa-database"></i> ${escapeHtml(item.origem || 'Histórico')}</span>
                        <span class="suggestion-chip"><i class="fas fa-redo"></i> ${usos} uso${usos === 1 ? '' : 's'}</span>
                    </div>
                </button>
            `;
        }).join('');

        panel.classList.add('show');
    }

    function searchItemSuggestions(input) {
        const term = input.value.trim();
        const previousTimer = autocompleteTimers.get(input);

        if (previousTimer) {
            clearTimeout(previousTimer);
        }

        if (term.length < 2) {
            hideSuggestions(input);
            return;
        }

        const timer = setTimeout(async () => {
            const previousController = autocompleteControllers.get(input);
            if (previousController) {
                previousController.abort();
            }

            const controller = new AbortController();
            autocompleteControllers.set(input, controller);

            showSuggestionMessage(input, 'Buscando itens cadastrados...', 'fas fa-spinner fa-spin');

            try {
                const response = await fetch(`atribuir_itens.php?id=${encodeURIComponent(oficioId)}&ajax=sugerir_itens&q=${encodeURIComponent(term)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal
                });

                if (!response.ok) {
                    throw new Error('Falha na busca');
                }

                const data = await response.json();

                if (input.value.trim() !== term) {
                    return;
                }

                renderSuggestions(input, Array.isArray(data) ? data : []);
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                showSuggestionMessage(input, 'Não foi possível carregar sugestões agora.', 'fas fa-exclamation-circle');
            }
        }, 260);

        autocompleteTimers.set(input, timer);
    }

    function applySuggestion(input, item) {
        const row = input.closest('.item-row');
        if (!row || !item) return;

        input.value = item.produto || '';

        const unidadeInput = row.querySelector('input[name$="[unidade]"]');
        const valorInput = row.querySelector('.item-valor');
        const qtdInput = row.querySelector('.item-qtd');

        if (unidadeInput) {
            unidadeInput.value = item.unidade || 'UN';
        }

        if (valorInput && Number(item.valor_unitario || 0) > 0) {
            valorInput.value = formatInputMoneyBR(item.valor_unitario);
        }

        hideSuggestions(input);
        calculateTotal();

        if (qtdInput) {
            qtdInput.focus();
            qtdInput.select();
        }
    }

    function createItemRow(index, item = {}) {
        const produto = item.produto || '';
        const quantidade = item.quantidade ?? 1;
        const unidade = item.unidade || 'UN';
        const valorUnitario = Number(item.valor_unitario || 0);
        const hasValorUnitario = Object.prototype.hasOwnProperty.call(item, 'valor_unitario');
        const valorInput = hasValorUnitario ? formatInputMoneyBR(valorUnitario) : '';
        const totalItem = (Number(quantidade) || 0) * valorUnitario;
        const row = document.createElement('div');

        row.className = 'item-row';
        row.innerHTML = `
            <div class="form-group" style="margin:0;">
                <label class="form-label">Nº</label>
                <input type="text" class="form-control item-seq" value="${index + 1}" readonly>
            </div>

            <div class="form-group item-name-group" style="margin:0;">
                <label class="form-label">Nome do Item</label>
                <input type="text" name="produtos[${index}][nome]" class="form-control item-name" required autocomplete="off" placeholder="Ex: Papel A4" value="${escapeHtml(produto)}">
                <div class="item-suggestions" role="listbox"></div>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Quantidade</label>
                <input type="number" step="0.01" name="produtos[${index}][qtd]" class="form-control item-qtd" required value="${escapeHtml(quantidade)}">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Unidade</label>
                <input type="text" name="produtos[${index}][unidade]" class="form-control" value="${escapeHtml(unidade)}">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Valor Unitário</label>
                <input type="text" name="produtos[${index}][valor]" class="form-control item-valor" required placeholder="0,00" value="${escapeHtml(valorInput)}">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Total do Item</label>
                <input type="text" class="form-control item-total" value="${escapeHtml(formatMoneyBR(totalItem))}" readonly>
            </div>

            <div style="margin-bottom: 5px;">
                <button type="button" class="btn btn-outline btn-sm remove-item" style="color:red; border-color:#ff000033;">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

        return row;
    }

    function removeEmptyItemRows() {
        Array.from(container.querySelectorAll('.item-row')).forEach(row => {
            const produto = row.querySelector('.item-name')?.value.trim() || '';
            const quantidade = row.querySelector('.item-qtd')?.value.trim() || '';
            const valor = row.querySelector('.item-valor')?.value.trim() || '';
            const valorNumerico = parseValorBR(valor);

            if (produto === '' && (quantidade === '' || quantidade === '1') && (valor === '' || valorNumerico === 0)) {
                row.remove();
            }
        });
    }

    function appendImportedItems(items) {
        items.forEach(item => {
            const index = container.querySelectorAll('.item-row').length;
            container.appendChild(createItemRow(index, item));
        });
    }

    function renumberItems() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach((row, index) => {
            const seqInput = row.querySelector('.item-seq');
            if (seqInput) {
                seqInput.value = index + 1;
            }

            row.querySelectorAll('input[name^="produtos["]').forEach(input => {
                input.name = input.name.replace(/produtos\[\d+\]/, `produtos[${index}]`);
            });
        });
    }

    function updateItemTotals() {
        container.querySelectorAll('.item-row').forEach(row => {
            const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
            const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
            const totalItem = qtd * valorUnit;

            const totalField = row.querySelector('.item-total');
            if (totalField) {
                totalField.value = formatMoneyBR(totalItem);
            }
        });
    }

    function calculateTotal() {
        let total = 0;

        container.querySelectorAll('.item-row').forEach(row => {
            const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
            const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
            total += (qtd * valorUnit);
        });

        totalDisplay.textContent = formatMoneyBR(total);

        if (orcamentoPrevisto > 0) {
            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                totalDisplay.classList.add('diff-warning');
                totalDisplay.classList.remove('diff-ok');
            } else {
                totalDisplay.classList.add('diff-ok');
                totalDisplay.classList.remove('diff-warning');
            }
        }

        updateItemTotals();
    }

    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-valor')) {
            e.target.value = e.target.value.replace(/[^\d,.\s]/g, '');
        }

        if (e.target.classList.contains('item-name')) {
            searchItemSuggestions(e.target);
        }

        calculateTotal();
    });

    container.addEventListener('focusin', function(e) {
        if (e.target.classList.contains('item-name') && e.target.value.trim().length >= 2) {
            container.querySelectorAll('.item-name').forEach(input => {
                if (input !== e.target) {
                    hideSuggestions(input);
                }
            });
            searchItemSuggestions(e.target);
        }
    });

    container.addEventListener('keydown', function(e) {
        if (!e.target.classList.contains('item-name')) {
            return;
        }

        const input = e.target;
        const panel = getSuggestionPanel(input);
        if (!panel || !panel.classList.contains('show')) {
            return;
        }

        const options = Array.from(panel.querySelectorAll('.suggestion-option'));
        if (!options.length) {
            if (e.key === 'Escape') {
                hideSuggestions(input);
            }
            return;
        }

        const currentIndex = parseInt(input.dataset.activeSuggestion || '-1', 10);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveSuggestion(input, currentIndex + 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveSuggestion(input, currentIndex <= 0 ? options.length - 1 : currentIndex - 1);
        } else if (e.key === 'Enter' && currentIndex >= 0) {
            e.preventDefault();
            const item = input._itemSuggestions?.[currentIndex];
            applySuggestion(input, item);
        } else if (e.key === 'Escape') {
            hideSuggestions(input);
        }
    });

    container.addEventListener('mousedown', function(e) {
        const option = e.target.closest('.suggestion-option');
        if (!option) {
            return;
        }

        e.preventDefault();
        const group = option.closest('.item-name-group');
        const input = group?.querySelector('.item-name');
        const item = input?._itemSuggestions?.[parseInt(option.dataset.index || '-1', 10)];
        applySuggestion(input, item);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.item-name-group')) {
            hideAllSuggestions();
        }
    });

    if (planilhaBtn && planilhaInput) {
        planilhaBtn.addEventListener('click', function() {
            planilhaInput.click();
        });

        planilhaInput.addEventListener('change', async function() {
            const files = Array.from(planilhaInput.files || []);
            if (!files.length) {
                return;
            }

            const oversizedFile = files.find(file => file.size > 15 * 1024 * 1024);
            if (oversizedFile) {
                setPlanilhaStatus(`O arquivo "${oversizedFile.name}" é muito grande. Use arquivos de até 15 MB.`, 'error');
                planilhaInput.value = '';
                return;
            }

            planilhaBtn.disabled = true;
            setPlanilhaStatus(files.length === 1 ? 'Lendo arquivo e preparando os itens...' : `Lendo ${files.length} arquivos e preparando os itens...`, 'warning');

            try {
                const parsedResults = [];
                const failures = [];

                for (const file of files) {
                    try {
                        setPlanilhaStatus(`Lendo "${file.name}"...`, 'warning');
                        const extracted = await extractBudgetFile(file);
                        const result = parsePlanilhaPdfText(extracted);
                        parsedResults.push({ file, result });
                    } catch (error) {
                        console.error(error);
                        failures.push(`${file.name}: ${error.message || 'não foi possível importar'}`);
                    }
                }

                if (!parsedResults.length) {
                    throw new Error(failures[0] || 'Não foi possível importar os arquivos selecionados.');
                }

                removeEmptyItemRows();
                parsedResults.forEach(({ result }) => appendImportedItems(result.items));

                renumberItems();
                calculateTotal();

                const importedItems = parsedResults.flatMap(({ result }) => result.items);
                const importedTotal = parsedResults.reduce((sum, { result }) => sum + Number(result.totalItens || 0), 0);
                const importedFileCount = parsedResults.length;
                const importedItemCount = importedItems.length;
                const hasImportedValues = importedTotal > 0
                    || importedItems.some(item => Number(item.valor_unitario || 0) > 0 || Number(item.valor_total || 0) > 0);
                const totalStatus = hasImportedValues
                    ? ` Total importado agora: ${formatMoneyBR(importedTotal)}.`
                    : ' Itens sem preço foram carregados com valor 0,00 para conferência.';
                const warningStatus = parsedResults
                    .map(({ file, result }) => result.warning ? `${file.name}: ${result.warning}` : '')
                    .filter(Boolean)
                    .join(' ');
                const failureStatus = failures.length
                    ? ` ${failures.length} arquivo(s) não foram importados: ${failures.join(' | ')}`
                    : '';
                const fileLabel = importedFileCount === 1 ? '1 arquivo' : `${importedFileCount} arquivos`;
                const statusType = failures.length ? 'warning' : 'success';

                setPlanilhaStatus(`${importedItemCount} itens adicionados de ${fileLabel}.${totalStatus}${warningStatus ? ` ${warningStatus}` : ''}${failureStatus}`, statusType);
            } catch (error) {
                console.error(error);
                setPlanilhaStatus(error.message || 'Não foi possível importar o arquivo.', 'error');
            } finally {
                planilhaBtn.disabled = false;
                planilhaInput.value = '';
            }
        });
    }

    document.getElementById('add-item').addEventListener('click', function() {
        const index = container.querySelectorAll('.item-row').length;
        const row = createItemRow(index);

        container.appendChild(row);
        renumberItems();
        calculateTotal();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            const rows = container.querySelectorAll('.item-row');
            if (rows.length > 1) {
                e.target.closest('.item-row').remove();
                renumberItems();
                calculateTotal();
            }
        }
    });

    document.getElementById('items-form').addEventListener('submit', function(e) {
        renumberItems();

        if (orcamentoPrevisto > 0) {
            let total = 0;

            container.querySelectorAll('.item-row').forEach(row => {
                const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
                const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
                total += (qtd * valorUnit);
            });

            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                e.preventDefault();
                alert("Bloqueado: O valor total atual dos itens não corresponde ao Valor do Orçamento Previsto!\nPor favor, faça a correção das quantidades ou valores.");
                return false;
            }
        }
    });

    renumberItems();
    calculateTotal();
});
</script>

<?php include 'views/layout/footer.php'; ?>
