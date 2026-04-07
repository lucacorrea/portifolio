<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$id = $_GET['id'] ?? 0;

// Buscar aquisição e itens vinculados
$stmt = $pdo->prepare("
    SELECT a.*, o.numero as oficio_num, s.nome as secretaria, f.nome as fornecedor
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE a.id = ? AND a.status = 'AGUARDANDO ENTREGA'
");
$stmt->execute([$id]);
$aq = $stmt->fetch();

if (!$aq) {
    die("Aquisição não encontrada ou já finalizada.");
}

$stmt_items = $pdo->prepare("SELECT * FROM itens_aquisicao WHERE aquisicao_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor_total_novo = 0;
    
    try {
        $pdo->beginTransaction();
        
        $stmt_update = $pdo->prepare("UPDATE itens_aquisicao SET valor_unitario = ? WHERE id = ?");
        
        foreach ($items as $idx => $item) {
            $valor_u = $_POST['valores'][$idx] ?? 0;
            $stmt_update->execute([$valor_u, $item['id']]);
            $valor_total_novo += ($item['quantidade'] * $valor_u);
        }
        
        // Atualizar valor total da aquisição
        $pdo->prepare("UPDATE aquisicoes SET valor_total = ? WHERE id = ?")->execute([$valor_total_novo, $id]);
        
        log_action($pdo, "EDITAR_AQUISICAO", "Valores da Aquisição {$aq['numero_aq']} atualizados para R$ " . number_format($valor_total_novo, 2, ',', '.'));
        $pdo->commit();
        
        flash_message('success', "Valores da Aquisição {$aq['numero_aq']} atualizados com SUCESSO!");
        header("Location: aquisicoes_visualizar.php?id=$id");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao atualizar valores: " . $e->getMessage();
    }
}

$page_title = "Lançar Valores: Aquisição " . $aq['numero_aq'];
include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Lançar/Editar Valores - Aquisição <?php echo $aq['numero_aq']; ?></h2>
        <a href="aquisicoes_lista.php" class="btn btn-outline btn-sm">Voltar</a>
    </div>

    <div style="background: var(--bg-body); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border-left: 5px solid var(--primary);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div>
                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Fornecedor Selecionado</label>
                <div style="font-weight: 700; color: var(--text-dark);"><?php echo $aq['fornecedor']; ?></div>
            </div>
            <div>
                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Secretaria Requisitante</label>
                <div style="font-weight: 700; color: var(--text-dark);"><?php echo $aq['secretaria']; ?></div>
            </div>
            <div>
                <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Ref. Solicitação</label>
                <div style="font-weight: 700; color: var(--text-dark);"><?php echo $aq['oficio_num']; ?></div>
            </div>
        </div>
    </div>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <h3 style="font-size: 0.875rem; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 1.25rem;">Itens da Ordem</h3>
        <div class="table-responsive">
            <table class="table-vcenter">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th width="200">Valor Unitário (R$)</th>
                        <th style="text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $idx => $item): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-dark);"><?php echo $item['produto']; ?></td>
                        <td><strong><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></strong> UN</td>
                        <td>
                            <input type="number" step="0.01" name="valores[]" class="form-control valor-unitario" 
                                   data-qtd="<?php echo $item['quantidade']; ?>" required 
                                   value="<?php echo $item['valor_unitario']; ?>" 
                                   style="font-weight: 700; color: var(--primary);">
                        </td>
                        <td class="subtotal" style="text-align: right; font-weight: 700; color: var(--text-dark);">
                            R$ <?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f1f5f9;">
                        <td colspan="3" style="text-align: right; font-weight: 800; text-transform: uppercase; font-size: 0.875rem;">Total Geral da Aquisição:</td>
                        <td id="total-geral" style="text-align: right; font-weight: 900; color: var(--secondary); font-size: 1.25rem;">
                            R$ <?php echo number_format($aq['valor_total'], 2, ',', '.'); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; text-align: right;">
            <button type="submit" class="btn btn-success" style="padding: 0.75rem 2.5rem;">
                <i class="fas fa-save"></i> Salvar e Atualizar Valores
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.valor-unitario');
    const totalGeral = document.getElementById('total-geral');

    function updateTotals() {
        let grandTotal = 0;
        inputs.forEach(input => {
            const qtd = parseFloat(input.dataset.qtd);
            const val = parseFloat(input.value) || 0;
            const sub = qtd * val;
            grandTotal += sub;
            input.closest('tr').querySelector('.subtotal').textContent = 'R$ ' + sub.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        });
        totalGeral.textContent = 'R$ ' + grandTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    }

    inputs.forEach(input => {
        input.addEventListener('input', updateTotals);
    });
});
</script>

<?php include 'views/layout/footer.php'; ?>
