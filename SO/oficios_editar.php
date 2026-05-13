<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$id = (int)($_GET['id'] ?? 0);

function parse_oficio_money($valor) {
    $valor = trim((string)$valor);

    if ($valor === '') {
        return null;
    }

    $valor = str_replace([' ', '.'], ['', ''], $valor);
    $valor = str_replace(',', '.', $valor);

    if (!is_numeric($valor)) {
        throw new Exception("Informe um valor monetário válido.");
    }

    $valor = (float)$valor;

    if ($valor < 0) {
        throw new Exception("Valores monetários não podem ser negativos.");
    }

    return $valor;
}

function format_money_input($valor) {
    if ($valor === null || $valor === '') {
        return '';
    }

    return number_format((float)$valor, 2, ',', '.');
}

function format_quantity_input($valor) {
    return rtrim(rtrim(number_format((float)$valor, 2, '.', ''), '0'), '.');
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

$stmt_aquisicao = $pdo->prepare("SELECT id, numero_aq FROM aquisicoes WHERE oficio_id = ? LIMIT 1");
$stmt_aquisicao->execute([$id]);
$aquisicao_vinculada = $stmt_aquisicao->fetch(PDO::FETCH_ASSOC);
$edicao_bloqueada = !empty($aquisicao_vinculada);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($edicao_bloqueada) {
            throw new Exception("Esta solicitação já possui aquisição gerada e não pode ter itens alterados por aqui.");
        }

        $numero_manual = mb_strtoupper(trim($_POST['numero_oficio'] ?? ''), 'UTF-8');
        $valor_orcamento = parse_oficio_money($_POST['valor_orcamento'] ?? '');
        $produtos = $_POST['produtos'] ?? [];

        if ($numero_manual === '') {
            throw new Exception("O número do ofício é obrigatório.");
        }

        $stmt_check = $pdo->prepare("SELECT id FROM oficios WHERE numero = ? AND id <> ?");
        $stmt_check->execute([$numero_manual, $id]);
        if ($stmt_check->fetch()) {
            throw new Exception("O número de ofício '{$numero_manual}' já está cadastrado em outra solicitação.");
        }

        $itens_sanitizados = [];
        $total_calculado = 0;

        foreach ($produtos as $idx => $p) {
            $nome = trim((string)($p['nome'] ?? ''));

            if ($nome === '') {
                continue;
            }

            $qtd = (float)str_replace(',', '.', (string)($p['qtd'] ?? 0));
            $unidade = trim((string)($p['unidade'] ?? 'UN'));
            $valor_unitario = parse_oficio_money($p['valor'] ?? '0');
            $valor_unitario = $valor_unitario ?? 0;

            if ($qtd <= 0) {
                throw new Exception("A quantidade do item " . ($idx + 1) . " deve ser maior que zero.");
            }

            if ($unidade === '') {
                $unidade = 'UN';
            }

            $total_calculado += ($qtd * $valor_unitario);

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

        if ($valor_orcamento !== null && $valor_orcamento > 0 && abs($total_calculado - $valor_orcamento) > 0.02) {
            throw new Exception("O valor total dos itens deve ser exatamente igual ao orçamento previsto de R$ " . number_format($valor_orcamento, 2, ',', '.'));
        }

        $pdo->beginTransaction();

        $novo_status = $oficio['status'] === 'PENDENTE_ITENS' ? 'ENVIADO' : $oficio['status'];

        $stmt_update = $pdo->prepare("
            UPDATE oficios
            SET numero = ?, valor_orcamento = ?, status = ?
            WHERE id = ?
        ");
        $stmt_update->execute([$numero_manual, $valor_orcamento, $novo_status, $id]);

        $pdo->prepare("DELETE FROM itens_oficio WHERE oficio_id = ?")->execute([$id]);

        $stmt_item = $pdo->prepare("
            INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($itens_sanitizados as $item) {
            $stmt_item->execute([
                $id,
                $item['produto'],
                $item['quantidade'],
                $item['unidade'],
                $item['valor_unitario'],
            ]);
        }

        log_action($pdo, "EDITAR_OFICIO", "Solicitação {$oficio['numero']} editada para {$numero_manual}");
        $pdo->commit();

        flash_message('success', "Solicitação {$numero_manual} atualizada com sucesso.");
        header("Location: oficios_visualizar.php?id={$id}");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = "Erro ao editar: " . $e->getMessage();
    }
}

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC");
$stmt_items->execute([$id]);
$items_existentes = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

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
    $items_form = [];
    foreach (($_POST['produtos'] ?? []) as $p) {
        $items_form[] = [
            'produto' => $p['nome'] ?? '',
            'quantidade_input' => $p['qtd'] ?? '1',
            'unidade' => $p['unidade'] ?? 'UN',
            'valor_input' => $p['valor'] ?? '',
        ];
    }
} else {
    $items_form = !empty($items_existentes)
        ? $items_existentes
        : [['produto' => '', 'quantidade' => 1, 'unidade' => 'UN', 'valor_unitario' => 0]];
}

$numero_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['numero_oficio'] ?? '')
    : ($oficio['numero'] ?? '');

