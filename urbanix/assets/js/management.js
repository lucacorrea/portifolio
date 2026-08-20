(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const Store = Urbanix.Store;
  const UI = Urbanix.UI;
  const money = value => UI.formatMoney(value, { maximumFractionDigits: 2 });
  const state = () => Store.getState();
  const byId = (items, id) => items.find(item => item.id === id);
  const name = (items, id, key) => byId(items, id)?.[key || 'name'] || 'Nao informado';
  const sum = (items, key) => items.reduce((total, item) => total + Number(item[key || 'amount'] || 0), 0);

  function badge(status) {
    const map = { active: ['Ativo', 'soft-success'], prospect: ['Prospect', 'soft-info'], paid: ['Pago', 'soft-success'], pending: ['A vencer', 'soft-info'], overdue: ['Vencido', 'soft-danger'], available: ['Disponivel', 'soft-success'], reserved: ['Reservado', 'soft-warning'], sold: ['Vendido', 'soft-danger'], blocked: ['Bloqueado', 'soft-muted'], submitted: ['Aguardando', 'soft-warning'], approved: ['Aprovado', 'soft-success'], received: ['Recebido', 'soft-success'] };
    const value = map[status] || [status || 'Pendente', 'soft-muted'];
    return `<span class="badge-soft ${value[1]}">${value[0]}</span>`;
  }

  async function formDialog(title, fields, submitLabel, eyebrow) {
    const form = document.createElement('form');
    form.className = 'row g-3';
    form.innerHTML = fields;
    const result = UI.openModal({ eyebrow: eyebrow || 'Urbanix ERP', title, content: form, actions: [{ label: 'Cancelar', variant: 'outline', value: null }, { label: submitLabel || 'Salvar', variant: 'primary', value: 'submit' }] });
    UI.applyMasks(form);
    const submit = form.closest('.urbanix-dialog').querySelector('footer .btn-primary-app');
    submit.addEventListener('click', event => { if (form.checkValidity()) return; event.stopImmediatePropagation(); form.reportValidity(); }, true);
    form.addEventListener('submit', event => { event.preventDefault(); submit.click(); });
    return (await result) === 'submit' ? new FormData(form) : null;
  }

  function renderDashboard() {
    const section = document.querySelector('.content');
    if (!section) return;
    const current = state();
    const vgv = sum(current.enterprises, 'estimatedVgv');
    const sold = sum(current.sales.filter(item => item.status === 'active'));
    const received = sum(current.payments);
    const receivable = current.accountsReceivable.filter(item => item.status !== 'paid');
    const overdue = receivable.filter(item => item.status === 'overdue');
    const overdueRate = receivable.length ? (overdue.length / receivable.length * 100).toFixed(1) : '0,0';
    const available = current.units.filter(item => item.status === 'available').length;
    const reserved = current.units.filter(item => item.status === 'reserved').length;
    section.innerHTML = `<div class="section-head"><div><h2>Visao executiva</h2><p>Indicadores consolidados e atualizados a partir da base local da demonstracao.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-app" href="relatorios.html">Abrir relatorios</a><a class="btn btn-primary-app" href="empreendimentos.html?action=new"><i class="bi bi-plus-lg me-2"></i>Novo empreendimento</a></div></div>
      <div class="row g-3 mb-3"><div class="col-xl-3 col-md-6"><div class="card-app metric"><div class="label">VGV total</div><div class="value">${money(vgv)}</div><div class="delta">${current.enterprises.filter(item => item.status !== 'archived').length} empreendimentos ativos</div><div class="metric-icon"><i class="bi bi-currency-dollar"></i></div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric gold"><div class="label">VGV vendido</div><div class="value">${money(sold)}</div><div class="delta">${vgv ? (sold / vgv * 100).toFixed(1) : 0}% comercializado</div><div class="metric-icon"><i class="bi bi-graph-up-arrow"></i></div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric blue"><div class="label">Recebido</div><div class="value">${money(received)}</div><div class="delta">${current.payments.length} pagamentos registrados</div><div class="metric-icon"><i class="bi bi-wallet2"></i></div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric red"><div class="label">Inadimplencia</div><div class="value">${overdueRate}%</div><div class="delta">${overdue.length} parcelas vencidas</div><div class="metric-icon"><i class="bi bi-exclamation-triangle"></i></div></div></div></div>
      <div class="row g-3 mb-3"><div class="col-xl-8"><div class="card-app"><div class="card-header d-flex justify-content-between"><strong>Vendas mensais</strong><span class="text-secondary small">Desempenho comercial</span></div><div class="card-body"><div class="chart-wrap"><canvas id="salesChart"></canvas></div></div></div></div><div class="col-xl-4"><div class="card-app h-100"><div class="card-header"><strong>Disponibilidade</strong></div><div class="card-body"><div class="mini-chart"><canvas id="availabilityChart"></canvas></div><div class="donut-legend mt-3"><div class="legend-line"><span><i class="dot"></i>Vendidos</span><strong>${current.units.filter(item => item.status === 'sold').length}</strong></div><div class="legend-line"><span><i class="dot" style="background:#2767c5"></i>Disponiveis</span><strong>${available}</strong></div><div class="legend-line"><span><i class="dot" style="background:#d7a84b"></i>Reservados</span><strong>${reserved}</strong></div><div class="legend-line"><span><i class="dot" style="background:#a6aebb"></i>Bloqueados</span><strong>${current.units.filter(item => item.status === 'blocked').length}</strong></div></div></div></div></div></div>
      <div class="row g-3 mb-3"><div class="col-md-3"><div class="card-app metric"><div class="label">Leads</div><div class="value">${current.leads.length}</div><div class="delta">${current.leads.filter(item => item.stage === 'new').length} novos</div></div></div><div class="col-md-3"><div class="card-app metric blue"><div class="label">Propostas abertas</div><div class="value">${current.proposals.filter(item => !['approved', 'rejected', 'expired'].includes(item.status)).length}</div><div class="delta">Funil comercial</div></div></div><div class="col-md-3"><div class="card-app metric gold"><div class="label">Reservas</div><div class="value">${current.reservations.filter(item => item.status === 'active').length}</div><div class="delta">${reserved} unidades reservadas</div></div></div><div class="col-md-3"><div class="card-app metric"><div class="label">Obras</div><div class="value">${current.works.length}</div><div class="delta">${Math.round(current.works.reduce((value, item) => value + item.progress, 0) / Math.max(1, current.works.length))}% de avanco medio</div></div></div></div>
      <div class="row g-3"><div class="col-xl-8"><div class="card-app"><div class="card-header d-flex justify-content-between"><strong>Empreendimentos</strong><a href="empreendimentos.html" class="small">Ver todos</a></div><div class="table-responsive"><table class="table table-app"><thead><tr><th>Empreendimento</th><th>Status</th><th>Obra</th><th>Unidades</th><th>VGV</th></tr></thead><tbody>${current.enterprises.map(item => `<tr><td><div class="object-title">${UI.escapeHtml(item.name)}</div><div class="object-sub">${UI.escapeHtml(item.city)} · ${UI.escapeHtml(item.type)}</div></td><td>${badge(item.status === 'archived' ? 'blocked' : 'active')}</td><td><div class="d-flex justify-content-between small mb-1"><span>${item.progress}%</span><span>Obra</span></div><div class="progress progress-thin"><div class="progress-bar" style="width:${item.progress}%"></div></div></td><td>${item.unitCount}</td><td><strong>${money(item.estimatedVgv)}</strong></td></tr>`).join('')}</tbody></table></div></div></div><div class="col-xl-4"><div class="card-app h-100"><div class="card-header"><strong>Atividades recentes</strong></div><div class="card-body"><div class="timeline">${current.audits.slice(-6).reverse().map(item => `<div class="timeline-item"><strong>${UI.escapeHtml(item.detail)}</strong><small>${UI.formatDate(item.createdAt, { dateStyle: 'short', timeStyle: 'short' })}</small></div>`).join('')}</div></div></div></div></div>`;
    section.querySelectorAll('.table-app').forEach(UI.enhanceTable);
  }

  const reportDefinitions = {
    sales: ['Vendas', 'bi-bag-check'], proposals: ['Propostas', 'bi-file-earmark-text'], customers: ['Clientes', 'bi-people'],
    vgv: ['VGV', 'bi-graph-up'], installments: ['Parcelas', 'bi-calendar-check'], commissions: ['Comissoes', 'bi-percent'],
    finance: ['Financeiro', 'bi-cash-coin'], works: ['Obras', 'bi-hammer'], measurements: ['Medicoes', 'bi-rulers'],
    inventory: ['Estoque', 'bi-box-seam'], purchases: ['Compras', 'bi-cart3']
  };

  function reportRows(type, current, enterpriseId) {
    const allow = item => !enterpriseId || item.enterpriseId === enterpriseId || item.workId && byId(current.works, item.workId)?.enterpriseId === enterpriseId || item.contractId && byId(current.contracts, item.contractId)?.enterpriseId === enterpriseId;
    if (type === 'sales') return { head: ['Venda', 'Cliente', 'Unidade', 'Valor', 'Data'], rows: current.sales.filter(allow).map(item => [item.number, name(current.customers, item.customerId), name(current.units, item.unitId, 'code'), money(item.amount), UI.formatDate(item.soldAt)]) };
    if (type === 'proposals') return { head: ['Proposta', 'Cliente', 'Unidade', 'Valor', 'Validade', 'Status'], rows: current.proposals.filter(allow).map(item => [item.number, name(current.customers, item.customerId), name(current.units, item.unitId, 'code'), money(item.negotiatedPrice), UI.formatDate(item.validUntil), item.status]) };
    if (type === 'customers') return { head: ['Cliente', 'CPF', 'Telefone', 'Status'], rows: current.customers.map(item => [item.name, item.cpf, item.phone, item.status]) };
    if (type === 'vgv') return { head: ['Empreendimento', 'VGV estimado', 'Vendido', 'Disponivel'], rows: current.enterprises.filter(item => !enterpriseId || item.id === enterpriseId).map(item => { const sold = sum(current.sales.filter(sale => sale.enterpriseId === item.id)); return [item.name, money(item.estimatedVgv), money(sold), money(item.estimatedVgv - sold)]; }) };
    if (type === 'installments') return { head: ['Contrato', 'Parcela', 'Vencimento', 'Valor', 'Status'], rows: current.installments.filter(allow).map(item => [name(current.contracts, item.contractId, 'number'), item.number, UI.formatDate(item.dueDate), money(item.amount), item.status]) };
    if (type === 'commissions') return { head: ['Corretor', 'Vendas', 'Valor vendido', 'Percentual', 'Comissao'], rows: current.brokers.map(broker => { const sales = current.sales.filter(item => item.brokerId === broker.id && allow(item)); const amount = sum(sales); return [broker.name, sales.length, money(amount), `${broker.commissionPercent}%`, money(amount * broker.commissionPercent / 100)]; }) };
    if (type === 'finance') return { head: ['Tipo', 'Descricao', 'Vencimento/Data', 'Valor', 'Status'], rows: current.accountsReceivable.filter(allow).map(item => ['Receber', name(current.contracts, item.contractId, 'number'), UI.formatDate(item.dueDate), money(item.amount), item.status]).concat(current.accountsPayable.filter(allow).map(item => ['Pagar', item.description, UI.formatDate(item.dueDate), money(item.amount), item.status])) };
    if (type === 'works') return { head: ['Obra', 'Orcado', 'Executado', 'Avanco', 'Status'], rows: current.works.filter(allow).map(item => [item.name, money(item.budget), money(item.executedCost), `${item.progress}%`, item.status]) };
    if (type === 'measurements') return { head: ['Medicao', 'Obra', 'Servico', 'Valor', 'Status'], rows: current.measurements.filter(allow).map(item => [item.number, name(current.works, item.workId), name(current.services, item.serviceId), money(item.amount), item.status]) };
    if (type === 'inventory') return { head: ['Material', 'Obra', 'Saldo', 'Minimo', 'Situacao'], rows: current.inventory.filter(allow).map(item => [item.material, name(current.works, item.workId), `${item.balance} ${item.unit}`, item.minimum, item.balance < item.minimum ? 'Estoque baixo' : 'Normal']) };
    return { head: ['Solicitacao', 'Obra', 'Material', 'Valor', 'Status'], rows: current.purchaseRequests.filter(allow).map(item => [item.number, name(current.works, item.workId), item.material, money(item.estimatedAmount), item.status]) };
  }

  function renderReports() {
    const section = document.querySelector('.content');
    if (!section) return;
    const current = state();
    section.innerHTML = `<div class="section-head"><div><h2>Central de relatorios</h2><p>Analises comerciais, financeiras, executivas e operacionais com filtros.</p></div><div class="d-flex gap-2"><button class="btn btn-outline-app" data-report-print>Imprimir</button><button class="btn btn-primary-app" data-report-export>Exportar Excel (CSV)</button></div></div><div class="row g-3 mb-3 report-cards">${Object.entries(reportDefinitions).map(([key, definition], index) => `<div class="col-xl-3 col-md-4 col-6"><button class="card-app report-card ${index === 0 ? 'active' : ''}" data-report="${key}"><i class="bi ${definition[1]}"></i><strong>${definition[0]}</strong><span>Abrir analise</span></button></div>`).join('')}</div><div class="card-app print-document"><div class="card-header report-filters"><div><strong data-report-title>Relatorio de vendas</strong><small>Dados da demonstracao</small></div><label>Empreendimento<select class="form-select" data-report-enterprise><option value="">Todos</option>${current.enterprises.map(item => `<option value="${item.id}">${UI.escapeHtml(item.name)}</option>`).join('')}</select></label></div><div data-report-preview></div></div>`;
    let selected = 'sales';
    const preview = () => {
      const data = reportRows(selected, state(), section.querySelector('[data-report-enterprise]').value);
      section.querySelector('[data-report-title]').textContent = `Relatorio de ${reportDefinitions[selected][0].toLowerCase()}`;
      section.querySelector('[data-report-preview]').innerHTML = data.rows.length ? `<div class="table-responsive"><table class="table table-app"><thead><tr>${data.head.map(value => `<th>${value}</th>`).join('')}</tr></thead><tbody>${data.rows.map(row => `<tr>${row.map(value => `<td>${UI.escapeHtml(value)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>` : '<div class="empty-state"><i class="bi bi-inbox"></i><h3>Nenhum registro encontrado</h3><p>Ajuste o empreendimento selecionado.</p></div>';
    };
    section.querySelectorAll('[data-report]').forEach(button => button.addEventListener('click', () => { selected = button.dataset.report; section.querySelectorAll('[data-report]').forEach(item => item.classList.toggle('active', item === button)); preview(); }));
    section.querySelector('[data-report-enterprise]').addEventListener('change', preview);
    section.querySelector('[data-report-export]').addEventListener('click', () => UI.exportTableCsv(section.querySelector('[data-report-preview] table'), `urbanix-${selected}`));
    section.querySelector('[data-report-print]').addEventListener('click', () => root.print());
    preview();
  }

  async function newUser() {
    const data = await formDialog('Novo usuario', `<div class="col-md-6"><label class="form-label" for="userName">Nome</label><input id="userName" name="name" class="form-control" maxlength="100" required></div><div class="col-md-6"><label class="form-label" for="userEmail">E-mail</label><input id="userEmail" name="email" class="form-control" type="email" required></div><div class="col-md-6"><label class="form-label" for="userPhone">Telefone</label><input id="userPhone" name="phone" class="form-control" required></div><div class="col-md-6"><label class="form-label" for="userRole">Perfil</label><select id="userRole" name="role" class="form-select"><option>Diretoria</option><option>Gerente</option><option>Financeiro</option><option>Engenharia</option><option>Compras</option><option>Corretor</option><option>Cliente</option></select></div><div class="col-12"><label class="form-label" for="userPassword">Senha demonstrativa</label><input id="userPassword" name="password" class="form-control" type="password" minlength="6" required></div>`, 'Criar usuario', 'Usuarios e permissoes');
    if (!data) return;
    const email = data.get('email').trim().toLowerCase();
    if (Store.query('users').some(item => item.email.toLowerCase() === email)) return UI.showToast('Ja existe um usuario com este e-mail.', 'danger');
    const userName = data.get('name').trim();
    Store.create('users', { name: userName, email, phone: data.get('phone').trim(), role: data.get('role'), password: data.get('password'), initials: userName.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase(), active: true });
    UI.showToast('Usuario demonstrativo criado.', 'success');
    renderSettings();
  }

  function renderSettings() {
    const section = document.querySelector('.content');
    if (!section) return;
    const current = state();
    const settings = current.settings;
    const permissions = settings.permissions || { Clientes: ['Ver', 'Criar', 'Editar'], Financeiro: ['Ver'], Obras: ['Ver', 'Criar', 'Editar'], Configuracoes: ['Ver', 'Editar'] };
    section.innerHTML = `<form data-settings-form><div class="section-head"><div><h2>Configuracoes</h2><p>Empresa, regras comerciais, financeiro, usuarios, permissoes e tema.</p></div><button class="btn btn-primary-app" type="submit">Salvar alteracoes</button></div><div class="row g-3 mb-3"><div class="col-xl-7"><div class="card-app"><div class="card-header"><strong>Dados da empresa</strong></div><div class="card-body"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="companyName">Razao social</label><input class="form-control" id="companyName" name="companyName" value="${UI.escapeHtml(settings.companyName || '')}" required></div><div class="col-md-6"><label class="form-label" for="companyCnpj">CNPJ</label><input class="form-control" id="companyCnpj" name="companyCnpj" data-mask="cnpj" value="${UI.escapeHtml(settings.companyCnpj || '00.000.000/0001-00')}" required></div><div class="col-md-6"><label class="form-label" for="companyEmail">E-mail</label><input class="form-control" id="companyEmail" name="companyEmail" type="email" value="${UI.escapeHtml(settings.companyEmail || 'contato@urbanix.com.br')}" required></div><div class="col-md-6"><label class="form-label" for="companyPhone">Telefone</label><input class="form-control" id="companyPhone" name="companyPhone" data-mask="phone" value="${UI.escapeHtml(settings.companyPhone || '(97) 99999-0000')}" required></div><div class="col-12"><label class="form-label" for="companyAddress">Endereco</label><input class="form-control" id="companyAddress" name="companyAddress" value="${UI.escapeHtml(settings.companyAddress || 'Tefe - AM')}" required></div></div></div></div></div><div class="col-xl-5"><div class="card-app h-100"><div class="card-header"><strong>Regras do sistema</strong></div><div class="card-body"><div class="row g-3"><div class="col-6"><label class="form-label" for="finePercent">Multa (%)</label><input class="form-control" id="finePercent" name="finePercent" type="number" min="0" step="0.01" value="${settings.finePercent || 2}"></div><div class="col-6"><label class="form-label" for="interestPercent">Juros mes (%)</label><input class="form-control" id="interestPercent" name="interestPercent" type="number" min="0" step="0.01" value="${settings.interestPercent || 1}"></div><div class="col-6"><label class="form-label" for="reservationHours">Reserva (horas)</label><input class="form-control" id="reservationHours" name="reservationHours" type="number" min="1" value="${settings.reservationHours}"></div><div class="col-6"><label class="form-label" for="proposalDays">Proposta (dias)</label><input class="form-control" id="proposalDays" name="proposalDays" type="number" min="1" value="${settings.proposalDays}"></div><div class="col-6"><label class="form-label" for="commission">Comissao (%)</label><input class="form-control" id="commission" name="defaultCommissionPercent" type="number" min="0" step="0.1" value="${settings.defaultCommissionPercent}"></div><div class="col-6"><label class="form-label" for="theme">Tema</label><select class="form-select" id="theme" name="theme"><option value="light" ${settings.theme === 'light' ? 'selected' : ''}>Claro</option><option value="dark" ${settings.theme === 'dark' ? 'selected' : ''}>Escuro</option></select></div></div></div></div></div></div><div class="row g-3"><div class="col-xl-7"><div class="card-app"><div class="card-header d-flex justify-content-between align-items-center"><strong>Usuarios</strong><button type="button" class="btn btn-sm btn-outline-app" data-new-user>Novo usuario</button></div><div class="table-responsive"><table class="table table-app"><thead><tr><th>Usuario</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Acoes</th></tr></thead><tbody>${current.users.map(user => `<tr><td>${UI.escapeHtml(user.name)}</td><td>${UI.escapeHtml(user.email)}</td><td>${UI.escapeHtml(user.role)}</td><td>${user.active ? badge('active') : badge('blocked')}</td><td><button type="button" class="btn btn-sm btn-outline-app" data-toggle-user="${user.id}">${user.active ? 'Inativar' : 'Ativar'}</button></td></tr>`).join('')}</tbody></table></div></div></div><div class="col-xl-5"><div class="card-app"><div class="card-header"><strong>Matriz de permissoes</strong></div><div class="table-responsive"><table class="table table-app permission-table"><thead><tr><th>Modulo</th><th>Ver</th><th>Criar</th><th>Editar</th><th>Excluir</th></tr></thead><tbody>${['Clientes', 'Financeiro', 'Obras', 'Configuracoes'].map(module => `<tr><td>${module}</td>${['Ver', 'Criar', 'Editar', 'Excluir'].map(action => `<td><input class="form-check-input" type="checkbox" name="permission:${module}:${action}" ${permissions[module]?.includes(action) ? 'checked' : ''} aria-label="${action} ${module}"></td>`).join('')}</tr>`).join('')}</tbody></table></div><div class="card-body pt-0"><small class="text-secondary">Permissoes sao apenas demonstrativas; a autorizacao real devera ser validada no backend.</small></div></div></div></div></form>`;
    const form = section.querySelector('[data-settings-form]');
    form.addEventListener('submit', event => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const data = new FormData(form);
      Store.mutate('settings.updated', entryState => {
        ['companyName', 'companyCnpj', 'companyEmail', 'companyPhone', 'companyAddress', 'theme'].forEach(key => { entryState.settings[key] = data.get(key); });
        ['finePercent', 'interestPercent', 'reservationHours', 'proposalDays', 'defaultCommissionPercent'].forEach(key => { entryState.settings[key] = Number(data.get(key)); });
        entryState.settings.permissions = {};
        ['Clientes', 'Financeiro', 'Obras', 'Configuracoes'].forEach(module => { entryState.settings.permissions[module] = ['Ver', 'Criar', 'Editar', 'Excluir'].filter(action => data.has(`permission:${module}:${action}`)); });
      });
      Urbanix.App?.applyTheme();
      UI.showToast('Configuracoes salvas no navegador.', 'success');
    });
    section.querySelector('[data-new-user]').addEventListener('click', newUser);
    section.querySelectorAll('[data-toggle-user]').forEach(button => button.addEventListener('click', async () => { const user = byId(state().users, button.dataset.toggleUser); if (!user || !await UI.confirmAction({ title: `${user.active ? 'Inativar' : 'Ativar'} ${user.name}?`, message: 'O acesso demonstrativo sera atualizado.', confirmLabel: user.active ? 'Inativar' : 'Ativar', danger: user.active })) return; Store.update('users', user.id, { active: !user.active }); renderSettings(); }));
    UI.applyMasks(form);
  }

  function downloadText(filename, content) {
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = filename; document.body.appendChild(link); link.click(); const url = link.href; link.remove(); root.setTimeout(() => URL.revokeObjectURL(url), 500);
    UI.showToast('Arquivo demonstrativo gerado.', 'success');
  }

  function renderPortal() {
    const section = document.querySelector('.content');
    if (!section) return;
    document.body.classList.add('portal-mode');
    const current = state();
    const params = new URLSearchParams(root.location.search);
    const requested = params.get('customer');
    const contract = current.contracts.find(item => item.customerId === requested) || current.contracts[0];
    const customer = byId(current.customers, contract?.customerId);
    const unit = byId(current.units, contract?.unitId);
    const enterprise = byId(current.enterprises, contract?.enterpriseId);
    const work = current.works.find(item => item.enterpriseId === enterprise?.id);
    const installments = current.installments.filter(item => item.contractId === contract?.id).sort((a, b) => a.number - b.number);
    const paid = installments.filter(item => item.status === 'paid');
    const next = installments.find(item => item.status !== 'paid');
    section.innerHTML = `<div class="portal-toolbar"><div><small>Portal do cliente</small><strong>${UI.escapeHtml(customer?.name || 'Cliente')}</strong></div><div class="portal-toolbar-actions"><a class="btn btn-outline-app" href="index.html"><i class="bi bi-arrow-left me-1"></i>Administrativo</a><label>Visualizar cadastro<select class="form-select" data-portal-customer>${current.contracts.map(item => `<option value="${item.customerId}" ${item.customerId === customer?.id ? 'selected' : ''}>${UI.escapeHtml(name(current.customers, item.customerId))}</option>`).join('')}</select></label></div></div><nav class="portal-nav" aria-label="Navegacao do portal"><button class="active" data-portal-tab="home">Inicio</button><button data-portal-tab="property">Meu imovel</button><button data-portal-tab="finance">Financeiro</button><button data-portal-tab="contract">Contrato</button><button data-portal-tab="documents">Documentos</button><button data-portal-tab="work">Acompanhar obra</button><button data-portal-tab="support">Atendimento</button></nav><div data-portal-view></div>`;
    const view = tab => {
      section.querySelectorAll('[data-portal-tab]').forEach(button => button.classList.toggle('active', button.dataset.portalTab === tab));
      const target = section.querySelector('[data-portal-view]');
      if (tab === 'home') target.innerHTML = `<div class="portal-hero"><div class="small opacity-75">Ola, ${UI.escapeHtml(customer?.name?.split(' ')[0] || 'cliente')}</div><h2 class="mt-1">Seu imovel, pagamentos e obra em um so lugar.</h2><div class="portal-unit"><div class="d-flex justify-content-between"><div><small class="opacity-75">Sua unidade</small><strong class="d-block fs-5">${UI.escapeHtml(enterprise?.name || '')} · ${UI.escapeHtml(unit?.code || '')}</strong></div>${badge(contract?.status)}</div></div></div><div class="row g-3 mt-1"><div class="col-xl-4"><div class="card-app metric"><div class="label">Parcelas pagas</div><div class="value">${paid.length} / ${contract?.installmentCount || 0}</div><div class="delta">Historico financeiro</div></div></div><div class="col-xl-4"><div class="card-app metric gold"><div class="label">Proxima parcela</div><div class="value">${money(next?.amount || 0)}</div><div class="delta">${next ? `Vence em ${UI.formatDate(next.dueDate)}` : 'Contrato quitado'}</div></div></div><div class="col-xl-4"><div class="card-app metric blue"><div class="label">Avanco da obra</div><div class="value">${work?.progress || enterprise?.progress || 0}%</div><div class="delta">Atualizado pela engenharia</div></div></div></div>`;
      if (tab === 'property') target.innerHTML = `<div class="card-app portal-detail"><div class="property-visual"><i class="bi bi-house-check"></i><span>${UI.escapeHtml(unit?.code || '')}</span></div><div><small>${UI.escapeHtml(enterprise?.type || '')} · ${UI.escapeHtml(enterprise?.city || '')}/${UI.escapeHtml(enterprise?.state || '')}</small><h2>${UI.escapeHtml(enterprise?.name || '')}</h2><div class="detail-kpis mt-3"><div><span>Unidade</span><strong>${UI.escapeHtml(unit?.code || '')}</strong></div><div><span>Area</span><strong>${unit?.area || 0} m2</strong></div><div><span>Situacao</span><strong>${badge(unit?.status)}</strong></div><div><span>Contrato</span><strong>${contract?.number || '—'}</strong></div></div></div></div>`;
      if (tab === 'finance') target.innerHTML = `<div class="card-app"><div class="card-header"><strong>Minhas parcelas</strong></div><div class="installment-grid p-3">${installments.map(item => `<button type="button" class="installment-card" data-portal-installment="${item.id}"><small>${String(item.number).padStart(2, '0')}/${contract?.installmentCount}</small><strong>${money(item.amount)}</strong>${badge(item.status)}</button>`).join('')}</div></div>`;
      if (tab === 'contract') target.innerHTML = `<div class="card-app document-view print-document"><small>URBANIX EMPREENDIMENTOS</small><h2 class="mt-2">Contrato ${contract?.number || ''}</h2><p>Comprador: <strong>${UI.escapeHtml(customer?.name || '')}</strong></p><p>Imovel: <strong>${UI.escapeHtml(enterprise?.name || '')} · ${UI.escapeHtml(unit?.code || '')}</strong></p><div class="detail-kpis"><div><span>Valor contratado</span><strong>${money(contract?.totalAmount || 0)}</strong></div><div><span>Entrada</span><strong>${money(contract?.downPayment || 0)}</strong></div><div><span>Saldo</span><strong>${money((contract?.totalAmount || 0) - (contract?.paidAmount || 0))}</strong></div><div><span>Assinatura</span><strong>${UI.formatDate(contract?.signedAt)}</strong></div></div><button class="btn btn-primary-app mt-3 no-print" data-contract-print>Imprimir contrato</button></div>`;
      if (tab === 'documents') target.innerHTML = `<div class="row g-3">${current.documents.filter(item => item.customerId === customer?.id || item.contractId === contract?.id).concat([{ id: 'statement', name: 'Extrato financeiro', category: 'finance' }]).map(item => `<div class="col-md-6"><button class="doc-card w-100 text-start" data-download-document="${item.id}"><div class="doc-icon"><i class="bi bi-file-earmark-pdf"></i></div><div class="flex-grow-1"><strong>${UI.escapeHtml(item.name)}</strong><div class="object-sub">Disponivel para download demonstrativo</div></div><i class="bi bi-download"></i></button></div>`).join('')}</div>`;
      if (tab === 'work') { const services = current.services.filter(item => item.workId === work?.id); target.innerHTML = `<div class="card-app"><div class="card-header"><strong>Acompanhamento da obra</strong></div><div class="card-body"><div class="work-progress-list">${services.map(item => `<div><span><strong>${UI.escapeHtml(item.name)}</strong><small>${item.progress}% concluido</small></span><div class="progress progress-thin"><div class="progress-bar" style="width:${item.progress}%"></div></div></div>`).join('')}</div><div class="photo-gallery mt-4"><div><i class="bi bi-image"></i><span>Pavimentacao</span></div><div><i class="bi bi-image"></i><span>Rede eletrica</span></div><div><i class="bi bi-image"></i><span>Infraestrutura</span></div></div></div></div>`; }
      if (tab === 'support') target.innerHTML = `<div class="card-app"><div class="card-header"><strong>Fale com a Urbanix</strong></div><div class="card-body"><form class="row g-3" data-support-form><div class="col-md-6"><label class="form-label" for="supportSubject">Assunto</label><select id="supportSubject" name="subject" class="form-select"><option>Financeiro</option><option>Contrato</option><option>Obra</option><option>Atualizacao cadastral</option></select></div><div class="col-12"><label class="form-label" for="supportMessage">Mensagem</label><textarea id="supportMessage" name="message" class="form-control" rows="4" maxlength="500" required></textarea></div><div class="col-12"><button class="btn btn-primary-app" type="submit">Enviar atendimento</button></div></form></div></div>`;
      target.querySelectorAll('[data-portal-installment]').forEach(button => button.addEventListener('click', () => { const item = byId(installments, button.dataset.portalInstallment); UI.openModal({ eyebrow: `Parcela ${item.number}`, title: money(item.amount), content: `Vencimento: ${UI.formatDate(item.dueDate)} · Situacao: ${item.status}` }); }));
      target.querySelector('[data-contract-print]')?.addEventListener('click', () => root.print());
      target.querySelectorAll('[data-download-document]').forEach(button => button.addEventListener('click', () => downloadText(`urbanix-${button.dataset.downloadDocument}.txt`, `Documento demonstrativo Urbanix\nCliente: ${customer?.name}\nContrato: ${contract?.number}`)));
      target.querySelector('[data-support-form]')?.addEventListener('submit', event => { event.preventDefault(); const data = new FormData(event.currentTarget); Store.mutate('support.created', entryState => { entryState.supportRequests ||= []; entryState.supportRequests.push({ id: Store.generateId('support'), customerId: customer.id, subject: data.get('subject'), message: data.get('message').trim(), status: 'open', createdAt: new Date().toISOString() }); }); event.currentTarget.reset(); UI.showToast('Solicitacao enviada. A equipe retornara pelo canal cadastrado.', 'success'); });
    };
    section.querySelectorAll('[data-portal-tab]').forEach(button => button.addEventListener('click', () => view(button.dataset.portalTab)));
    section.querySelector('[data-portal-customer]').addEventListener('change', event => root.location.assign(`portal-cliente.html?customer=${encodeURIComponent(event.target.value)}`));
    view('home');
  }

  function init() {
    const page = document.body.dataset.page;
    if (page === 'dashboard') renderDashboard();
    if (page === 'relatorios') renderReports();
    if (page === 'configuracoes') renderSettings();
    if (page === 'portal-cliente') renderPortal();
  }

  document.addEventListener('DOMContentLoaded', init);
  Urbanix.Management = Object.freeze({ init, renderDashboard, renderReports, renderSettings, renderPortal });
})(window);
