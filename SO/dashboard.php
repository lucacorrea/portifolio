<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Dashboard Geral";
include 'views/layout/header.php';

// KPIs
$total_oficios = $pdo->query("SELECT COUNT(*) FROM oficios")->fetchColumn();
$total_aguardando = $pdo->query("SELECT COUNT(*) FROM oficios WHERE status = 'ENVIADO'")->fetchColumn();
$total_aprovados = $pdo->query("SELECT COUNT(*) FROM oficios WHERE status = 'APROVADO'")->fetchColumn();
$total_finalizados = $pdo->query("SELECT COUNT(*) FROM aquisicoes WHERE status = 'FINALIZADO'")->fetchColumn();

// Últimas Solicitações
$stmt = $pdo->query("
    SELECT o.*, s.nome as secretaria 
    FROM oficios o 
    JOIN secretarias s ON o.secretaria_id = s.id 
    ORDER BY o.criado_em DESC LIMIT 5
");
$ultimos_oficios = $stmt->fetchAll();
?>

<style>
    .dashboard-nowrap,
    .dashboard-nowrap th,
    .dashboard-nowrap td {
        white-space: nowrap !important;
    }
</style>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-body">
            <div class="card-action">
                <i class="fas fa-file-alt" style="color: var(--primary);"></i>
            </div>
            <div class="card-label">Total de Solicitações</div>
            <div class="card-number"><?php echo $total_oficios; ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-action">
                <i class="fas fa-clock" style="color: var(--status-pending);"></i>
            </div>
            <div class="card-label">Aguardando Análise</div>
            <div class="card-number"><?php echo $total_aguardando; ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-action">
                <i class="fas fa-check-circle" style="color: var(--status-approved);"></i>
            </div>
            <div class="card-label">Solicitações Aprovadas</div>
            <div class="card-number"><?php echo $total_aprovados; ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-action">
                <i class="fas fa-box-open" style="color: var(--status-finalized);"></i>
            </div>
            <div class="card-label">Entregas Finalizadas</div>
            <div class="card-number"><?php echo $total_finalizados; ?></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-dark); font-weight: 700; font-size: 1rem;">
                    <i class="fas fa-chart-pie" style="margin-right: 10px; color: var(--primary);"></i> Distribuição por Status
                </h3>
                <div style="height: 300px; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1rem;">
                        <i class="fas fa-list-ul" style="margin-right: 10px; color: var(--primary);"></i> Últimas Solicitações
                    </h3>
                    <a href="oficios_lista.php" class="btn btn-outline btn-sm">Ver Todos</a>
                </div>

                <div class="table-responsive">
                    <table class="table-vcenter text-nowrap dashboard-nowrap" style="white-space: nowrap !important;">
                        <thead>
                            <tr>
                                <th style="white-space: nowrap !important;">Número</th>
                                <th style="white-space: nowrap !important;">Secretaria</th>
                                <th style="white-space: nowrap !important;">Status</th>
                                <th class="w-1" style="white-space: nowrap !important;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_oficios as $oficio): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--primary); white-space: nowrap !important;">
                                        <?php echo $oficio['numero']; ?>
                                    </td>
                                    <td style="white-space: nowrap !important;">
                                        <span class="text-muted" style="white-space: nowrap !important;">
                                            <?php echo $oficio['secretaria']; ?>
                                        </span>
                                    </td>
                                    <td style="white-space: nowrap !important;">
                                        <span class="badge badge-<?php echo strtolower($oficio['status'] == 'ENVIADO' ? 'pending' : ($oficio['status'] == 'APROVADO' ? 'approved' : ($oficio['status'] == 'REPROVADO' ? 'rejected' : 'finalized'))); ?>" style="white-space: nowrap !important;">
                                            <?php echo $oficio['status']; ?>
                                        </span>
                                    </td>
                                    <td style="white-space: nowrap !important;">
                                        <a href="oficios_visualizar.php?id=<?php echo $oficio['id']; ?>" class="btn btn-outline btn-sm" title="Visualizar" style="white-space: nowrap !important;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($ultimos_oficios)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding: 2rem; color: var(--text-muted); white-space: nowrap !important;">
                                        Nenhuma solicitação encontrada.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Aguardando', 'Aprovados', 'Finalizados'],
            datasets: [{
                label: 'Quantidade de Solicitações',
                data: [<?php echo $total_aguardando; ?>, <?php echo $total_aprovados; ?>, <?php echo $total_finalizados; ?>],
                backgroundColor: ['#f1c40f', '#27ae60', '#9b59b6'],
                borderRadius: 8,
                barPercentage: 0.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
});
</script>

<?php include 'views/layout/footer.php'; ?>