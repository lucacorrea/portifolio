<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

// 🔥 MODO AJAX (SEM ARQUIVO EXTERNO)
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // KPIs
    $total_oficios = $pdo->query("SELECT COUNT(*) FROM oficios")->fetchColumn();
    $total_pendente = $pdo->query("SELECT COUNT(*) FROM oficios WHERE status = 'PENDENTE_ITENS'")->fetchColumn();
    $total_aguardando = $pdo->query("SELECT COUNT(*) FROM oficios WHERE status = 'ENVIADO'")->fetchColumn();
    $total_aprovados = $pdo->query("SELECT COUNT(*) FROM oficios WHERE status = 'APROVADO'")->fetchColumn();

    // Últimos
    $stmt = $pdo->query("
        SELECT o.*, s.nome as secretaria 
        FROM oficios o 
        JOIN secretarias s ON o.secretaria_id = s.id 
        ORDER BY o.criado_em DESC LIMIT 5
    ");

    echo json_encode([
        'kpis' => [
            'total' => $total_oficios,
            'pendente' => $total_pendente,
            'aguardando' => $total_aguardando,
            'aprovados' => $total_aprovados
        ],
        'ultimos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

    exit;
}

$nivel = strtoupper($_SESSION['nivel'] ?? '');
$page_title = "Dashboard Geral";
include 'views/layout/header.php';
?>

<style>
.dashboard-nowrap, .dashboard-nowrap th, .dashboard-nowrap td {
    white-space: nowrap !important;
}

.card-icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}
</style>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-body">
            <div class="card-label">Total</div>
            <div class="card-number" id="total_oficios">0</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-label">Aguardando Itens</div>
            <div class="card-number" id="total_pendente">0</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-label">Em Análise</div>
            <div class="card-number" id="total_aguardando">0</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-label">Aprovados</div>
            <div class="card-number" id="total_aprovados">0</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <canvas id="statusChart" style="height:300px;"></canvas>
    </div>

    <div class="col-md-6">
        <table class="table dashboard-nowrap">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Secretaria</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tabela_oficios"></tbody>
        </table>
    </div>
</div>

<script>
let chart;

// 🔥 FUNÇÃO PRINCIPAL
async function carregarDashboard() {
    const res = await fetch(window.location.pathname + '?ajax=1');
    const data = await res.json();

    // KPIs
    document.getElementById('total_oficios').innerText = data.kpis.total;
    document.getElementById('total_pendente').innerText = data.kpis.pendente;
    document.getElementById('total_aguardando').innerText = data.kpis.aguardando;
    document.getElementById('total_aprovados').innerText = data.kpis.aprovados;

    // TABELA
    let html = '';
    data.ultimos.forEach(o => {

        let badge = 'badge-secondary';
        if(o.status === 'ENVIADO') badge = 'badge-primary';
        if(o.status === 'APROVADO') badge = 'badge-success';
        if(o.status === 'REPROVADO') badge = 'badge-danger';

        html += `
            <tr>
                <td><strong>${o.numero}</strong></td>
                <td>${o.secretaria}</td>
                <td><span class="badge ${badge}">${o.status}</span></td>
                <td>
                    <a href="oficios_visualizar.php?id=${o.id}" class="btn btn-sm btn-outline">
                        👁
                    </a>
                </td>
            </tr>
        `;
    });

    document.getElementById('tabela_oficios').innerHTML = html;

    // GRÁFICO
    if (chart) {
        chart.data.datasets[0].data = [
            data.kpis.pendente,
            data.kpis.aguardando,
            data.kpis.aprovados
        ];
        chart.update();
    } else {
        const ctx = document.getElementById('statusChart').getContext('2d');

        chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Itens', 'Análise', 'Aprovados'],
                datasets: [{
                    data: [
                        data.kpis.pendente,
                        data.kpis.aguardando,
                        data.kpis.aprovados
                    ],
                    backgroundColor: ['#64748b','#0d6efd','#198754']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%'
            }
        });
    }
}

// 🔥 AUTO UPDATE
setInterval(carregarDashboard, 5000);

// PRIMEIRA CARGA
carregarDashboard();
</script>

<?php include 'views/layout/footer.php'; ?>