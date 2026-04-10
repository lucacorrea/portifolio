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
$existing_aq = $stmt_check->fetch();
if ($existing_aq) {
    flash_message('info', "Esta solicitação já possui uma aquisição gerada! Você foi redirecionado para a impressão.");
    header("Location: aquisicoes_visualizar.php?id=" . $existing_aq['id']);
    exit();
}

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fornecedor_id = $_POST['fornecedor_id'] ?? '';
    $valor_total = 0;

    try {
        $pdo->beginTransaction();

        $numero_aq = generate_aquisicao_number($pdo);
        $codigo_entrega = generate_unique_code($pdo);

        $stmt_aq = $pdo->prepare("INSERT INTO aquisicoes (numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total) VALUES (?, ?, ?, ?, ?)");
        $stmt_aq->execute([$numero_aq, $codigo_entrega, $id, $fornecedor_id, 0]);
        $aq_id = $pdo->lastInsertId();

        $stmt_item_aq = $pdo->prepare("INSERT INTO itens_aquisicao (aquisicao_id, produto, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");

        foreach ($items as $item) {
            $valor_u = $item['valor_unitario'] ?? 0;
            $stmt_item_aq->execute([$aq_id, $item['produto'], $item['quantidade'], $valor_u]);
            $valor_total += ($item['quantidade'] * $valor_u);
        }

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

<style>
    .aq-card { border-radius: 14px; overflow: hidden; }
    .aq-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .aq-info-box { background: #f8f9fa; border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
    .aq-table-wrap { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 12px; }
    .aq-table { width: 100%; border-collapse: collapse; background: #fff; }
    .aq-table th, .aq-table td { padding: 14px; border-bottom: 1px solid var(--border-color); }
    .aq-table thead th { background: #f8f9fa; font-weight: 700; text-align: left; }
    .aq-total-geral { font-weight: 700; color: var(--secondary); font-size: 1.1rem; }
</style>

<div class="card aq-card">
    <div class="card-body">
        <div class="aq-header">
            <h3><i class="fas fa-file-invoice-dollar"></i> Gerar Aquisição - Solicitação <?php echo htmlspecialchars($oficio['numero']); ?></h3>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="aq-info-box">
            <p><strong>Secretaria:</strong> <?php echo htmlspecialchars($oficio['secretaria']); ?></p>
        </div>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label">Selecionar Fornecedor</label>
                <select name="fornecedor_id" class="form-control" required>
                    <option value="">Selecione o Fornecedor...</option>
                    <?php foreach ($fornecedores as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>">
                            <?php echo htmlspecialchars($f['nome']); ?> (<?php echo htmlspecialchars($f['cnpj']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <h3 class="aq-section-title">Itens Definidos</h3>
            <div class="aq-table-wrap">
                <table class="aq-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th>Valor Unitário</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grandTotal = 0;
                        foreach ($items as $item): 
                            $sub = $item['quantidade'] * $item['valor_unitario'];
                            $grandTotal += $sub;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['produto']); ?></td>
                                <td><?php echo number_format($item['quantidade'], 2, ',', '.') . ' ' . htmlspecialchars($item['unidade']); ?></td>
                                <td><?php echo format_money($item['valor_unitario']); ?></td>
                                <td style="font-weight:700;"><?php echo format_money($sub); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right; font-weight:700;">TOTAL:</td>
                            <td class="aq-total-geral"><?php echo format_money($grandTotal); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="aq-actions" style="margin-top: 2rem; text-align: right;">
                <a href="oficios_lista.php" class="btn btn-outline" style="margin-right: 10px;">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check"></i> Gerar Aquisição
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>