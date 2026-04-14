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
$oficio = $stmt->fetch();

if (!$oficio) {
    die("Solicitação não encontrada.");
}

// Buscar itens existentes se houver
$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ?");
$stmt_items->execute([$id]);
$items_existentes = $stmt_items->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produtos = $_POST['produtos'] ?? [];
    
    try {
        $pdo->beginTransaction();

        $orcamento_esperado = (float)($oficio['valor_orcamento'] ?? 0);
        $total_calculado = 0;

        foreach ($produtos as $p) {
            if (!empty($p['nome'])) {
                $val = (float)str_replace(',', '.', $p['valor']);
                $qtd = (float)$p['qtd'];
                $total_calculado += ($val * $qtd);
            }
        }

        if ($orcamento_esperado > 0 && abs($total_calculado - $orcamento_esperado) > 0.02) {
            throw new Exception("O valor total dos itens deve ser exatamente igual ao orçamento previsto de R$ " . number_format($orcamento_esperado, 2, ',', '.'));
        }

        // Limpar itens antigos se estiver reatribuindo
        $pdo->prepare("DELETE FROM itens_oficio WHERE oficio_id = ?")->execute([$id]);

        $stmt_ins = $pdo->prepare("INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($produtos as $p) {
            if (!empty($p['nome'])) {
                $val = str_replace(',', '.', $p['valor']);
                $stmt_ins->execute([$id, $p['nome'], $p['qtd'], $p['unidade'] ?: 'UN', $val]);
            }
        }

        // Atualizar status para ENVIADO
        $pdo->prepare("UPDATE oficios SET status = 'ENVIADO' WHERE id = ?")->execute([$id]);

        log_action($pdo, "ATRIBUIR_ITENS", "Itens atribuídos ao ofício {$oficio['numero']}");
        $pdo->commit();

        flash_message('success', "Itens atribuídos com sucesso à solicitação {$oficio['numero']}!");
        header("Location: oficios_lista_sefaz.php");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao salvar itens: " . $e->getMessage();
    }
}

$page_title = "Atribuir Itens - " . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
    .item-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr auto;
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
    .diff-warning { color: #dc3545; }
    .diff-ok { color: #198754; }

    @media (max-width: 992px) {
        .item-row { grid-template-columns: 1fr; }
    }
</style>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3><i class="fas fa-box-open"></i> Atribuição de Itens - <?php echo $oficio['numero']; ?></h3>
            <a href="oficios_lista_sefaz.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="budget-info">
            <div>
                <span class="text-muted">Secretaria:</span> <strong><?php echo htmlspecialchars($oficio['secretaria']); ?></strong><br>
                <span class="text-muted">Orçamento Previsto:</span> 
                <strong id="orcamento-previsto" data-valor="<?php echo $oficio['valor_orcamento'] ?? 0; ?>">
                    <?php echo $oficio['valor_orcamento'] ? format_money($oficio['valor_orcamento']) : 'Não informado'; ?>
                </strong>
            </div>
            <div style="text-align: right;">
                <span class="text-muted">Total Atual dos Itens:</span><br>
                <span id="total-itens" class="total-calc">R$ 0,00</span>
            </div>
        </div>

        <form action="" method="POST" id="items-form">
            <div id="items-container">
                <?php 
                $items = !empty($items_existentes) ? $items_existentes : [['produto' => '', 'quantidade' => 1, 'unidade' => 'UN', 'valor_unitario' => 0]];
                foreach ($items as $idx => $it): 
                ?>
                <div class="item-row">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Nome do Item</label>
                        <input type="text" name="produtos[<?php echo $idx; ?>][nome]" class="form-control" required placeholder="Ex: Papel A4" value="<?php echo htmlspecialchars($it['produto']); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Quantidade</label>
                        <input type="number" step="0.01" name="produtos[<?php echo $idx; ?>][qtd]" class="form-control item-qtd" required value="<?php echo (float)$it['quantidade']; ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Unidade</label>
                        <input type="text" name="produtos[<?php echo $idx; ?>][unidade]" class="form-control" value="<?php echo htmlspecialchars($it['unidade']); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Valor Unitário</label>
                        <input type="text" name="produtos[<?php echo $idx; ?>][valor]" class="form-control item-valor" required placeholder="0,00" value="<?php echo number_format($it['valor_unitario'], 2, ',', ''); ?>">
                    </div>
                    <div style="margin-bottom: 5px;">
                        <button type="button" class="btn btn-outline btn-sm remove-item" style="color:red; border-color:#ff000033;"><i class="fas fa-trash"></i></button>
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

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qtd = parseFloat(row.querySelector('.item-qtd').value) || 0;
            const valStr = row.querySelector('.item-valor').value.replace(',', '.');
            const val = parseFloat(valStr) || 0;
            total += (qtd * val);
        });
        
        totalDisplay.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        
        if (orcamentoPrevisto > 0) {
            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                totalDisplay.classList.add('diff-warning');
                totalDisplay.classList.remove('diff-ok');
            } else {
                totalDisplay.classList.add('diff-ok');
                totalDisplay.classList.remove('diff-warning');
            }
        }
    }

    container.addEventListener('input', calculateTotal);

    document.getElementById('add-item').addEventListener('click', function() {
        const index = document.querySelectorAll('.item-row').length;
        const row = document.createElement('div');
        row.className = 'item-row';
        row.innerHTML = `
            <div class="form-group" style="margin:0;">
                <label class="form-label">Nome do Item</label>
                <input type="text" name="produtos[${index}][nome]" class="form-control" required>
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
                <input type="text" name="produtos[${index}][valor]" class="form-control item-valor" required placeholder="0,00">
            </div>
            <div style="margin-bottom: 5px;">
                <button type="button" class="btn btn-outline btn-sm remove-item" style="color:red; border-color:#ff000033;"><i class="fas fa-trash"></i></button>
            </div>
        `;
        container.appendChild(row);
        calculateTotal();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            if (document.querySelectorAll('.item-row').length > 1) {
                e.target.closest('.item-row').remove();
                calculateTotal();
            }
        }
    });

    // Validar formato de moeda nas inputs
    container.addEventListener('keyup', function(e) {
        if (e.target.classList.contains('item-valor')) {
            e.target.value = e.target.value.replace(/[^\d,]/g, '');
        }
    });

    calculateTotal();

    document.getElementById('items-form').addEventListener('submit', function(e) {
        if (orcamentoPrevisto > 0) {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const qtd = parseFloat(row.querySelector('.item-qtd').value) || 0;
                const valStr = row.querySelector('.item-valor').value.replace(',', '.');
                const val = parseFloat(valStr) || 0;
                total += (qtd * val);
            });
            
            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                e.preventDefault();
                alert("Bloqueado: O valor total atual dos itens não corresponde ao Valor do Orçamento Previsto!\\nPor favor, faça a correção das quantidades ou valores.");
            }
        }
    });

});
</script>

<?php include 'views/layout/footer.php'; ?>
