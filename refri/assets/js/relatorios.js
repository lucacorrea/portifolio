document.addEventListener('DOMContentLoaded', () => {
  KY.fetchJson('api/relatorios.php')
    .then((data) => {
      renderSummary(data.summary);
      renderTable(data.table);
      renderCharts(data);
    })
    .catch((error) => console.error(error));

  window.addEventListener('resize', debounce(() => {
    KY.fetchJson('api/relatorios.php').then(renderCharts);
  }, 180));
});

function renderSummary(summary) {
  const root = document.getElementById('reportSummary');
  root.innerHTML = summary.map((item) => `
    <article class="report-card">
      <span>${item.label}</span>
      <strong>${item.value}</strong>
      <p>${item.helper}</p>
    </article>
  `).join('');
}

function renderTable(rows) {
  const root = document.getElementById('reportTable');
  root.innerHTML = rows.map((row) => `
    <tr>
      <td>${row.data}</td>
      <td>${row.cliente}</td>
      <td>${row.servico}</td>
      <td>${KY.badge(row.status)}</td>
      <td>${row.valor}</td>
      <td>${KY.badge(row.nota)}</td>
    </tr>
  `).join('');
}

function renderCharts(data) {
  KY.drawLineArea(
    document.getElementById('reportRevenueChart'),
    data.revenue.map((item) => item.month),
    data.revenue.map((item) => item.value)
  );

  KY.drawBars(
    document.getElementById('servicesChart'),
    data.services.map((item) => item.name),
    data.services.map((item) => item.value),
    { color: '#0f766e' }
  );

  KY.drawDonut(
    document.getElementById('budgetChart'),
    data.budgets,
    document.getElementById('budgetLegend')
  );
}

function debounce(fn, wait) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}
