const tableState = { rows: [], filtered: [] };

const rowTemplates = {
  clientes: (i) => `<tr><td data-label="Cliente"><span class="table-title">${i.nome}</span><span class="table-subtitle">${i.doc}</span></td><td data-label="Contato">${i.telefone}<span class="table-subtitle">${i.email}</span></td><td data-label="Tipo">${i.tipo}</td><td data-label="Cidade">${i.cidade}</td><td data-label="OS ativa">${i.os_ativa}</td><td data-label="Status">${App.badge(i.status)}</td><td data-label="Ações"><div class="table-actions"><button class="btn btn--secondary btn--sm">Ver</button><button class="btn btn--secondary btn--sm">WhatsApp</button></div></td></tr>`,
  os: (i) => `<tr><td data-label="OS"><span class="table-title">${i.numero}</span><span class="table-subtitle">${i.data}</span></td><td data-label="Cliente">${i.cliente}</td><td data-label="Serviço">${i.servico}</td><td data-label="Equipamento">${i.equipamento}</td><td data-label="Status">${App.badge(i.status)}</td><td data-label="Técnico">${i.tecnico}</td><td data-label="Valor">${App.money(i.valor)}</td><td data-label="Ações"><div class="table-actions"><button class="btn btn--secondary btn--sm">Ver</button><button class="btn btn--secondary btn--sm">PDF</button></div></td></tr>`,
  orcamentos: (i) => `<tr><td data-label="Orçamento"><span class="table-title">${i.numero}</span><span class="table-subtitle">${i.telefone}</span></td><td data-label="Cliente">${i.cliente}</td><td data-label="Validade">${i.validade}</td><td data-label="Status">${App.badge(i.status)}</td><td data-label="Total">${App.money(i.total)}</td><td data-label="Responsável">${i.responsavel}</td><td data-label="Ações"><div class="table-actions"><button class="btn btn--secondary btn--sm">Ver</button><button class="btn btn--primary btn--sm" data-send-budget="${i.id}">WhatsApp</button></div></td></tr>`,
  pecas: (i) => `<tr><td data-label="Peça"><span class="table-title">${i.nome}</span></td><td data-label="Código">${i.codigo}</td><td data-label="Categoria">${i.categoria}</td><td data-label="Estoque">${i.estoque} / mín. ${i.minimo}</td><td data-label="Custo">${App.money(i.custo)}</td><td data-label="Venda">${App.money(i.venda)}</td><td data-label="Status">${App.badge(i.status)}</td><td data-label="Ações"><div class="table-actions"><button class="btn btn--secondary btn--sm">Editar</button></div></td></tr>`,
  servicos: (i) => `<tr><td data-label="Serviço"><span class="table-title">${i.nome}</span></td><td data-label="Categoria">${i.categoria}</td><td data-label="Valor base">${App.money(i.valor)}</td><td data-label="Tempo médio">${i.tempo}</td><td data-label="Status">${App.badge(i.status)}</td><td data-label="Ações"><div class="table-actions"><button class="btn btn--secondary btn--sm">Editar</button></div></td></tr>`,
  notas: (i) => `<tr><td data-label="Nota"><span class="table-title">${i.numero}</span></td><td data-label="Cliente">${i.cliente}</td><td data-label="OS">${i.os}</td><td data-label="Tipo">${i.tipo}</td><td data-label="Status">${App.badge(i.status)}</td><td data-label="Valor">${App.money(i.valor)}</td><td data-label="Data">${i.data}</td><td data-label="Ações"><div class="table-actions"><button class="btn btn--secondary btn--sm">XML</button><button class="btn btn--secondary btn--sm">PDF</button></div></td></tr>`
};

function renderTable() {
  const body = document.querySelector('#tableBody');
  if (!body) return;
  const endpoint = body.dataset.endpoint;
  const template = rowTemplates[endpoint];
  if (!tableState.filtered.length) {
    body.innerHTML = `<tr><td colspan="9"><div class="empty-state">Nenhum registro encontrado.</div></td></tr>`;
  } else {
    body.innerHTML = tableState.filtered.map(template).join('');
  }
  document.querySelector('#tableCount').textContent = `${tableState.filtered.length} registro(s) encontrado(s)`;
}

function applyFilter() {
  const q = (document.querySelector('#tableSearch')?.value || '').toLowerCase();
  const status = document.querySelector('#filterStatus')?.value || '';
  const type = document.querySelector('#filterType')?.value || '';
  tableState.filtered = tableState.rows.filter(row => {
    const rowText = JSON.stringify(row).toLowerCase();
    const matchSearch = !q || rowText.includes(q);
    const matchStatus = !status || row.status === status;
    const matchType = !type || row.tipo === type || row.categoria === type;
    return matchSearch && matchStatus && matchType;
  });
  renderTable();
}

document.addEventListener('DOMContentLoaded', async () => {
  const body = document.querySelector('#tableBody');
  if (!body) return;
  try {
    const endpoint = body.dataset.endpoint;
    const data = await App.fetchJson(`api/${endpoint}.php`);
    App.renderStats('#summaryCards', data.summary || []);
    tableState.rows = data.items || [];
    tableState.filtered = tableState.rows;
    renderTable();
  } catch (e) { App.toast(e.message); }
  document.querySelector('#btnFilter')?.addEventListener('click', applyFilter);
  document.querySelector('#tableSearch')?.addEventListener('input', applyFilter);
  document.querySelector('#filterStatus')?.addEventListener('change', applyFilter);
  document.querySelector('#filterType')?.addEventListener('change', applyFilter);
  document.querySelector('#btnClear')?.addEventListener('click', () => {
    document.querySelectorAll('.filter-panel input, .filter-panel select').forEach(el => el.value = '');
    tableState.filtered = tableState.rows;
    renderTable();
  });
});
