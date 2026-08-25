(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const Store = Urbanix.Store;
  const UI = Urbanix.UI;
  const money = value => UI.formatMoney(value, { maximumFractionDigits: 2 });
  const page = () => document.body.dataset.page;
  const state = () => Store.getState();
  const byId = (items, id) => items.find(item => item.id === id);
  const label = (items, id, key) => byId(items, id)?.[key || 'name'] || 'Nao informado';
  const sum = items => items.reduce((total, item) => total + Number(item.amount || 0), 0);

  function statusBadge(status) {
    const values = {
      active: ['Ativo', 'soft-success'], paid: ['Pago', 'soft-success'], pending: ['A vencer', 'soft-info'],
      overdue: ['Vencido', 'soft-danger'], cancelled: ['Cancelado', 'soft-muted'],
      terminated: ['Distratado', 'soft-danger'], awaiting: ['Aguardando', 'soft-warning']
    };
    const value = values[status] || [status || 'Pendente', 'soft-muted'];
    return `<span class="badge-soft ${value[1]}">${value[0]}</span>`;
  }

  async function formDialog(title, fields, submitLabel) {
    const form = document.createElement('form');
    form.className = 'row g-3';
    form.innerHTML = fields;
    const promise = UI.openModal({
      eyebrow: 'Operacao financeira', title, content: form,
      actions: [{ label: 'Cancelar', variant: 'outline', value: null }, { label: submitLabel || 'Salvar', variant: 'primary', value: 'submit' }]
    });
    UI.applyMasks(form);
    const submit = form.closest('.urbanix-dialog').querySelector('footer .btn-primary-app');
    submit.addEventListener('click', event => {
      if (form.checkValidity()) return;
      event.stopImmediatePropagation();
      form.reportValidity();
    }, true);
    form.addEventListener('submit', event => { event.preventDefault(); submit.click(); });
    return (await promise) === 'submit' ? new FormData(form) : null;
  }

  function audit(entryState, action, entity, entityId, detail) {
    const userId = entryState.session?.userId || 'user-admin';
    entryState.audits.push({ id: Store.generateId('audit'), userId, action, entity, entityId, detail, createdAt: new Date().toISOString() });
  }

  function recomputeContract(entryState, contractId) {
    const contract = byId(entryState.contracts, contractId);
    if (!contract) return;
    contract.paidAmount = sum(entryState.payments.filter(item => item.contractId === contractId));
    if (contract.paidAmount >= contract.totalAmount) contract.status = 'paid';
  }

  async function registerReceipt(receivableId) {
    const current = state();
    const receivable = byId(current.accountsReceivable, receivableId);
    if (!receivable || receivable.status === 'paid') {
      UI.showToast('Este titulo ja foi recebido ou nao existe.', 'danger');
      return false;
    }
    const data = await formDialog('Registrar recebimento', `
      <div class="col-md-6"><label class="form-label" for="receiptValue">Valor recebido</label><input id="receiptValue" name="amount" class="form-control" type="number" min="0.01" step="0.01" value="${receivable.amount}" required></div>
      <div class="col-md-6"><label class="form-label" for="receiptDate">Data</label><input id="receiptDate" name="date" class="form-control" type="date" value="${new Date().toISOString().slice(0, 10)}" required></div>
      <div class="col-md-6"><label class="form-label" for="receiptMethod">Forma de pagamento</label><select id="receiptMethod" name="method" class="form-select" required><option value="pix">Pix</option><option value="boleto">Boleto</option><option value="transferencia">Transferencia</option><option value="dinheiro">Dinheiro</option></select></div>
      <div class="col-md-6"><label class="form-label" for="receiptBank">Banco / conta</label><input id="receiptBank" name="bank" class="form-control" maxlength="80" value="Conta principal"></div>
      <div class="col-12"><label class="form-label" for="receiptNote">Observacao</label><textarea id="receiptNote" name="note" class="form-control" rows="2" maxlength="240"></textarea></div>`, 'Confirmar recebimento');
    if (!data) return false;
    const amount = Number(data.get('amount'));
    if (!Number.isFinite(amount) || amount <= 0) return UI.showToast('Informe um valor valido.', 'danger');
    const confirmed = await UI.confirmAction({ title: `Confirmar recebimento de ${money(amount)}?`, message: 'A parcela sera marcada como paga e o saldo do contrato sera atualizado.', confirmLabel: 'Receber' });
    if (!confirmed) return false;
    Store.mutate('receivable.paid', entryState => {
      const target = byId(entryState.accountsReceivable, receivableId);
      if (!target || target.status === 'paid') throw new Error('Recebimento duplicado bloqueado.');
      const installment = byId(entryState.installments, target.installmentId);
      const paymentId = Store.generateId('payment');
      entryState.payments.push({ id: paymentId, installmentId: target.installmentId, contractId: target.contractId, amount, paidAt: `${data.get('date')}T12:00:00.000Z`, method: data.get('method'), bank: data.get('bank'), note: data.get('note') });
      target.status = 'paid';
      target.paidAmount = amount;
      target.paidAt = data.get('date');
      if (installment) { installment.status = 'paid'; installment.paymentId = paymentId; }
      recomputeContract(entryState, target.contractId);
      audit(entryState, 'receivable.paid', 'accountsReceivable', target.id, `Recebimento de ${money(amount)} registrado`);
    });
    UI.showToast('Recebimento registrado e contrato atualizado.', 'success');
    return true;
  }

  async function changeDueDate(receivableId) {
    const current = state();
    const item = byId(current.accountsReceivable, receivableId);
    if (!item || item.status === 'paid') return UI.showToast('Titulos pagos nao podem ter o vencimento alterado.', 'danger');
    const data = await formDialog('Alterar vencimento', `<div class="col-12"><label class="form-label" for="newDueDate">Novo vencimento</label><input id="newDueDate" name="dueDate" class="form-control" type="date" value="${item.dueDate}" required></div>`, 'Alterar');
    if (!data) return;
    Store.mutate('receivable.due-date', entryState => {
      const target = byId(entryState.accountsReceivable, receivableId);
      const installment = byId(entryState.installments, target.installmentId);
      target.dueDate = data.get('dueDate');
      target.status = 'pending';
      if (installment) { installment.dueDate = target.dueDate; installment.status = 'pending'; }
      audit(entryState, 'receivable.due-date', 'accountsReceivable', target.id, `Vencimento alterado para ${target.dueDate}`);
    });
    UI.showToast('Vencimento atualizado.', 'success');
  }

  function contractDetail(contractId) {
    const current = state();
    const contract = byId(current.contracts, contractId);
    if (!contract) return;
    const customer = label(current.customers, contract.customerId);
    const unit = label(current.units, contract.unitId, 'code');
    const enterprise = label(current.enterprises, contract.enterpriseId);
    const installments = current.installments.filter(item => item.contractId === contract.id).sort((a, b) => a.number - b.number);
    const overdue = installments.filter(item => item.status === 'overdue').length;
    const paid = installments.filter(item => item.status === 'paid').length;
    const content = document.createElement('div');
    content.innerHTML = `<div class="detail-hero"><div><small>${UI.escapeHtml(enterprise)}</small><h3>${UI.escapeHtml(customer)}</h3><p>Unidade ${UI.escapeHtml(unit)} · assinado em ${UI.formatDate(contract.signedAt)}</p></div>${statusBadge(contract.status)}</div>
      <div class="detail-kpis"><div><span>Valor total</span><strong>${money(contract.totalAmount)}</strong></div><div><span>Pago</span><strong>${money(contract.paidAmount)}</strong></div><div><span>Saldo</span><strong>${money(contract.totalAmount - contract.paidAmount)}</strong></div><div><span>Parcelas</span><strong>${paid} pagas · ${overdue} vencidas</strong></div></div>
      <div class="installment-grid mt-3">${installments.slice(0, 18).map(item => `<button type="button" data-installment="${item.id}" class="installment-card"><small>${String(item.number).padStart(2, '0')}/${contract.installmentCount}</small><strong>${money(item.amount)}</strong>${statusBadge(item.status)}</button>`).join('')}</div>`;
    UI.openModal({ eyebrow: contract.number, title: 'Detalhes do contrato', content, actions: [{ label: 'Imprimir', variant: 'outline', value: 'print' }, { label: 'Fechar', variant: 'primary', value: null }] }).then(result => { if (result === 'print') root.print(); });
    content.querySelectorAll('[data-installment]').forEach(button => button.addEventListener('click', () => installmentDetail(button.dataset.installment)));
  }

  function installmentDetail(installmentId) {
    const current = state();
    const installment = byId(current.installments, installmentId);
    const receivable = current.accountsReceivable.find(item => item.installmentId === installmentId);
    if (!installment || !receivable) return;
    const content = document.createElement('div');
    const lateDays = Math.max(0, Math.floor((Date.now() - new Date(`${installment.dueDate}T12:00:00`).getTime()) / 86400000));
    const fine = installment.status === 'overdue' ? installment.amount * 0.02 : 0;
    const interest = installment.status === 'overdue' ? installment.amount * 0.00033 * lateDays : 0;
    content.innerHTML = `<div class="detail-kpis"><div><span>Vencimento</span><strong>${UI.formatDate(installment.dueDate)}</strong></div><div><span>Original</span><strong>${money(installment.amount)}</strong></div><div><span>Juros e multa</span><strong>${money(fine + interest)}</strong></div><div><span>Atualizado</span><strong>${money(installment.amount + fine + interest)}</strong></div></div><div class="d-flex flex-wrap gap-2 mt-3">${installment.status !== 'paid' ? `<button class="btn btn-primary-app" data-receive>Registrar pagamento</button><button class="btn btn-outline-app" data-due>Alterar vencimento</button>` : '<button class="btn btn-outline-app" data-receipt>Emitir comprovante</button>'}</div>`;
    UI.openModal({ eyebrow: `Parcela ${installment.number}`, title: 'Detalhes da parcela', content });
    content.querySelector('[data-receive]')?.addEventListener('click', async () => { if (await registerReceipt(receivable.id)) root.location.reload(); });
    content.querySelector('[data-due]')?.addEventListener('click', async () => { await changeDueDate(receivable.id); root.location.reload(); });
    content.querySelector('[data-receipt]')?.addEventListener('click', () => root.print());
  }

  function renderContracts() {
    const section = document.querySelector('.content');
    if (!section) return;
    const current = state();
    const paidTotal = sum(current.payments);
    const overdue = current.installments.filter(item => item.status === 'overdue').length;
    section.innerHTML = `<div class="section-head"><div><h2>Contratos</h2><p>Carteira contratual, parcelas, pagamentos, aditivos e historico.</p></div><button class="btn btn-primary-app" data-new-contract>Novo contrato</button></div>
      <div class="row g-3 mb-3"><div class="col-md-4"><div class="card-app metric"><div class="label">Contratos ativos</div><div class="value">${current.contracts.filter(item => item.status === 'active').length}</div><div class="delta">Carteira demonstrativa</div></div></div><div class="col-md-4"><div class="card-app metric blue"><div class="label">Total recebido</div><div class="value">${money(paidTotal)}</div><div class="delta">Pagamentos registrados</div></div></div><div class="col-md-4"><div class="card-app metric red"><div class="label">Parcelas vencidas</div><div class="value">${overdue}</div><div class="delta">Exigem acompanhamento</div></div></div></div>
      <div class="card-app"><div class="table-responsive"><table class="table table-app"><thead><tr><th>Contrato</th><th>Cliente</th><th>Empreendimento / unidade</th><th>Valor</th><th>Pago</th><th>Situacao</th><th>Acoes</th></tr></thead><tbody>${current.contracts.map(contract => `<tr><td><strong>${contract.number}</strong></td><td>${UI.escapeHtml(label(current.customers, contract.customerId))}</td><td>${UI.escapeHtml(label(current.enterprises, contract.enterpriseId))} · ${UI.escapeHtml(label(current.units, contract.unitId, 'code'))}</td><td>${money(contract.totalAmount)}</td><td>${money(contract.paidAmount)}</td><td>${statusBadge(contract.status)}</td><td><button class="btn btn-sm btn-outline-app" data-contract="${contract.id}">Abrir</button></td></tr>`).join('')}</tbody></table></div></div>`;
    section.querySelectorAll('[data-contract]').forEach(button => button.addEventListener('click', () => contractDetail(button.dataset.contract)));
    section.querySelector('[data-new-contract]').addEventListener('click', createContract);
    section.querySelectorAll('.table-app').forEach(UI.enhanceTable);
  }

  async function createContract() {
    const current = state();
    const available = current.sales.filter(sale => !sale.contractId && sale.status === 'active');
    if (!available.length) return UI.showToast('Todas as vendas ativas ja possuem contrato. Gere uma nova venda no modulo comercial.', 'info', 'Nenhuma venda pendente');
    const options = available.map(sale => `<option value="${sale.id}">${sale.number} · ${UI.escapeHtml(label(current.customers, sale.customerId))}</option>`).join('');
    const data = await formDialog('Gerar contrato', `<div class="col-12"><label class="form-label" for="contractSale">Venda</label><select id="contractSale" name="saleId" class="form-select" required>${options}</select></div><div class="col-md-6"><label class="form-label" for="contractInstallments">Quantidade de parcelas</label><input id="contractInstallments" name="count" class="form-control" type="number" min="1" max="240" value="120" required></div><div class="col-md-6"><label class="form-label" for="contractFirstDue">Primeiro vencimento</label><input id="contractFirstDue" name="dueDate" class="form-control" type="date" required></div>`, 'Gerar contrato');
    if (!data) return;
    Store.mutate('contract.created', entryState => {
      const sale = byId(entryState.sales, data.get('saleId'));
      if (!sale || sale.contractId || entryState.contracts.some(item => item.saleId === sale.id)) throw new Error('Contrato duplicado bloqueado.');
      const contractId = Store.generateId('contract');
      const count = Number(data.get('count'));
      const amount = Math.max(0, (sale.amount || 0) / count);
      const number = `CTR-${String(116 + entryState.contracts.length).padStart(6, '0')}`;
      entryState.contracts.push({ id: contractId, number, saleId: sale.id, customerId: sale.customerId, enterpriseId: sale.enterpriseId, unitId: sale.unitId, totalAmount: sale.amount, downPayment: 0, paidAmount: 0, installmentCount: count, signedAt: new Date().toISOString().slice(0, 10), status: 'active' });
      sale.contractId = contractId;
      for (let index = 0; index < count; index += 1) {
        const due = new Date(`${data.get('dueDate')}T12:00:00`); due.setMonth(due.getMonth() + index);
        const installmentId = Store.generateId('installment');
        entryState.installments.push({ id: installmentId, contractId, number: index + 1, dueDate: due.toISOString().slice(0, 10), amount, status: 'pending', paymentId: null });
        entryState.accountsReceivable.push({ id: Store.generateId('receivable'), installmentId, contractId, dueDate: due.toISOString().slice(0, 10), amount, status: 'pending' });
      }
      audit(entryState, 'contract.created', 'contracts', contractId, `Contrato ${number} gerado`);
    });
    UI.showToast('Contrato e parcelas gerados.', 'success');
    renderContracts();
  }

  function renderFinance() {
    const section = document.querySelector('.content');
    if (!section) return;
    const current = state();
    const received = sum(current.payments);
    const receivable = sum(current.accountsReceivable.filter(item => item.status !== 'paid'));
    const payable = sum(current.accountsPayable.filter(item => item.status !== 'paid' && item.status !== 'cancelled'));
    const overdue = sum(current.accountsReceivable.filter(item => item.status === 'overdue'));
    section.innerHTML = `<div class="section-head"><div><h2>Financeiro</h2><p>Recebimentos, pagamentos, fluxo de caixa, inadimplencia e comissoes.</p></div><div class="d-flex gap-2"><button class="btn btn-outline-app" data-export>Exportar</button><button class="btn btn-primary-app" data-new-payable>Novo lancamento</button></div></div>
      <div class="row g-3 mb-3"><div class="col-xl-3 col-md-6"><div class="card-app metric"><div class="label">Saldo realizado</div><div class="value">${money(received - sum(current.accountsPayable.filter(item => item.status === 'paid')))}</div><div class="delta">Entradas menos saidas</div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric blue"><div class="label">A receber</div><div class="value">${money(receivable)}</div><div class="delta">Carteira futura</div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric gold"><div class="label">A pagar</div><div class="value">${money(payable)}</div><div class="delta">Compromissos abertos</div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric red"><div class="label">Inadimplencia</div><div class="value">${money(overdue)}</div><div class="delta">Titulos vencidos</div></div></div></div>
      <div class="card-app mb-3"><div class="card-header tabs-row" role="tablist"><button class="active" data-fin-tab="receivable">Contas a receber</button><button data-fin-tab="payable">Contas a pagar</button><button data-fin-tab="cash">Fluxo de caixa</button><button data-fin-tab="commission">Comissoes</button></div><div data-fin-content></div></div>`;
    const show = tab => {
      section.querySelectorAll('[data-fin-tab]').forEach(button => button.classList.toggle('active', button.dataset.finTab === tab));
      const target = section.querySelector('[data-fin-content]');
      if (tab === 'receivable') target.innerHTML = `<div class="table-responsive"><table class="table table-app"><thead><tr><th>Contrato</th><th>Cliente</th><th>Vencimento</th><th>Valor</th><th>Situacao</th><th>Acoes</th></tr></thead><tbody>${current.accountsReceivable.map(item => { const contract = byId(current.contracts, item.contractId); return `<tr><td>${contract?.number || '—'}</td><td>${UI.escapeHtml(label(current.customers, contract?.customerId))}</td><td>${UI.formatDate(item.dueDate)}</td><td>${money(item.amount)}</td><td>${statusBadge(item.status)}</td><td>${item.status !== 'paid' ? `<button class="btn btn-sm btn-primary-app" data-receivable="${item.id}">Receber</button> <button class="btn btn-sm btn-outline-app" data-change-due="${item.id}">Vencimento</button>` : '<button class="btn btn-sm btn-outline-app" data-print>Comprovante</button>'}</td></tr>`; }).join('')}</tbody></table></div>`;
      if (tab === 'payable') target.innerHTML = `<div class="table-responsive"><table class="table table-app"><thead><tr><th>Fornecedor</th><th>Descricao</th><th>Obra</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Acoes</th></tr></thead><tbody>${current.accountsPayable.map(item => `<tr><td>${UI.escapeHtml(label(current.suppliers, item.supplierId))}</td><td>${UI.escapeHtml(item.description)}</td><td>${UI.escapeHtml(label(current.works, item.workId))}</td><td>${UI.formatDate(item.dueDate)}</td><td>${money(item.amount)}</td><td>${statusBadge(item.status)}</td><td>${item.status === 'paid' ? '—' : `<button class="btn btn-sm btn-primary-app" data-payable="${item.id}">Pagar</button>`}</td></tr>`).join('')}</tbody></table></div>`;
      if (tab === 'cash') { const movements = current.payments.map(item => ({ date: item.paidAt, description: `Recebimento ${label(current.contracts, item.contractId, 'number')}`, input: item.amount, output: 0 })).concat(current.accountsPayable.filter(item => item.status === 'paid').map(item => ({ date: item.paidAt, description: item.description, input: 0, output: item.amount }))).sort((a, b) => a.date.localeCompare(b.date)); let balance = 0; target.innerHTML = `<div class="p-3"><div class="chart-wrap"><canvas id="cashChart"></canvas></div></div><div class="table-responsive"><table class="table table-app"><thead><tr><th>Data</th><th>Descricao</th><th>Entrada</th><th>Saida</th><th>Saldo</th></tr></thead><tbody>${movements.map(item => { balance += item.input - item.output; return `<tr><td>${UI.formatDate(item.date)}</td><td>${UI.escapeHtml(item.description)}</td><td>${item.input ? money(item.input) : '—'}</td><td>${item.output ? money(item.output) : '—'}</td><td><strong>${money(balance)}</strong></td></tr>`; }).join('')}</tbody></table></div>`; }
      if (tab === 'commission') target.innerHTML = `<div class="table-responsive"><table class="table table-app"><thead><tr><th>Corretor</th><th>Vendas</th><th>Valor vendido</th><th>Percentual</th><th>Comissao</th><th>Situacao</th></tr></thead><tbody>${current.brokers.map(broker => { const sales = current.sales.filter(item => item.brokerId === broker.id && item.status === 'active'); const amount = sum(sales); return `<tr><td>${UI.escapeHtml(broker.name)}</td><td>${sales.length}</td><td>${money(amount)}</td><td>${broker.commissionPercent}%</td><td>${money(amount * broker.commissionPercent / 100)}</td><td>${statusBadge('pending')}</td></tr>`; }).join('')}</tbody></table></div>`;
      if (tab === 'cash' && root.Chart) {
        const canvas = target.querySelector('#cashChart');
        new root.Chart(canvas, { type: 'line', data: { labels: ['Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago'], datasets: [{ label: 'Entradas', data: [820, 940, 1020, 910, 1180, 1320], borderColor: '#0f6c5c', backgroundColor: 'rgba(15,108,92,.1)', fill: true, tension: .35 }, { label: 'Saidas', data: [540, 610, 650, 690, 760, 830], borderColor: '#c23a3a', tension: .35 }] }, options: { responsive: true, maintainAspectRatio: false } });
      }
      target.querySelectorAll('.table-app').forEach(UI.enhanceTable);
      target.querySelectorAll('[data-receivable]').forEach(button => button.addEventListener('click', async () => { if (await registerReceipt(button.dataset.receivable)) renderFinance(); }));
      target.querySelectorAll('[data-change-due]').forEach(button => button.addEventListener('click', async () => { await changeDueDate(button.dataset.changeDue); renderFinance(); }));
      target.querySelectorAll('[data-payable]').forEach(button => button.addEventListener('click', () => payPayable(button.dataset.payable)));
      target.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => root.print()));
    };
    section.querySelectorAll('[data-fin-tab]').forEach(button => button.addEventListener('click', () => show(button.dataset.finTab)));
    section.querySelector('[data-new-payable]').addEventListener('click', newPayable);
    section.querySelector('[data-export]').addEventListener('click', () => UI.exportTableCsv(section.querySelector('.table-app'), 'urbanix-financeiro'));
    show(new URLSearchParams(root.location.search).get('tab') || 'receivable');
  }

  async function newPayable() {
    const current = state();
    const data = await formDialog('Novo lancamento a pagar', `<div class="col-md-6"><label class="form-label" for="paySupplier">Fornecedor</label><select id="paySupplier" name="supplierId" class="form-select" required>${current.suppliers.map(item => `<option value="${item.id}">${UI.escapeHtml(item.name)}</option>`).join('')}</select></div><div class="col-md-6"><label class="form-label" for="payWork">Obra</label><select id="payWork" name="workId" class="form-select" required>${current.works.map(item => `<option value="${item.id}">${UI.escapeHtml(item.name)}</option>`).join('')}</select></div><div class="col-12"><label class="form-label" for="payDescription">Descricao</label><input id="payDescription" name="description" class="form-control" maxlength="120" required></div><div class="col-md-6"><label class="form-label" for="payDue">Vencimento</label><input id="payDue" name="dueDate" class="form-control" type="date" required></div><div class="col-md-6"><label class="form-label" for="payValue">Valor</label><input id="payValue" name="amount" class="form-control" type="number" min="0.01" step="0.01" required></div>`, 'Cadastrar');
    if (!data) return;
    Store.create('accountsPayable', { supplierId: data.get('supplierId'), workId: data.get('workId'), description: data.get('description').trim(), dueDate: data.get('dueDate'), amount: Number(data.get('amount')), status: 'pending' });
    UI.showToast('Conta a pagar cadastrada.', 'success');
    renderFinance();
  }

  async function payPayable(id) {
    const current = state();
    const item = byId(current.accountsPayable, id);
    if (!item || item.status === 'paid') return;
    if (!await UI.confirmAction({ title: `Pagar ${money(item.amount)}?`, message: item.description, confirmLabel: 'Registrar pagamento' })) return;
    Store.mutate('payable.paid', entryState => {
      const target = byId(entryState.accountsPayable, id);
      if (target.status === 'paid') throw new Error('Pagamento duplicado bloqueado.');
      target.status = 'paid'; target.paidAt = new Date().toISOString();
      const work = byId(entryState.works, target.workId); if (work) work.executedCost += Number(target.amount || 0);
      audit(entryState, 'payable.paid', 'accountsPayable', id, `Pagamento de ${money(target.amount)} registrado`);
    });
    UI.showToast('Pagamento registrado e custo da obra atualizado.', 'success');
    renderFinance();
  }

  function init() {
    if (page() === 'contratos') renderContracts();
    if (page() === 'financeiro') renderFinance();
  }

  document.addEventListener('DOMContentLoaded', init);
  Urbanix.Finance = Object.freeze({ init, renderContracts, renderFinance, registerReceipt });
})(window);
