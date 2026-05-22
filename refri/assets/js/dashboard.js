document.addEventListener('DOMContentLoaded', async () => {
  try {
    const data = await App.fetchJson('api/dashboard.php');
    App.renderStats('#dashboardStats', data.stats);
    App.drawHorizontalChart(document.querySelector('#osStatusChart'), data.osStatus, { color: '#0f766e' });
    App.drawBarChart(document.querySelector('#revenueChart'), data.revenue, { color: '#2563eb' });
    document.querySelector('#recentOrders').innerHTML = data.orders.map(item => `
      <tr>
        <td data-label="OS"><span class="table-title">${item.numero}</span><span class="table-subtitle">${item.data}</span></td>
        <td data-label="Cliente">${item.cliente}</td>
        <td data-label="Serviço">${item.servico}</td>
        <td data-label="Status">${App.badge(item.status)}</td>
        <td data-label="Técnico">${item.tecnico}</td>
        <td data-label="Valor">${App.money(item.valor)}</td>
        <td data-label="Ações"><div class="table-actions"><button class="btn btn--secondary btn--sm">Ver</button></div></td>
      </tr>`).join('');
    document.querySelector('#todaySchedule').innerHTML = data.schedule.map(item => `
      <div class="dash-item"><div class="dash-item__time">${item.time}</div><div><strong>${item.title}</strong><span>${item.client}</span></div>${App.badge(item.status)}</div>`).join('');
    document.querySelector('#alertsList').innerHTML = data.alerts.map(item => `<div class="alert-card"><strong>${item.title}</strong><span>${item.text}</span></div>`).join('');
  } catch (e) {
    App.toast(e.message);
  }
});
