<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
sefaz_check();

$id = (int)($_GET['id'] ?? 0);

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
        $pdo->beginTransaction();

        $orcamento_esperado = (float)($oficio['valor_orcamento'] ?? 0);
        $total_calculado = 0;

        foreach ($produtos as $p) {
            if (!empty($p['nome'])) {
                $valorLimpo = str_replace('.', '', (string)($p['valor'] ?? '0'));
                $valorLimpo = str_replace(',', '.', $valorLimpo);
                $val = (float)$valorLimpo;
                $qtd = (float)($p['qtd'] ?? 0);
                $total_calculado += ($val * $qtd);
            }
        }

        if ($orcamento_esperado > 0 && abs($total_calculado - $orcamento_esperado) > 0.02) {
            throw new Exception("O valor total dos itens deve ser exatamente igual ao orçamento previsto de R$ " . number_format($orcamento_esperado, 2, ',', '.'));
        }

        $pdo->prepare("DELETE FROM itens_oficio WHERE oficio_id = ?")->execute([$id]);

        $stmt_ins = $pdo->prepare("
            INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($produtos as $p) {
            if (!empty($p['nome'])) {
                $valorLimpo = str_replace('.', '', (string)($p['valor'] ?? '0'));
                $valorLimpo = str_replace(',', '.', $valorLimpo);

                $stmt_ins->execute([
                    $id,
                    trim((string)$p['nome']),
                    (float)($p['qtd'] ?? 0),
                    !empty($p['unidade']) ? trim((string)$p['unidade']) : 'UN',
                    (float)$valorLimpo
                ]);
            }
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
                $items = !empty($items_existentes)
                    ? $items_existentes
                    : [['produto' => '', 'quantidade' => 1, 'unidade' => 'UN', 'valor_unitario' => 0]];

                foreach ($items as $idx => $it):
                    $qtd_item = (float)($it['quantidade'] ?? 0);
                    $valor_unit_item = (float)($it['valor_unitario'] ?? 0);
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
                                value="<?php echo htmlspecialchars((string)$qtd_item, ENT_QUOTES, 'UTF-8'); ?>">
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
                                value="<?php echo htmlspecialchars(number_format($valor_unit_item, 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('items-container');
    const totalDisplay = document.getElementById('total-itens');
    const orcamentoPrevisto = parseFloat(document.getElementById('orcamento-previsto').dataset.valor) || 0;
    const oficioId = <?php echo (int)$id; ?>;
    const autocompleteTimers = new WeakMap();
    const autocompleteControllers = new WeakMap();

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

    document.getElementById('add-item').addEventListener('click', function() {
        const index = container.querySelectorAll('.item-row').length;

        const row = document.createElement('div');
        row.className = 'item-row';
        row.innerHTML = `
            <div class="form-group" style="margin:0;">
                <label class="form-label">Nº</label>
                <input type="text" class="form-control item-seq" value="${index + 1}" readonly>
            </div>

            <div class="form-group item-name-group" style="margin:0;">
                <label class="form-label">Nome do Item</label>
                <input type="text" name="produtos[${index}][nome]" class="form-control item-name" required autocomplete="off" placeholder="Ex: Papel A4">
                <div class="item-suggestions" role="listbox"></div>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Quantidade</label>
                <input type="number" step="0.01" name="produtos[${index}][qtd]" class="form-control item-qtd" required value="1">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Unidade</label>
                <input type="text" name="produtos[${index}][unidade]" class="form-control" value="UN">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Valor Unitário</label>
                <input type="text" name="produtos[${index}][valor]" class="form-control item-valor" required placeholder="0,00" >
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Total do Item</label>
                <input type="text" class="form-control item-total" value="R$ 0,00" readonly>
            </div>

            <div style="margin-bottom: 5px;">
                <button type="button" class="btn btn-outline btn-sm remove-item" style="color:red; border-color:#ff000033;">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

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
