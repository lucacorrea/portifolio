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
                <h4 class="planilha-import-title"><i class="fas fa-file-import"></i> Importar orçamento PDF</h4>
                <p class="planilha-import-text">Aceita PDF, DOCX, TXT, CSV e imagens para conferência antes de salvar.</p>
            </div>
            <div>
                <input type="file" id="planilha-pdf-input" accept=".pdf,.docx,.txt,.csv,.jpg,.jpeg,.png,.webp,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/csv,image/*" hidden>
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
            .replace(/R\$/gi, '')
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

    function splitWords(value) {
        return String(value || '').trim().split(/\s+/).filter(Boolean);
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
        const parsedItems = items.filter(item => item.produto && item.quantidade > 0);

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
            if (!headerParts || headerParts.length < 4) {
                continue;
            }

            const headers = headerParts.map(part => part.trim());
            const productIndex = findHeaderIndex(headers, ['descri', 'descricao', 'produto', 'item', 'material', 'servico']);
            const quantityIndex = findHeaderIndex(headers, ['qtd', 'quantidade', 'qtde']);
            const unitIndex = findHeaderIndex(headers, ['und', 'unidade', 'unid']);
            const unitValueIndex = findHeaderIndex(headers, ['unit', 'valor unitario', 'vlr unitario', 'v unit', 'preco unitario', 'unitario']);
            const totalIndex = findHeaderIndex(headers, ['valor total', 'vlr total', 'total', 'subtotal']);

            if (productIndex < 0 || quantityIndex < 0 || unitValueIndex < 0) {
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
                const valorUnitario = parsePlanilhaMoney(parts[unitValueIndex] || '');
                const valorTotal = totalIndex >= 0 ? parsePlanilhaMoney(parts[totalIndex] || '') : quantidade * valorUnitario;

                if (!produto || quantidade <= 0) {
                    continue;
                }

                items.push({
                    produto,
                    unidade: unitIndex >= 0 ? String(parts[unitIndex] || 'UN').trim().toUpperCase() : 'UN',
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
        const normalized = String(text || '').normalize('NFC').replace(/\s+/g, ' ').trim();

        const parsers = [
            () => parseSiahPositionedBudget(rows),
            () => parseDelimitedBudget(text),
            () => parseCurrencyColumnsBudget(normalized),
            () => parseSiahVisualLinesBudget(text),
            () => parseSiahBudget(text),
            () => parseGenericTextBudget(text)
        ];

        for (const parser of parsers) {
            const result = parser();
            if (result && result.items.length) {
                return result;
            }
        }

        throw new Error('Não encontrei linhas de itens no PDF. Verifique se o arquivo segue um modelo de orçamento com quantidade, unidade, valor unitário e total.');
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
            const result = await worker.recognize(file);
            return {
                text: result?.data?.text || '',
                rows: []
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
        const valorInput = valorUnitario > 0 ? formatInputMoneyBR(valorUnitario) : '';
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
            const file = planilhaInput.files?.[0];
            if (!file) {
                return;
            }

            if (file.size > 15 * 1024 * 1024) {
                setPlanilhaStatus('O arquivo selecionado é muito grande. Use um arquivo de até 15 MB.', 'error');
                planilhaInput.value = '';
                return;
            }

            const hasTypedItems = Array.from(container.querySelectorAll('.item-name'))
                .some(input => input.value.trim() !== '');
            if (hasTypedItems && !window.confirm('Importar a planilha vai substituir os itens atuais do formulário. Continuar?')) {
                planilhaInput.value = '';
                return;
            }

            planilhaBtn.disabled = true;
            setPlanilhaStatus('Lendo arquivo e preparando os itens...', 'warning');

            try {
                const extracted = await extractBudgetFile(file);
                const result = parsePlanilhaPdfText(extracted);

                container.innerHTML = '';
                result.items.forEach((item, index) => {
                    container.appendChild(createItemRow(index, item));
                });

                renumberItems();
                calculateTotal();

                const totalStatus = result.totalPdf > 0 && Math.abs(result.totalPdf - result.totalItens) > 0.02
                    ? ` Total do PDF: ${formatMoneyBR(result.totalPdf)}. Total importado: ${formatMoneyBR(result.totalItens)}.`
                    : ` Total importado: ${formatMoneyBR(result.totalItens)}.`;

                setPlanilhaStatus(`${result.items.length} itens carregados da planilha.${totalStatus}`, 'success');
            } catch (error) {
                console.error(error);
                setPlanilhaStatus(error.message || 'Não foi possível importar a planilha PDF.', 'error');
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
