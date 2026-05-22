document.addEventListener('DOMContentLoaded', async () => {
  try {
    const data = await App.fetchJson('api/dashboard.php');

    App.renderStats('#dashboardStats', data.stats);
    App.drawHorizontalChart(document.querySelector('#osStatusChart'), data.osStatus, { color: '#0f766e' });
    App.drawBarChart(document.querySelector('#revenueChart'), data.revenue, { color: '#2563eb' });
    renderRecentOrders(data.orders || []);
    renderSchedule(data.schedule || []);
    renderAlerts(data.alerts || []);
    bindDashboardSearch();

    window.addEventListener('resize', App.debounce(() => {
      App.drawHorizontalChart(document.querySelector('#osStatusChart'), data.osStatus, { color: '#0f766e' });
      App.drawBarChart(document.querySelector('#revenueChart'), data.revenue, { color: '#2563eb' });
    }, 180));
  } catch (error) {
    App.toast(error.message);
  }
});

function renderRecentOrders(orders) {
  const root = document.querySelector('#recentOrders');
  if (!root) return;

  root.innerHTML = orders.length ? orders.map((item) => `
    <tr data-row="${App.escapeHtml(JSON.stringify(item))}">
      <td data-label="OS">
        <span class="table-title">${App.escapeHtml(item.numero)}</span>
        <span class="table-subtitle">${App.escapeHtml(item.data)}</span>
      </td>
      <td data-label="Cliente">${App.escapeHtml(item.cliente)}</td>
      <td data-label="Serviço">${App.escapeHtml(item.servico)}</td>
      <td data-label="Status">${App.badge(item.status)}</td>
      <td data-label="Técnico">${App.escapeHtml(item.tecnico)}</td>
      <td data-label="Valor">${App.money(item.valor)}</td>
      <td data-label="Ações">
        <div class="table-actions">
          <a class="btn btn--secondary btn--sm" href="ordens-servico.php">Ver</a>
        </div>
      </td>
    </tr>
  `).join('') : `<tr><td colspan="7"><div class="empty-state">Nenhuma OS recente encontrada.</div></td></tr>`;
}

function renderSchedule(schedule) {
  const root = document.querySelector('#todaySchedule');
  if (!root) return;

  root.innerHTML = schedule.map((item) => `
    <div class="dash-item">
      <div class="dash-item__time">${App.escapeHtml(item.time)}</div>
      <div>
        <strong>${App.escapeHtml(item.title)}</strong>
        <span>${App.escapeHtml(item.client)}</span>
      </div>
      ${App.badge(item.status)}
    </div>
  `).join('');
}

function renderAlerts(alerts) {
  const root = document.querySelector('#alertsList');
  if (!root) return;

  root.innerHTML = alerts.map((item) => `
    <div class="alert-card">
      <strong>${App.escapeHtml(item.title)}</strong>
      <span>${App.escapeHtml(item.text)}</span>
    </div>
  `).join('');
}

function bindDashboardSearch() {
  const search = document.querySelector('#globalSearch');
  const rows = Array.from(document.querySelectorAll('#recentOrders tr[data-row]'));
  if (!search || !rows.length) return;

  search.addEventListener('input', () => {
    const query = App.normalize(search.value);
    rows.forEach((row) => {
      const match = !query || App.normalize(row.dataset.row + row.textContent).includes(query);
      row.style.display = match ? '' : 'none';
    });
  });
}