$orcamento_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['valor_orcamento'] ?? '')
    : format_money_input($oficio['valor_orcamento'] ?? null);

$page_title = "Editar Solicitação - " . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
    .edit-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .oficio-edit-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

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
        gap: 1rem;
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

    .edit-actions {
        text-align: right;
        border-top: 1px solid var(--border-color);
        padding-top: 2rem;
    }

    @media (max-width: 1200px) {
        .item-row {
            grid-template-columns: 70px 1.8fr 1fr 1fr 1fr 1fr auto;
        }
    }

    @media (max-width: 992px) {
        .oficio-edit-grid,
        .item-row {
            grid-template-columns: 1fr;
        }

        .budget-info {
            align-items: flex-start;
            flex-direction: column;
        }

        .edit-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="card">
    <div class="card-body">
        <div class="edit-header">
            <h3 style="margin: 0;">
                <i class="fas fa-edit"></i> Editar Solicitação - <?php echo htmlspecialchars($oficio['numero'], ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            <a href="oficios_lista.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($edicao_bloqueada): ?>
            <div class="alert alert-warning">
                Esta solicitação já possui a aquisição
                <strong><?php echo htmlspecialchars($aquisicao_vinculada['numero_aq'], ENT_QUOTES, 'UTF-8'); ?></strong>
                gerada. Para evitar divergência de dados, a edição dos itens está bloqueada.
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="items-form">
            <div class="oficio-edit-grid">
                <div class="form-group">
                    <label class="form-label">Número do Ofício <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="numero_oficio"
                        class="form-control"
                        value="<?php echo htmlspecialchars($numero_value, ENT_QUOTES, 'UTF-8'); ?>"
                        oninput="this.value = this.value.toUpperCase()"
                        <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Valor do Orçamento</label>
                    <input
                        type="text"
                        name="valor_orcamento"
                        id="valor-orcamento"
                        class="form-control"
                        placeholder="0,00"
                        value="<?php echo htmlspecialchars($orcamento_value, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="budget-info">
                <div>
                    <span class="text-muted">Secretaria:</span>
                    <strong><?php echo htmlspecialchars($oficio['secretaria'], ENT_QUOTES, 'UTF-8'); ?></strong><br>

                    <span class="text-muted">Status atual:</span>
                    <strong><?php echo htmlspecialchars($oficio['status'], ENT_QUOTES, 'UTF-8'); ?></strong>
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
                                                <?php echo htmlspecialchars($rp['produto'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td style="padding: 12px; text-align:center;">
                                                <?php echo htmlspecialchars($rp['unidade'], ENT_QUOTES, 'UTF-8'); ?>
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

            <div id="items-container">
                <?php foreach ($items_form as $idx => $it): ?>
                    <?php
                    $qtd_input = $it['quantidade_input'] ?? format_quantity_input($it['quantidade'] ?? 1);
                    $valor_input = $it['valor_input'] ?? format_money_input($it['valor_unitario'] ?? 0);
                    $qtd_item = (float)str_replace(',', '.', (string)$qtd_input);
                    try {
                        $valor_unit_item = parse_oficio_money($valor_input) ?? 0;
                    } catch (Exception $e) {
                        $valor_unit_item = 0;
                    }
                    $valor_total_item = $qtd_item * $valor_unit_item;
                    ?>
                    <div class="item-row">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nº</label>
                            <input type="text" class="form-control item-seq" value="<?php echo $idx + 1; ?>" readonly>
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nome do Item</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][nome]"
                                class="form-control"
                                required
                                placeholder="Ex: Papel A4"
                                value="<?php echo htmlspecialchars($it['produto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>>
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Quantidade</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="produtos[<?php echo $idx; ?>][qtd]"
                                class="form-control item-qtd"
                                required
                                value="<?php echo htmlspecialchars((string)$qtd_input, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>>
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Unidade</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][unidade]"
                                class="form-control"
                                value="<?php echo htmlspecialchars($it['unidade'] ?? 'UN', ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>>
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Valor Unitário</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][valor]"
                                class="form-control item-valor"
                                required
                                placeholder="0,00"
                                value="<?php echo htmlspecialchars($valor_input, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>>
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
                            <button
                                type="button"
                                class="btn btn-outline btn-sm remove-item"
                                style="color:red; border-color:#ff000033;"
                                <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>>
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button
                type="button"
                class="btn btn-outline"
                id="add-item"
                style="margin-bottom: 2rem;"
                <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>>
                <i class="fas fa-plus"></i> Adicionar Mais Itens
            </button>

            <div class="edit-actions">
                <button
                    type="submit"
                    class="btn btn-primary btn-lg"
                    <?php echo $edicao_bloqueada ? 'disabled' : ''; ?>>
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('items-container');
    const totalDisplay = document.getElementById('total-itens');
    const budgetInput = document.getElementById('valor-orcamento');
    const addButton = document.getElementById('add-item');
    const form = document.getElementById('items-form');

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
        const orcamentoPrevisto = parseValorBR(budgetInput?.value || '');

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
        } else {
            totalDisplay.classList.remove('diff-warning', 'diff-ok');
        }

        updateItemTotals();
    }

    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-valor')) {
            e.target.value = e.target.value.replace(/[^\d,.\s]/g, '');
        }
        calculateTotal();
    });

    if (budgetInput) {
        budgetInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^\d,.\s]/g, '');
            calculateTotal();
        });
    }

    if (addButton) {
        addButton.addEventListener('click', function() {
            const index = container.querySelectorAll('.item-row').length;
            const row = document.createElement('div');
            row.className = 'item-row';
            row.innerHTML = `
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Nº</label>
                    <input type="text" class="form-control item-seq" value="${index + 1}" readonly>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Nome do Item</label>
                    <input type="text" name="produtos[${index}][nome]" class="form-control" required placeholder="Ex: Papel A4">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Quantidade</label>
                    <input type="number" step="0.01" min="0.01" name="produtos[${index}][qtd]" class="form-control item-qtd" required value="1">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Unidade</label>
                    <input type="text" name="produtos[${index}][unidade]" class="form-control" value="UN">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Valor Unitário</label>
                    <input type="text" name="produtos[${index}][valor]" class="form-control item-valor" required placeholder="0,00">
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
    }

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

    form.addEventListener('submit', function(e) {
        renumberItems();

        const orcamentoPrevisto = parseValorBR(budgetInput?.value || '');
        if (orcamentoPrevisto > 0) {
            let total = 0;

            container.querySelectorAll('.item-row').forEach(row => {
                const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
                const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
                total += (qtd * valorUnit);
            });

            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                e.preventDefault();
                alert("Bloqueado: O valor total atual dos itens não corresponde ao Valor do Orçamento Previsto.");
                return false;
            }
        }
    });

    renumberItems();
    calculateTotal();
});
</script>

<?php include 'views/layout/footer.php'; ?>
