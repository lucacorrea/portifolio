<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$nivel = strtoupper($_SESSION['nivel'] ?? '');

$page_title = "Dashboard Geral";
include 'views/layout/header.php';

// KPIs
$total_oficios = $pdo->query("SELECT COUNT(*) FROM oficios")->fetchColumn();
$total_pendente = $pdo->query("SELECT COUNT(*) FROM oficios WHERE status = 'PENDENTE_ITENS'")->fetchColumn();
$total_aguardando = $pdo->query("SELECT COUNT(*) FROM oficios WHERE status = 'ENVIADO'")->fetchColumn();
$total_aprovados = $pdo->query("SELECT COUNT(*) FROM oficios WHERE status = 'APROVADO'")->fetchColumn();

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
    .dashboard-nowrap, .dashboard-nowrap th, .dashboard-nowrap td, .dashboard-nowrap span, .dashboard-nowrap a {
        white-space: nowrap !important;
        text-align: left !important;
    }
    .card-icon {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
</style>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-body">
            <div class="card-icon"><i class="fas fa-file-alt" style="color: var(--primary);"></i></div>
            <div class="card-label">Total de Solicitações</div>
            <div class="card-number"><?php echo $total_oficios; ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-icon"><i class="fas fa-clipboard-list" style="color: var(--secondary);"></i></div>
            <div class="card-label">Aguardando Itens (SEFAZ)</div>
            <div class="card-number"><?php echo $total_pendente; ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-icon"><i class="fas fa-clock" style="color: var(--status-pending);"></i></div>
            <div class="card-label">Pendente de Análise</div>
            <div class="card-number"><?php echo $total_aguardando; ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-icon"><i class="fas fa-check-circle" style="color: var(--status-approved);"></i></div>
            <div class="card-label">Aprovadas (Prontas para AQ)</div>
            <div class="card-number"><?php echo $total_aprovados; ?></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-dark); font-weight: 700; font-size: 1rem;">
                    <i class="fas fa-chart-pie" style="margin-right: 10px; color: var(--primary);"></i> Fluxo Operacional
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
                    <?php 
                        $list_url = ($nivel === 'SEFAZ') ? 'oficios_lista_sefaz.php' : 'oficios_lista.php';
                    ?>
                    <a href="<?php echo $list_url; ?>" class="btn btn-outline btn-sm">Ver Todas</a>
                </div>

                <div class="table-responsive">
                    <table class="table-vcenter text-nowrap dashboard-nowrap">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Secretaria</th>
                                <th>Status</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_oficios as $oficio): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--primary);">
                                        <?php echo $oficio['numero']; ?>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo $oficio['secretaria']; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                            $b_class = 'badge-pending';
                                            if($oficio['status'] == 'ENVIADO') $b_class = 'badge-primary';
                                            if($oficio['status'] == 'APROVADO') $b_class = 'badge-approved';
                                            if($oficio['status'] == 'REPROVADO') $b_class = 'badge-rejected';
                                        ?>
                                        <span class="badge <?php echo $b_class; ?>">
                                            <?php echo $oficio['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="oficios_visualizar.php?id=<?php echo $oficio['id']; ?>" class="btn btn-outline btn-sm" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
        type: 'doughnut',
        data: {
            labels: ['Aguardando Itens', 'Aguardando Análise', 'Aprovados'],
            datasets: [{
                data: [<?php echo $total_pendente; ?>, <?php echo $total_aguardando; ?>, <?php echo $total_aprovados; ?>],
                backgroundColor: ['#64748b', '#0d6efd', '#198754'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '70%'
        }
    });
});
</script>

<?php include 'views/layout/footer.php'; ?>