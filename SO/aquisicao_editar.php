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
            $valor_u = str_replace(',', '.', (string)$valor_u);
            $valor_u = (float)$valor_u;

            $stmt_update->execute([$valor_u, $item['id']]);
            $valor_total_novo += ((float)$item['quantidade'] * $valor_u);
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

<style>
    .aq-page {
        width: 100%;
    }

    .aq-card {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    }

    .aq-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.25rem 1.5rem 0.25rem;
        flex-wrap: wrap;
    }

    .aq-card-title {
        margin: 0;
        font-size: 1.9rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }

    .aq-btn-voltar {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        border-radius: 10px;
        padding: 0.55rem 1rem;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s ease;
    }

    .aq-btn-voltar:hover {
        background: #f8fafc;
        color: #0f172a;
        text-decoration: none;
    }

    .aq-info-box {
        background: #f8fafc;
        padding: 1.35rem 1.5rem;
        border-radius: 0;
        margin: 0 0 1.75rem;
        border-left: 5px solid #2563eb;
    }

    .aq-info-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .aq-info-item {
        min-width: 0;
    }

    .aq-info-label {
        display: block;
        font-size: 0.74rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 0.4rem;
        letter-spacing: 0.02em;
    }

    .aq-info-value {
        font-weight: 800;
        color: #0f172a;
        line-height: 1.4;
        word-break: break-word;
    }

    .aq-body {
        padding: 0 0 1.5rem;
    }

    .aq-section-title {
        font-size: 0.92rem;
        font-weight: 800;
        color: #2563eb;
        text-transform: uppercase;
        margin: 0 1.5rem 1rem;
    }

    .aq-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .aq-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
    }

    .aq-table thead th {
        background: #f8fafc;
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 800;
        text-transform: uppercase;
        padding: 1rem 0.9rem;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        white-space: nowrap;
    }

    .aq-table tbody td {
        padding: 1rem 0.9rem;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
        color: #0f172a;
    }

    .aq-table tfoot td {
        padding: 1rem 0.9rem;
        background: #f1f5f9;
        border-top: 1px solid #dbeafe;
        font-weight: 800;
    }

    .aq-produto {
        font-weight: 700;
        color: #0f172a;
    }

    .aq-qtd {
        white-space: nowrap;
        font-weight: 700;
    }

    .aq-campo-valor {
        width: 100%;
        min-width: 140px;
        height: 44px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        padding: 0.7rem 0.85rem;
        font-size: 0.98rem;
        font-weight: 800;
        color: #2563eb;
        background: #fff;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .aq-campo-valor:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .aq-subtotal {
        text-align: right;
        font-weight: 800;
        color: #0f172a;
        white-space: nowrap;
    }

    .aq-total-label {
        text-align: right;
        font-weight: 900;
        text-transform: uppercase;
        font-size: 0.92rem;
        color: #0f172a;
    }

    .aq-total-geral {
        text-align: right;
        font-weight: 900;
        color: #0f172a;
        font-size: 1.95rem;
        white-space: nowrap;
    }

    .aq-actions {
        margin-top: 1.75rem;
        padding: 1.25rem 1.5rem 0;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
    }

    .aq-btn-salvar {
        border: 0;
        border-radius: 12px;
        background: #16a34a;
        color: #fff;
        font-weight: 800;
        font-size: 1rem;
        padding: 0.9rem 1.6rem;
        min-width: 260px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        box-shadow: 0 10px 20px rgba(22, 163, 74, 0.18);
        transition: 0.2s ease;
        cursor: pointer;
    }

    .aq-btn-salvar:hover {
        background: #15803d;
    }

    .aq-btn-salvar:active {
        transform: translateY(1px);
    }

    .aq-alert {
        margin: 0 1.5rem 1.25rem;
        border-radius: 10px;
    }

    @media (max-width: 992px) {
        .aq-card-title {
            font-size: 1.55rem;
        }

        .aq-info-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .aq-card-header {
            padding: 1rem 1rem 0.25rem;
            flex-direction: column;
            align-items: stretch;
        }

        .aq-card-title {
            font-size: 1.35rem;
        }

        .aq-btn-voltar {
            width: 100%;
        }

        .aq-info-box {
            padding: 1rem;
        }

        .aq-section-title {
            margin-left: 1rem;
            margin-right: 1rem;
        }

        .aq-actions {
            padding: 1rem 1rem 0;
        }

        .aq-btn-salvar {
            width: 100%;
            min-width: 0;
        }

        .aq-total-geral {
            font-size: 1.45rem;
        }
    }

    @media (max-width: 576px) {
        .aq-table {
            min-width: 680px;
        }

        .aq-table thead th,
        .aq-table tbody td,
        .aq-table tfoot td {
            padding: 0.85rem 0.7rem;
        }

        .aq-campo-valor {
            min-width: 125px;
            height: 42px;
            font-size: 0.95rem;
        }

        .aq-total-label {
            font-size: 0.82rem;
        }

        .aq-total-geral {
            font-size: 1.25rem;
        }
    }
