<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Lista de Aquisições";

$stmt = $pdo->query("
    SELECT a.*, o.numero as oficio_num, s.nome as secretaria, f.nome as fornecedor
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    ORDER BY a.criado_em DESC
");
$aquisicoes = $stmt->fetchAll();

include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem;">
                <i class="fas fa-file-invoice-dollar" style="margin-right: 10px; color: var(--primary);"></i> Aquisições Geradas
            </h3>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive">
            <table class="table-vcenter">
                <thead>
                    <tr>
                        <th>Nº Aquisição</th>
                        <th>Ref. Ofício</th>
                        <th>Secretaria</th>
                        <th>Fornecedor</th>
                        <th style="text-align: right;">Valor Total</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($aquisicoes as $aq): ?>
                    <tr>
                        <td><strong style="color: var(--text-dark);"><?php echo $aq['numero_aq']; ?></strong></td>
                        <td><span class="text-muted"><?php echo $aq['oficio_num']; ?></span></td>
                        <td><span style="font-weight: 600;"><?php echo $aq['secretaria']; ?></span></td>
                        <td><?php echo $aq['fornecedor']; ?></td>
                        <td style="text-align: right; font-weight: 700; color: var(--primary);"><?php echo format_money($aq['valor_total']); ?></td>
                        <td style="text-align: center;">
                            <span class="badge badge-<?php echo strtolower($aq['status'] == 'AGUARDANDO ENTREGA' ? 'pending' : 'finalized'); ?>" style="padding: 0.4rem 1rem;">
                                <?php echo $aq['status']; ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="aquisicoes_visualizar.php?id=<?php echo $aq['id']; ?>" class="btn btn-outline btn-sm" title="Visualizar Detalhes"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($aquisicoes)): ?>
                        <tr><td colspan="7" style="text-align:center; padding: 3rem; color: var(--text-muted);">Nenhuma aquisição gerada até o momento.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
