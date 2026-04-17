<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
sefaz_check();

$id = (int)($_GET['id'] ?? 0);

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

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nome do Item</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][nome]"
                                class="form-control"
                                required
                                placeholder="Ex: Papel A4"
                                value="<?php echo htmlspecialchars($it['produto']); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Quantidade</label>
                            <input
                                type="number"
                                step="0.01"
                                name="produtos[<?php echo $idx; ?>][qtd]"
                                class="form-control item-qtd"
                                required
                                value="<?php echo number_format($qtd_item, 2, '.', ''); ?>">
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
                                value="<?php echo number_format($valor_unit_item, 2, ',', '.'); ?>">
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
        calculateTotal();
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

            <div class="form-group" style="margin:0;">
                <label class="form-label">Nome do Item</label>
                <input type="text" name="produtos[${index}][nome]" class="form-control" required placeholder="Ex: Papel A4">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Quantidade</label>
                <input type="number" step="0.01" name="produtos[${index}][qtd]" class="form-control item-qtd" required value="1.00">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Unidade</label>
                <input type="text" name="produtos[${index}][unidade]" class="form-control" value="UN">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Valor Unitário</label>
                <input type="text" name="produtos[${index}][valor]" class="form-control item-valor" required placeholder="0,00" value="0,00">
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