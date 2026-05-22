<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/topbar.php'; ?>

<div class="app-wrapper">
  <!-- sidebar já incluída -->
  <main class="main-content">
    <h1 class="section-title">Dashboard</h1>

    <!-- KPIs -->
    <div class="dashboard-grid">
      <div class="card metric-card">
        <div class="metric-icon">📋</div>
        <div class="metric-info">
          <h3>Total de OS</h3>
          <div class="metric-value" id="metric-total-os">--</div>
          <div class="metric-change text-secondary">+5% desde o mês passado</div>
        </div>
      </div>
      <div class="card metric-card">
        <div class="metric-icon">🔧</div>
        <div class="metric-info">
          <h3>OS Abertas</h3>
          <div class="metric-value" id="metric-os-abertas">--</div>
          <div class="metric-change text-warning">12 urgentes</div>
        </div>
      </div>
      <div class="card metric-card">
        <div class="metric-icon">💰</div>
        <div class="metric-info">
          <h3>Faturamento (mês)</h3>
          <div class="metric-value" id="metric-faturamento">--</div>
          <div class="metric-change text-success">+12%</div>
        </div>
      </div>
      <div class="card metric-card">
        <div class="metric-icon">👥</div>
        <div class="metric-info">
          <h3>Clientes ativos</h3>
          <div class="metric-value" id="metric-clientes">--</div>
          <div class="metric-change text-secondary">últimos 30 dias</div>
        </div>
      </div>
    </div>

    <!-- Gráfico + Atividades recentes -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">OS por status</h2>
        </div>
        <canvas id="chart-status" height="200"></canvas>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h2 class="panel-title">Atividades recentes</h2>
        </div>
        <ul class="activity-list">
          <li class="activity-item">
            <span class="activity-text">OS #1023 - Manutenção finalizada</span>
            <span class="activity-time">10 min atrás</span>
          </li>
          <li class="activity-item">
            <span class="activity-text">Cliente João Silva agendou visita</span>
            <span class="activity-time">1 h atrás</span>
          </li>
          <li class="activity-item">
            <span class="activity-text">Peça compressor recebida</span>
            <span class="activity-time">3 h atrás</span>
          </li>
        </ul>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de exemplo
const ctx = document.getElementById('chart-status').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['Abertas','Em andamento','Aguardando','Finalizadas'],
    datasets: [{
      data: [12, 8, 5, 20],
      backgroundColor: '#0F766E',
      borderRadius: 4
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } }
  }
});
</script>