</style>

<div class="aq-page">
    <div class="aq-card">
        <div class="aq-card-header">
            <h2 class="aq-card-title">Lançar/Editar Valores - Aquisição <?php echo htmlspecialchars($aq['numero_aq']); ?></h2>
            <a href="aquisicoes_lista.php" class="aq-btn-voltar">Voltar</a>
        </div>

        <div class="aq-info-box">
            <div class="aq-info-grid">
                <div class="aq-info-item">
                    <span class="aq-info-label">Fornecedor Selecionado</span>
                    <div class="aq-info-value"><?php echo htmlspecialchars($aq['fornecedor']); ?></div>
                </div>
                <div class="aq-info-item">
                    <span class="aq-info-label">Secretaria Requisitante</span>
                    <div class="aq-info-value"><?php echo htmlspecialchars($aq['secretaria']); ?></div>
                </div>
                <div class="aq-info-item">
                    <span class="aq-info-label">Ref. Solicitação</span>
                    <div class="aq-info-value"><?php echo htmlspecialchars($aq['oficio_num']); ?></div>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger aq-alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="aq-body">
            <form action="" method="POST">
                <h3 class="aq-section-title">Itens da Ordem</h3>

                <div class="aq-table-wrap">
                    <table class="aq-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th style="width: 220px;">Valor Unitário (R$)</th>
                                <th style="text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $idx => $item): ?>
                                <tr>
                                    <td class="aq-produto"><?php echo htmlspecialchars($item['produto']); ?></td>
                                    <td class="aq-qtd">
                                        <?php echo number_format((float)$item['quantidade'], 2, ',', '.'); ?> UN
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="valores[]"
                                            class="aq-campo-valor valor-unitario"
                                            data-qtd="<?php echo (float)$item['quantidade']; ?>"
                                            required
                                            value="<?php echo htmlspecialchars((string)$item['valor_unitario']); ?>">
                                    </td>
                                    <td class="aq-subtotal subtotal">
                                        R$ <?php echo number_format((float)$item['quantidade'] * (float)$item['valor_unitario'], 2, ',', '.'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="aq-total-label">Total Geral da Aquisição:</td>
                                <td id="total-geral" class="aq-total-geral">
                                    R$ <?php echo number_format((float)$aq['valor_total'], 2, ',', '.'); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="aq-actions">
                    <button type="submit" class="aq-btn-salvar">
                        <i class="fas fa-save"></i>
                        Salvar e Atualizar Valores
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.valor-unitario');
    const totalGeral = document.getElementById('total-geral');

    function formatBRL(valor) {
        return valor.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateTotals() {
        let grandTotal = 0;

        inputs.forEach(input => {
            const qtd = parseFloat(input.dataset.qtd) || 0;
            const val = parseFloat(input.value) || 0;
            const sub = qtd * val;

            grandTotal += sub;

            const subtotalEl = input.closest('tr').querySelector('.subtotal');
            if (subtotalEl) {
                subtotalEl.textContent = 'R$ ' + formatBRL(sub);
            }
        });

        if (totalGeral) {
            totalGeral.textContent = 'R$ ' + formatBRL(grandTotal);
        }
    }

    inputs.forEach(input => {
        input.addEventListener('input', updateTotals);
        input.addEventListener('change', updateTotals);
    });

    updateTotals();
});
</script>

<?php include 'views/layout/footer.php'; ?>