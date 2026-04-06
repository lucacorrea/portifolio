<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// Acesso via secretaria_id (setado no login)
if (!isset($_SESSION['secretaria_id'])) {
    header("Location: login.php");
    exit();
}

$sec_id = $_SESSION['secretaria_id'];
$page_title = "Acompanhamento de Solicitações - " . $_SESSION['secretaria_nome'];

$stmt = $pdo->prepare("
    SELECT o.*, 
           (SELECT status FROM aquisicoes WHERE oficio_id = o.id) as aq_status,
           (SELECT numero_aq FROM aquisicoes WHERE oficio_id = o.id) as aq_numero
    FROM oficios o 
    WHERE o.secretaria_id = ? 
    ORDER BY o.criado_em DESC
");
$stmt->execute([$sec_id]);
$meus_pedidos = $stmt->fetchAll();

include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem;">
                <i class="fas fa-list-check" style="margin-right: 10px; color: var(--primary);"></i> Minhas Solicitações (Acompanhamento)
            </h3>
            <a href="confirmar_entrega.php" class="btn btn-primary btn-sm"><i class="fas fa-check-double"></i> Confirmar Recebimento</a>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive">
            <table class="table-vcenter">
                <thead>
                    <tr>
                        <th>Nº Ofício</th>
                        <th>Data Solicitação</th>
                        <th>Status Ofício</th>
                        <th>Status Aquisição</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($meus_pedidos as $p): ?>
                    <tr>
                        <td><strong style="color: var(--text-dark); font-size: 1rem;"><?php echo $p['numero']; ?></strong></td>
                        <td><span class="text-muted"><?php echo format_date($p['criado_em']); ?></span></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($p['status'] == 'ENVIADO' ? 'pending' : ($p['status'] == 'APROVADO' ? 'approved' : 'rejected')); ?>" style="padding: 0.4rem 1rem;">
                                <?php echo $p['status']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if($p['aq_numero']): ?>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span class="badge badge-<?php echo $p['aq_status'] == 'FINALIZADO' ? 'finalized' : 'pending'; ?>" style="font-size: 0.7rem; padding: 0.3rem 0.8rem;">
                                        <?php echo $p['aq_status']; ?>
                                    </span>
                                    <small class="text-muted" style="font-weight: 600; font-size: 0.75rem;">Ref: <?php echo $p['aq_numero']; ?></small>
                                </div>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 0.875rem; font-style: italic;">Aguardando...</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <a href="oficios_visualizar.php?id=<?php echo $p['id']; ?>" class="btn btn-outline btn-sm" title="Visualizar Detalhes"><i class="fas fa-eye"></i> Visualizar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($meus_pedidos)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 3rem; color: var(--text-muted);">Nenhuma solicitação encontrada para sua secretaria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
