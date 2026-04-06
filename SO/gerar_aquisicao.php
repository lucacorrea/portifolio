<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT o.*, s.nome as secretaria 
    FROM oficios o 
    JOIN secretarias s ON o.secretaria_id = s.id 
    WHERE o.id = ? AND o.status = 'APROVADO'
");
$stmt->execute([$id]);
$oficio = $stmt->fetch();

if (!$oficio) {
    die("Solicitação não encontrada ou não está aprovada.");
}

// Verificar se já existe aquisição
$stmt_check = $pdo->prepare("SELECT id FROM aquisicoes WHERE oficio_id = ?");
$stmt_check->execute([$id]);
if ($stmt_check->fetch()) {
    flash_message('danger', "Uma aquisição já foi gerada para esta solicitação!");
    header("Location: aquisicoes_lista.php");
    exit();
}

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fornecedor_id = $_POST['fornecedor_id'];
    $valor_total = 0;
    
    try {
        $pdo->beginTransaction();
        
        $numero_aq = generate_aquisicao_number($pdo);
        $codigo_entrega = generate_unique_code($pdo);
        
        $stmt_aq = $pdo->prepare("INSERT INTO aquisicoes (numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total) VALUES (?, ?, ?, ?, ?)");
        $stmt_aq->execute([$numero_aq, $codigo_entrega, $id, $fornecedor_id, 0]);
        $aq_id = $pdo->lastInsertId();
        
        $stmt_item_aq = $pdo->prepare("INSERT INTO itens_aquisicao (aquisicao_id, produto, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");
        
        foreach ($items as $idx => $item) {
            $valor_u = $_POST['valores'][$idx] ?? 0;
            $stmt_item_aq->execute([$aq_id, $item['produto'], $item['quantidade'], $valor_u]);
            $valor_total += ($item['quantidade'] * $valor_u);
        }
        
        // Atualizar valor total
        $pdo->prepare("UPDATE aquisicoes SET valor_total = ? WHERE id = ?")->execute([$valor_total, $aq_id]);
        
        log_action($pdo, "GERAR_AQUISICAO", "Aquisição $numero_aq gerada para Solicitação {$oficio['numero']}");
        $pdo->commit();
        
        flash_message('success', "Aquisição $numero_aq GERADA com SUCESSO!");
        header("Location: aquisicoes_visualizar.php?id=$aq_id");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao gerar aquisição: " . $e->getMessage();
    }
}

$page_title = "Gerar Aquisição: Solicitação " . $oficio['numero'];
include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Gerar Aquisição - Solicitação <?php echo $oficio['numero']; ?></h2>
        <a href="oficios_lista.php" class="btn btn-danger btn-sm">Cancelar</a>
    </div>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="form-group">
            <label class="form-label">Selecionar Fornecedor</label>
            <select name="fornecedor_id" class="form-control" required>
                <option value="">Selecione o Fornecedor...</option>
                <?php foreach($fornecedores as $f): ?>
                    <option value="<?php echo $f['id']; ?>"><?php echo $f['nome']; ?> (<?php echo $f['cnpj']; ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <h3 style="font-size: 1rem; color: var(--primary); margin: 25px 0 15px;">Itens e Valores</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Valor Unitário (R$)</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $idx => $item): ?>
                    <tr>
                        <td><?php echo $item['produto']; ?></td>
                        <td><?php echo number_format($item['quantidade'], 2, ',', '.'); ?> <?php echo $item['unidade']; ?></td>
                        <td width="200">
                            <input type="number" step="0.01" name="valores[]" class="form-control valor-unitario" data-qtd="<?php echo $item['quantidade']; ?>" required value="0.00">
                        </td>
                        <td class="subtotal" style="font-weight: 600;">R$ 0,00</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa;">
                        <td colspan="3" style="text-align: right; font-weight: 700;">TOTAL DA AQUISIÇÃO:</td>
                        <td id="total-geral" style="font-weight: 700; color: var(--secondary); font-size: 1.1rem;">R$ 0,00</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="margin-top: 30px; text-align: right;">
            <button type="submit" class="btn btn-success" style="padding: 12px 40px;">
                <i class="fas fa-file-signature"></i> Finalizar e Gerar Aquisição
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
