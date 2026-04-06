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

<style>
    .aq-card {
        border-radius: 14px;
        overflow: hidden;
    }

    .aq-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .aq-header h3 {
        margin: 0;
        color: var(--text-dark);
        font-weight: 700;
        font-size: 1.25rem;
    }

    .aq-info-box {
        background: #f8f9fa;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }

    .aq-info-box p {
        margin: 0;
        color: var(--text-dark);
        line-height: 1.5;
    }

    .aq-form .form-label {
        font-weight: 700;
        margin-bottom: .5rem;
    }

    .aq-section-title {
        font-size: 1rem;
        color: var(--primary);
        margin: 1.8rem 0 1rem;
        font-weight: 700;
    }

    .aq-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid var(--border-color);
        border-radius: 12px;
    }

    .aq-table {
        width: 100%;
        min-width: 760px;
        border-collapse: collapse;
        background: #fff;
    }

    .aq-table th,
    .aq-table td {
        padding: 14px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .aq-table thead th {
        background: #f8f9fa;
        font-size: .92rem;
        font-weight: 700;
        color: var(--text-dark);
        text-align: left;
    }

    .aq-table tfoot td {
        background: #f8f9fa;
        font-weight: 700;
    }

    .aq-table .money {
        font-weight: 700;
        color: var(--primary);
    }

    .aq-table .subtotal {
        font-weight: 700;
        white-space: nowrap;
    }

    .aq-table .input-valor {
        min-width: 140px;
    }

    .aq-total-geral {
        font-weight: 700;
        color: var(--secondary);
        font-size: 1.1rem;
        white-space: nowrap;
    }

    .aq-actions {
        margin-top: 1.5rem;
        display: flex;
        justify-content: flex-end;
    }

    .btn-finalizar-aq {
        padding: 12px 40px;
    }

    @media (max-width: 768px) {
        .aq-header {
            flex-direction: column;
            align-items: stretch;
        }

        .aq-header .btn {
            width: 100%;
            justify-content: center;
            text-align: center;
        }

        .aq-card .card-body {
            padding: 1rem;
        }

        .aq-info-box {
            padding: .9rem 1rem;
        }

        .aq-section-title {
            margin-top: 1.4rem;
        }

        .aq-actions {
            justify-content: stretch;
        }

        .btn-finalizar-aq {
            width: 100%;
            padding: 12px 18px;
            justify-content: center;
        }
    }
</style>

<div class="card aq-card">
    <div class="card-body">

        <div class="aq-header">
            <h3>
                <i class="fas fa-file-invoice-dollar" style="margin-right: 10px; color: var(--primary);"></i>
                Gerar Aquisição - Solicitação <?php echo htmlspecialchars($oficio['numero']); ?>
            </h3>

            <a href="oficios_lista.php" class="btn btn-danger btn-sm">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="aq-info-box">
            <p>
                <strong>Secretaria:</strong>
                <?php echo htmlspecialchars($oficio['secretaria']); ?>
            </p>
        </div>

        <form action="" method="POST" class="aq-form">
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

            <h3 class="aq-section-title">Itens e Valores</h3>

            <div class="aq-table-wrap">
                <table class="aq-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th>Valor Unitário (R$)</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['produto']); ?></td>
                                <td>
                                    <?php echo number_format((float)$item['quantidade'], 2, ',', '.'); ?>
                                    <?php echo htmlspecialchars($item['unidade']); ?>
                                </td>
                                <td style="width: 200px;">
                                    <input
                                        type="number"
                                        step="0.01"
                                        name="valores[]"
                                        class="form-control valor-unitario input-valor"
                                        data-qtd="<?php echo htmlspecialchars($item['quantidade']); ?>"
                                        required
                                        value="0.00"
                                        min="0">
                                </td>
                                <td class="subtotal">R$ 0,00</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right;">TOTAL DA AQUISIÇÃO:</td>
                            <td id="total-geral" class="aq-total-geral">R$ 0,00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="aq-actions">
                <button type="submit" class="btn btn-success btn-finalizar-aq">
                    <i class="fas fa-file-signature"></i> Finalizar e Gerar Aquisição
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.valor-unitario');
        const totalGeral = document.getElementById('total-geral');

        function updateTotals() {
            let grandTotal = 0;

            inputs.forEach(input => {
                const qtd = parseFloat(input.dataset.qtd) || 0;
                const val = parseFloat(input.value) || 0;
                const sub = qtd * val;

                grandTotal += sub;

                const subtotalCell = input.closest('tr').querySelector('.subtotal');
                if (subtotalCell) {
                    subtotalCell.textContent = 'R$ ' + sub.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            });

            totalGeral.textContent = 'R$ ' + grandTotal.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        inputs.forEach(input => {
            input.addEventListener('input', updateTotals);
            input.addEventListener('change', updateTotals);
        });

        updateTotals();
    });
</script>

<?php include 'views/layout/footer.php'; ?>