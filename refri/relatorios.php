<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/topbar.php'; ?>

<div class="app-wrapper">
  <main class="main-content">
    <div class="reports-header">
      <h2>Relatórios Gerenciais</h2>
      <div class="export-buttons">
        <button class="btn btn-secondary">PDF</button>
        <button class="btn btn-secondary">Excel</button>
        <button class="btn btn-secondary">Imprimir</button>
      </div>
    </div>

    <!-- Cards resumo -->
    <div class="summary-cards">
      <div class="summary-card">
        <div class="label">OS Emitidas (mês)</div>
        <div class="value">42</div>
      </div>
      <div class="summary-card">
        <div class="label">Ticket Médio</div>
        <div class="value">R$ 850,00</div>
      </div>
      <div class="summary-card">
        <div class="label">Satisfação</div>
        <div class="value">96%</div>
      </div>
      <div class="summary-card">
        <div class="label">Tempo médio (dias)</div>
        <div class="value">3.2</div>
      </div>
    </div>

    <!-- Gráfico -->
    <div class="chart-box">
      <canvas id="reportChart" height="80"></canvas>
    </div>

    <!-- Tabela analítica -->
    <div class="report-table">
      <table>
        <thead>
          <tr><th>Técnico</th><th>OS concluídas</th><th>Faturamento</th><th>Avaliação</th></tr>
        </thead>
        <tbody>
          <tr><td>Carlos</td><td>18</td><td>R$ 14.200</td><td>4.9</td></tr>
          <tr><td>Mariana</td><td>15</td><td>R$ 12.800</td><td>4.8</td></tr>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx2 = document.getElementById('reportChart').getContext('2d');
new Chart(ctx2, {
  type: 'line',
  data: {
    labels: ['Jan','Fev','Mar','Abr','Mai'],
    datasets: [{
      label: 'Faturamento',
      data: [12000,15000,11000,18000,16000],
      borderColor: '#0F766E',
      backgroundColor: 'rgba(15,118,110,0.05)',
      tension: 0.2,
      fill: true
    }]
  },
  options: {
    plugins: { legend: { display: true } },
    scales: { y: { beginAtZero: true } }
  }
});
</script>