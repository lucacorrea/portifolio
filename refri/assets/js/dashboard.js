document.addEventListener('DOMContentLoaded', () => {
  KY.fetchJson('api/dashboard.php')
    .then((data) => {
      renderStats(data.stats);
      renderRecentOrders(data.recentOrders);
      renderAgenda(data.agenda);
      renderAlerts(data.alerts);
      drawCharts(data);
    })
    .catch((error) => console.error(error));

  window.addEventListener('resize', debounce(() => {
    KY.fetchJson('api/dashboard.php').then(drawCharts);
  }, 180));
});

function renderStats(stats) {
  const root = document.getElementById('dashboardStats');
  root.innerHTML = stats.map((item) => `
    <article class="metric-card">
      <div class="metric-card__top">
        <div>
          <h3>${item.label}</h3>
          <strong>${item.value}</strong>
        </div>
        <div class="metric-card__icon tone-${item.tone}">${item.icon}</div>
      </div>
      <p>${item.helper}</p>
    </article>
  `).join('');
}

function renderRecentOrders(orders) {
  const root = document.getElementById('recentOrders');
  root.innerHTML = orders.map((order) => `
    <tr>
      <td><strong>${order.os}</strong></td>
      <td>${order.cliente}</td>
      <td>${order.servico}</td>
      <td>${KY.badge(order.status)}</td>
      <td>${order.valor}</td>
      <td><a class="link-action" href="tabelas.php?tipo=os&registro=${encodeURIComponent(order.os)}">Ver</a></td>
    </tr>
  `).join('');
}

function renderAgenda(agenda) {
  const root = document.getElementById('todayAgenda');
  root.innerHTML = agenda.map((item) => `
    <div class="timeline-item">
      <div class="timeline-item__time">${item.hora}</div>
      <div>
        <strong>${item.servico}</strong>
        <span>${item.cliente} · ${item.local}</span>
      </div>
    </div>
  `).join('');
}

function renderAlerts(alerts) {
  const root = document.getElementById('alertsList');
  root.innerHTML = alerts.map((item) => `
    <div class="alert-item">
      <div class="alert-item__icon">⚠</div>
      <div>
        <strong>${item.title}</strong>
        <p>${item.text}</p>
      </div>
    </div>
  `).join('');
}

function drawCharts(data) {
  KY.drawBars(
    document.getElementById('statusChart'),
    data.osStatus.map((item) => item.name),
    data.osStatus.map((item) => item.value),
    { color: '#0f766e' }
  );

  KY.drawLineArea(
    document.getElementById('revenueChart'),
    data.revenue.map((item) => item.month),
    data.revenue.map((item) => item.value)
  );
}

function debounce(fn, wait) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}
