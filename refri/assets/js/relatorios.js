document.addEventListener('DOMContentLoaded', async () => {
  try {
    const data = await App.fetchJson('api/relatorios.php');
    App.renderStats('#reportSummary', data.summary);
    App.drawBarChart(document.querySelector('#revenueReportChart'), data.revenue, { color: '#0f766e' });
    App.drawHorizontalChart(document.querySelector('#servicesReportChart'), data.services, { color: '#2563eb' });
    App.drawDonut(document.querySelector('#budgetReportChart'), data.budgets, document.querySelector('#budgetLegend'));
    document.querySelector('#partsReportTable').innerHTML = data.parts.map(i => `<tr><td data-label="Peça">${i.nome}</td><td data-label="Qtd.">${i.qtd}</td><td data-label="Total">${i.total}</td><td data-label="Status">${App.badge(i.status)}</td></tr>`).join('');
    document.querySelector('#reportTable').innerHTML = data.rows.map(i => `<tr><td data-label="Data">${i.data}</td><td data-label="Cliente">${i.cliente}</td><td data-label="Tipo">${i.tipo}</td><td data-label="Serviço">${i.servico}</td><td data-label="Status">${App.badge(i.status)}</td><td data-label="Técnico">${i.tecnico}</td><td data-label="Valor">${i.valor}</td><td data-label="Pagamento">${App.badge(i.pagamento)}</td><td data-label="Nota">${App.badge(i.nota)}</td></tr>`).join('');
  } catch (e) { App.toast(e.message); }
  document.querySelectorAll('.period-btn').forEach(btn => btn.addEventListener('click', () => {
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('is-active'));
    btn.classList.add('is-active');
    App.toast(`Período aplicado: ${btn.textContent}`);
  }));
  document.querySelector('#exportPdf')?.addEventListener('click', () => App.toast('Exportação PDF pronta para integrar com back-end real.'));
  document.querySelector('#exportExcel')?.addEventListener('click', () => App.toast('Exportação Excel pronta para integrar com back-end real.'));
  document.querySelector('#printReport')?.addEventListener('click', () => window.print());
});
