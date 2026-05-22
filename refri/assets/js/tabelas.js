let tableData = null;

document.addEventListener('DOMContentLoaded', () => {
  initModal();
  loadTable();

  document.getElementById('applyFilters')?.addEventListener('click', applyFilters);
  document.getElementById('clearFilters')?.addEventListener('click', clearFilters);
  const searchInput = document.getElementById('tableSearch') || document.getElementById('searchInput');
  searchInput?.addEventListener('input', debounce(applyFilters, 220));
  document.getElementById('statusFilter')?.addEventListener('change', applyFilters);
  document.getElementById('openFiltersMobile')?.addEventListener('click', () => {
    document.getElementById('filterPanel')?.classList.toggle('is-open');
  });
});

function loadTable() {
  const tipo = document.querySelector('[data-table-type]')?.dataset.tableType || window.KY_TABLE_TYPE || 'os';

  KY.fetchJson(`api/listagem.php?tipo=${encodeURIComponent(tipo)}`)
    .then((data) => {
      tableData = data;
      renderSummary(data.summary);
      renderTable(data.columns, data.rows);
    })
    .catch((error) => console.error(error));
}

function renderSummary(summary) {
  const root = document.getElementById('tableSummary');
  root.innerHTML = summary.map((item) => `
    <article class="mini-card">
      <div class="mini-card__content">
        <span>${item.label}</span>
        <strong>${item.value}</strong>
      </div>
      <div class="mini-card__icon">${item.icon}</div>
    </article>
  `).join('');
}

function renderTable(columns, rows) {
  const head = document.getElementById('tableHead');
  const body = document.getElementById('tableBody');
  const cards = document.getElementById('mobileCards');
  const info = document.getElementById('paginationInfo');

  head.innerHTML = `<tr>${columns.map((column) => `<th>${column.label}</th>`).join('')}<th>Ações</th></tr>`;

  body.innerHTML = rows.length ? rows.map((row) => `
    <tr>
      ${columns.map((column) => `<td>${formatCell(row[column.key], column.key)}</td>`).join('')}
      <td>${renderActions(row)}</td>
    </tr>
  `).join('') : `<tr><td colspan="${columns.length + 1}">Nenhum registro encontrado.</td></tr>`;

  cards.innerHTML = rows.map((row) => `
    <article class="record-card">
      <div class="record-card__top">
        <div>
          <h3>${row.numero || row.nome || row.cliente}</h3>
          <p>${row.cliente || row.telefone || row.servico || row.categoria}</p>
        </div>
        ${row.status ? KY.badge(row.status) : ''}
      </div>
      <div class="record-card__meta">
        ${columns.slice(1, 5).map((column) => `
          <div>
            <span>${column.label}</span>
            <strong>${stripHtml(formatCell(row[column.key], column.key))}</strong>
          </div>
        `).join('')}
      </div>
      <div class="record-card__actions">
        <button class="row-action">Ver</button>
        <button class="row-action whatsapp-action">WhatsApp</button>
      </div>
    </article>
  `).join('');

  info.textContent = `Mostrando ${rows.length} registros`;
}

function formatCell(value, key) {
  if (key === 'status') return KY.badge(value);
  if (key === 'valor' || key === 'total' || key === 'estoqueValor') return value;
  return value ?? '-';
}

function renderActions(row) {
  const whatsappText = encodeURIComponent(`Olá, ${row.cliente || row.nome || 'cliente'}! Segue o orçamento/OS para acompanhamento: https://kyamaguchi.local/documento/${row.numero || row.id}`);
  return `
    <div class="action-menu">
      <button class="row-action">Ver</button>
      <button class="row-action">Editar</button>
      <a class="row-action whatsapp-action" href="https://wa.me/?text=${whatsappText}" target="_blank" rel="noopener">WhatsApp</a>
      <button class="row-action">⋯</button>
    </div>
  `;
}

function applyFilters() {
  if (!tableData) return;

  const searchEl = document.getElementById('tableSearch') || document.getElementById('searchInput');
  const search = searchEl?.value.toLowerCase().trim() || '';
  const status = document.getElementById('statusFilter')?.value || '';

  const rows = tableData.rows.filter((row) => {
    const all = Object.values(row).join(' ').toLowerCase();
    const matchSearch = !search || all.includes(search);
    const matchStatus = !status || row.status === status;
    return matchSearch && matchStatus;
  });

  renderTable(tableData.columns, rows);
}

function clearFilters() {
  const searchEl = document.getElementById('tableSearch') || document.getElementById('searchInput');
  if (searchEl) searchEl.value = '';
  document.getElementById('statusFilter').value = '';
  document.getElementById('dateStart').value = '';
  document.getElementById('dateEnd').value = '';
  if (tableData) renderTable(tableData.columns, tableData.rows);
}

function initModal() {
  const modal = document.getElementById('createModal');
  const open = document.getElementById('openCreateModal');
  const close = document.getElementById('closeCreateModal');
  const cancel = document.getElementById('cancelModal');

  const show = () => {
    modal?.classList.add('is-open');
    modal?.setAttribute('aria-hidden', 'false');
  };
  const hide = () => {
    modal?.classList.remove('is-open');
    modal?.setAttribute('aria-hidden', 'true');
  };

  open?.addEventListener('click', show);
  close?.addEventListener('click', hide);
  cancel?.addEventListener('click', hide);
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) hide();
  });
}

function stripHtml(html) {
  const div = document.createElement('div');
  div.innerHTML = html;
  return div.textContent || div.innerText || '';
}

function debounce(fn, wait) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}
