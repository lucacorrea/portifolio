(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const Store = Urbanix.Store;
  const UI = Urbanix.UI;
  const STAGES = [
    ['new', 'Novos leads'], ['contact', 'Contato'], ['qualification', 'Qualificação'],
    ['visit', 'Visita'], ['proposal', 'Proposta'], ['reservation', 'Reserva'], ['sale', 'Venda']
  ];
  let inventoryListenerBound = false;
  const STATUS = {
    active: 'Ativo', prospect: 'Prospect', negotiation: 'Em negociação', sent: 'Enviada',
    approved: 'Aprovada', rejected: 'Recusada', converted: 'Convertida', released: 'Liberada',
    expired: 'Expirada', cancelled: 'Cancelada'
  };
  const esc = value => UI.escapeHtml(value);
  const state = () => Store.getState();
  const rel = (data, collection, id, key) => data[collection].find(item => item.id === id)?.[key || 'name'] || '—';
  const normalizeKey = value => UI.normalize(value).replace(/\D/g, '') || UI.normalize(value);
  const currentUserId = data => data.session?.userId || 'user-admin';
  const optionList = (items, selected, label, placeholder) => `<option value="">${esc(placeholder || 'Selecione')}</option>${items.map(item => `<option value="${esc(item.id)}" ${item.id === selected ? 'selected' : ''}>${esc(label(item))}</option>`).join('')}`;
  const audit = (data, action, entity, entityId, detail) => data.audits.push({ id: Store.generateId('audit'), userId: currentUserId(data), action, entity, entityId, detail, createdAt: new Date().toISOString() });
  const notify = (data, title, detail, href) => data.notifications.push({ id: Store.generateId('notification'), title, detail, href, read: false, createdAt: new Date().toISOString() });
  const closeDialog = node => node.closest('.urbanix-dialog')?.querySelector('[data-dialog-close]')?.click();
  const badge = status => `<span class="badge-soft ${status === 'approved' || status === 'active' || status === 'converted' ? 'soft-success' : status === 'rejected' || status === 'expired' ? 'soft-danger' : status === 'sent' ? 'soft-info' : 'soft-warning'}">${esc(STATUS[status] || status)}</span>`;
  const futureDate = days => { const date = new Date(); date.setDate(date.getDate() + days); return date.toISOString().slice(0, 10); };
  const sequence = (items, prefix, pad) => { let index = items.length + 1; while (items.some(item => item.number === `${prefix}${String(index).padStart(pad || 4, '0')}`)) index += 1; return `${prefix}${String(index).padStart(pad || 4, '0')}`; };

  function mutate(action, work) {
    return Store.mutate(action, work).result;
  }

  function convertLead(leadId) {
    return mutate('lead.converted', data => {
      const lead = data.leads.find(item => item.id === leadId);
      if (!lead) throw new Error('Lead não encontrado.');
      const linked = lead.customerId && data.customers.find(item => item.id === lead.customerId);
      if (linked) return linked;
      const cpf = normalizeKey(lead.cpf);
      const email = UI.normalize(lead.email);
      let customer = data.customers.find(item => (cpf && normalizeKey(item.cpf) === cpf) || (email && UI.normalize(item.email) === email));
      if (!customer) {
        customer = { id: Store.generateId('customer'), name: lead.name.trim(), cpf: lead.cpf || '', phone: lead.phone || '', email: lead.email || '', brokerId: lead.brokerId || '', status: 'prospect', createdAt: new Date().toISOString() };
        data.customers.push(customer);
      }
      lead.customerId = customer.id;
      audit(data, 'lead.converted', 'lead', lead.id, `Lead vinculado ao cliente ${customer.name}`);
      notify(data, 'Lead convertido em cliente', customer.name, 'clientes.html');
      return customer;
    });
  }

  function expireReservations() {
    const due = state().reservations.filter(item => item.status === 'active' && new Date(item.expiresAt).getTime() <= Date.now());
    if (!due.length) return 0;
    return mutate('reservations.expired', data => {
      let count = 0;
      data.reservations.forEach(reservation => {
        if (reservation.status !== 'active' || new Date(reservation.expiresAt).getTime() > Date.now()) return;
        reservation.status = 'expired';
        const unit = data.units.find(item => item.id === reservation.unitId);
        if (unit?.reservationId === reservation.id && unit.status === 'reserved') Object.assign(unit, { status: 'available', reservationId: null });
        audit(data, 'reservation.expired', 'reservation', reservation.id, 'Reserva expirada e unidade liberada com segurança');
        count += 1;
      });
      if (count) notify(data, 'Reservas expiradas', `${count} unidade(s) liberada(s)`, 'reservas.html');
      return count;
    });
  }

  function createReservation(values) {
    expireReservations();
    return mutate('reservation.created', data => {
      const customer = data.customers.find(item => item.id === values.customerId);
      const unit = data.units.find(item => item.id === values.unitId);
      const proposal = values.proposalId ? data.proposals.find(item => item.id === values.proposalId) : null;
      if (!customer || !unit) throw new Error('Cliente ou unidade inválidos.');
      if (proposal && proposal.status !== 'approved') throw new Error('A proposta precisa estar aprovada para reservar.');
      const existing = data.reservations.find(item => item.status === 'active' && ((proposal && item.proposalId === proposal.id) || item.unitId === unit.id));
      if (existing && existing.customerId === customer.id && existing.unitId === unit.id && (!proposal || !existing.proposalId || existing.proposalId === proposal.id)) { if (proposal && !existing.proposalId) { existing.proposalId = proposal.id; audit(data, 'reservation.proposal-linked', 'reservation', existing.id, `Reserva vinculada à ${proposal.number}`); } return existing; }
      if (existing || unit.status !== 'available') throw new Error('A unidade não está disponível para reserva.');
      if (proposal && (proposal.customerId !== customer.id || proposal.unitId !== unit.id)) throw new Error('A proposta não corresponde ao cliente e à unidade.');
      const now = new Date();
      const hours = Math.max(1, Math.min(168, Number(values.hours) || Number(data.settings.reservationHours) || 48));
      const reservation = { id: Store.generateId('reservation'), customerId: customer.id, enterpriseId: unit.enterpriseId, unitId: unit.id, brokerId: values.brokerId || proposal?.brokerId || customer.brokerId || '', proposalId: proposal?.id || null, status: 'active', createdAt: now.toISOString(), expiresAt: new Date(now.getTime() + hours * 3600000).toISOString() };
      data.reservations.push(reservation);
      Object.assign(unit, { status: 'reserved', reservationId: reservation.id });
      if (proposal && proposal.status !== 'converted') proposal.status = 'approved';
      audit(data, 'reservation.created', 'reservation', reservation.id, `Unidade ${unit.code} reservada para ${customer.name}`);
      notify(data, 'Nova reserva criada', `${unit.code} · ${customer.name}`, 'reservas.html');
      return reservation;
    });
  }

  function releaseReservation(reservationId) {
    return mutate('reservation.released', data => {
      const reservation = data.reservations.find(item => item.id === reservationId);
      if (!reservation) throw new Error('Reserva não encontrada.');
      if (reservation.status === 'released' || reservation.status === 'expired') return reservation;
      if (reservation.status !== 'active') throw new Error('Somente reservas ativas podem ser liberadas.');
      reservation.status = 'released';
      const unit = data.units.find(item => item.id === reservation.unitId);
      if (unit?.reservationId === reservation.id && unit.status === 'reserved') Object.assign(unit, { status: 'available', reservationId: null });
      audit(data, 'reservation.released', 'reservation', reservation.id, `Unidade ${unit?.code || ''} liberada manualmente`);
      notify(data, 'Reserva liberada', unit?.code || reservation.id, 'reservas.html');
      return reservation;
    });
  }

  function convertProposalToSale(proposalId, reservationId, conditions) {
    expireReservations();
    return mutate('sale.created', data => {
      const reservation = data.reservations.find(item => item.id === reservationId) || data.reservations.find(item => item.proposalId === proposalId && item.status === 'active');
      const proposal = data.proposals.find(item => item.id === proposalId) || (reservation?.proposalId && data.proposals.find(item => item.id === reservation.proposalId));
      if (!proposal && !reservation) throw new Error('Informe uma proposta ou reserva válida.');
      const existing = data.sales.find(item => (proposal && item.proposalId === proposal.id) || (reservation && item.reservationId === reservation.id)) || (proposal?.saleId && data.sales.find(item => item.id === proposal.saleId));
      if (existing) return existing;
      if (proposal && proposal.status !== 'approved') throw new Error('A proposta precisa estar aprovada antes da venda.');
      if (proposal?.validUntil && proposal.validUntil < new Date().toISOString().slice(0, 10)) throw new Error('A proposta está vencida e precisa ser renovada.');
      if (!proposal && reservation.status !== 'active') throw new Error('A reserva precisa estar ativa antes da venda.');
      const source = proposal || { customerId: reservation.customerId, unitId: reservation.unitId, brokerId: reservation.brokerId, negotiatedPrice: Number(conditions?.amount), downPayment: Number(conditions?.downPayment), installmentCount: Number(conditions?.installmentCount) };
      const customer = data.customers.find(item => item.id === source.customerId);
      const unit = data.units.find(item => item.id === source.unitId);
      if (!customer || !unit) throw new Error('Cliente ou unidade da negociação não existe.');
      if (unit.status === 'sold') throw new Error('A unidade já está vendida.');
      if (unit.status === 'blocked') throw new Error('A unidade está bloqueada e não pode ser vendida.');
      if (unit.status === 'reserved' && (!reservation || unit.reservationId !== reservation.id || reservation.customerId !== customer.id)) throw new Error('A unidade está reservada para outra negociação.');
      if (!Number.isFinite(Number(source.negotiatedPrice)) || Number(source.negotiatedPrice) <= 0 || !Number.isInteger(Number(source.installmentCount)) || Number(source.installmentCount) < 1 || Number(source.downPayment) < 0 || Number(source.downPayment) > Number(source.negotiatedPrice)) throw new Error('Condições financeiras inválidas.');
      const soldAt = new Date().toISOString();
      const sale = { id: Store.generateId('sale'), number: sequence(data.sales, `V-${new Date().getFullYear()}-`, 4), customerId: customer.id, enterpriseId: unit.enterpriseId, unitId: unit.id, brokerId: source.brokerId, proposalId: proposal?.id || null, reservationId: reservation?.id || null, amount: Number(source.negotiatedPrice), soldAt, contractId: null, status: 'active' };
      const contract = { id: Store.generateId('contract'), number: sequence(data.contracts, 'CTR-', 6), saleId: sale.id, customerId: customer.id, enterpriseId: unit.enterpriseId, unitId: unit.id, totalAmount: sale.amount, downPayment: Number(source.downPayment), paidAmount: 0, installmentCount: Number(source.installmentCount), signedAt: soldAt.slice(0, 10), status: 'active' };
      sale.contractId = contract.id;
      const count = Math.max(1, Math.min(240, contract.installmentCount));
      const balanceCents = Math.round(Math.max(0, contract.totalAmount - contract.downPayment) * 100);
      const baseCents = Math.floor(balanceCents / count);
      const extraCents = balanceCents % count;
      for (let number = 1; number <= count; number += 1) {
        const due = new Date(); due.setMonth(due.getMonth() + number);
        const installment = { id: Store.generateId('installment'), contractId: contract.id, number, dueDate: due.toISOString().slice(0, 10), amount: (baseCents + (number <= extraCents ? 1 : 0)) / 100, status: 'pending', paymentId: null };
        data.installments.push(installment);
        data.accountsReceivable.push({ id: Store.generateId('receivable'), installmentId: installment.id, contractId: contract.id, dueDate: installment.dueDate, amount: installment.amount, status: 'pending' });
      }
      data.sales.push(sale); data.contracts.push(contract);
      Object.assign(unit, { status: 'sold', saleId: sale.id, ownerCustomerId: customer.id, reservationId: null });
      if (proposal) Object.assign(proposal, { status: 'converted', saleId: sale.id });
      if (reservation?.status === 'active') reservation.status = 'converted';
      audit(data, 'sale.created', 'sale', sale.id, `${sale.number} gerada a partir de ${proposal?.number || 'reserva ' + reservation.id}`);
      notify(data, 'Venda concluída', `${sale.number} · ${unit.code}`, 'vendas.html');
      return sale;
    });
  }

  function dialogForm(title, html, setup) {
    const content = document.createElement('div'); content.innerHTML = html;
    UI.openModal({ title, content, actions: [{ label: 'Fechar', variant: 'outline', value: null }] });
    UI.applyMasks(content); setup(content);
  }

  function leadForm(render) {
    const data = state();
    dialogForm('Novo lead', `<form class="row g-3" data-lead-form novalidate><div class="col-12"><label class="form-label" for="leadName">Nome</label><input id="leadName" name="name" class="form-control" required maxlength="100"></div><div class="col-md-6"><label class="form-label" for="leadPhone">Telefone</label><input id="leadPhone" name="phone" class="form-control" data-mask="phone" required></div><div class="col-md-6"><label class="form-label" for="leadEmail">E-mail</label><input id="leadEmail" name="email" type="email" class="form-control" required></div><div class="col-md-6"><label class="form-label" for="leadOrigin">Origem</label><select id="leadOrigin" name="origin" class="form-select" required><option>Site</option><option>Instagram</option><option>Indicação</option><option>WhatsApp</option></select></div><div class="col-md-6"><label class="form-label" for="leadEnterprise">Empreendimento</label><select id="leadEnterprise" name="enterpriseId" class="form-select" required>${optionList(data.enterprises, '', item => item.name)}</select></div><div class="col-md-6"><label class="form-label" for="leadBroker">Corretor</label><select id="leadBroker" name="brokerId" class="form-select" required>${optionList(data.brokers, '', item => item.name)}</select></div><div class="col-md-6"><label class="form-label" for="leadValue">Valor esperado</label><input id="leadValue" name="expectedValue" type="number" min="0" step="100" class="form-control" required></div><div class="col-12 d-flex justify-content-end"><button class="btn btn-primary-app" type="submit">Salvar lead</button></div></form>`, content => content.querySelector('form').addEventListener('submit', event => {
      event.preventDefault(); if (!event.currentTarget.checkValidity()) return event.currentTarget.classList.add('was-validated');
      const values = Object.fromEntries(new FormData(event.currentTarget));
      mutate('lead.created', draft => { const lead = { ...values, id: Store.generateId('lead'), cpf: null, unitId: null, stage: 'new', customerId: null, expectedValue: Number(values.expectedValue), lastInteractionAt: new Date().toISOString() }; draft.leads.push(lead); audit(draft, 'lead.created', 'lead', lead.id, `Lead ${lead.name} criado`); notify(draft, 'Novo lead', lead.name, 'crm.html'); });
      closeDialog(content); UI.showToast('Lead criado e adicionado ao funil.', 'success'); render();
    }));
  }

  function leadDetail(lead, render) {
    const data = state();
    const history = data.audits.filter(item => item.entityId === lead.id).slice(-6).reverse();
    const content = document.createElement('div');
    content.innerHTML = `<dl class="row mb-3"><dt class="col-4">Contato</dt><dd class="col-8">${esc(lead.phone)}<br>${esc(lead.email)}</dd><dt class="col-4">Empreendimento</dt><dd class="col-8">${esc(rel(data, 'enterprises', lead.enterpriseId))}</dd><dt class="col-4">Corretor</dt><dd class="col-8">${esc(rel(data, 'brokers', lead.brokerId))}</dd><dt class="col-4">Última interação</dt><dd class="col-8">${UI.formatDate(lead.lastInteractionAt, { dateStyle: 'medium', timeStyle: 'short' })}</dd></dl><h3 class="h6">Linha do tempo</h3><ul class="list-group mb-3">${history.length ? history.map(item => `<li class="list-group-item"><strong>${esc(item.detail)}</strong><small class="d-block text-secondary">${UI.formatDate(item.createdAt, { dateStyle: 'short', timeStyle: 'short' })}</small></li>`).join('') : '<li class="list-group-item text-secondary">Nenhuma interação registrada.</li>'}</ul>`;
    UI.openModal({ title: lead.name, content, actions: [{ label: 'Fechar', variant: 'outline', value: null }, { label: lead.customerId ? 'Cliente vinculado' : 'Converter em cliente', variant: 'primary', value: 'convert' }] }).then(result => {
      if (result !== 'convert' || lead.customerId) return;
      try { const customer = convertLead(lead.id); UI.showToast(`${customer.name} está na base de clientes.`, 'success'); render(); } catch (error) { UI.showToast(error.message, 'danger'); }
    });
  }

  function renderCrm(rootNode) {
    const data = state();
    rootNode.innerHTML = `<div class="section-head"><div><h2>CRM comercial</h2><p>Arraste os cards ou use o seletor acessível para avançar o atendimento.</p></div><button class="btn btn-primary-app" data-new-lead data-action-handled="true"><i class="bi bi-person-plus me-2"></i>Novo lead</button></div><div class="pipeline" data-pipeline>${STAGES.map(([key, label]) => { const leads = data.leads.filter(item => item.stage === key); return `<section class="pipeline-col" data-stage="${key}"><div class="pipeline-title"><span>${label}</span><span class="badge bg-secondary-subtle text-secondary">${leads.length}</span></div>${leads.map(lead => `<article class="lead-card" draggable="true" tabindex="0" data-lead-id="${lead.id}"><button type="button" class="border-0 bg-transparent p-0 text-start w-100" data-view-lead="${lead.id}"><span class="lead-name d-block">${esc(lead.name)}</span><span class="lead-meta d-block">${esc(lead.origin)} · ${esc(rel(data, 'enterprises', lead.enterpriseId))}</span><span class="lead-value d-block">${UI.formatMoney(lead.expectedValue)}</span></button><label class="visually-hidden" for="stage-${lead.id}">Etapa de ${esc(lead.name)}</label><select id="stage-${lead.id}" class="form-select form-select-sm mt-2" data-lead-stage="${lead.id}">${STAGES.map(([value, text]) => `<option value="${value}" ${value === lead.stage ? 'selected' : ''}>${text}</option>`).join('')}</select></article>`).join('')}</section>`; }).join('')}</div>`;
    rootNode.querySelector('[data-new-lead]').addEventListener('click', () => leadForm(() => renderCrm(rootNode)));
    const move = (leadId, nextStage) => { if (!STAGES.some(([key]) => key === nextStage)) return; mutate('lead.stage-changed', draft => { const lead = draft.leads.find(item => item.id === leadId); if (!lead || lead.stage === nextStage) return; lead.stage = nextStage; lead.lastInteractionAt = new Date().toISOString(); audit(draft, 'lead.stage-changed', 'lead', lead.id, `Movido para ${STAGES.find(item => item[0] === nextStage)[1]}`); }); renderCrm(rootNode); };
    rootNode.querySelectorAll('[data-lead-stage]').forEach(select => select.addEventListener('change', () => move(select.dataset.leadStage, select.value)));
    rootNode.querySelectorAll('[data-view-lead]').forEach(button => button.addEventListener('click', () => leadDetail(data.leads.find(item => item.id === button.dataset.viewLead), () => renderCrm(rootNode))));
    rootNode.querySelectorAll('[data-lead-id]').forEach(card => card.addEventListener('dragstart', event => event.dataTransfer.setData('text/plain', card.dataset.leadId)));
    rootNode.querySelectorAll('[data-stage]').forEach(column => { column.addEventListener('dragover', event => event.preventDefault()); column.addEventListener('drop', event => { event.preventDefault(); move(event.dataTransfer.getData('text/plain'), column.dataset.stage); }); });
  }

  function customerForm(customer, render) {
    const data = state(); const editing = Boolean(customer);
    dialogForm(editing ? 'Editar cliente' : 'Novo cliente', `<form class="row g-3" data-customer-form novalidate><div class="col-12"><label class="form-label" for="customerName">Nome</label><input id="customerName" name="name" class="form-control" required maxlength="100" value="${esc(customer?.name || '')}"></div><div class="col-md-6"><label class="form-label" for="customerCpf">CPF</label><input id="customerCpf" name="cpf" class="form-control" data-mask="cpf" required value="${esc(customer?.cpf || '')}"></div><div class="col-md-6"><label class="form-label" for="customerPhone">Telefone</label><input id="customerPhone" name="phone" class="form-control" data-mask="phone" required value="${esc(customer?.phone || '')}"></div><div class="col-12"><label class="form-label" for="customerEmail">E-mail</label><input id="customerEmail" name="email" type="email" class="form-control" required value="${esc(customer?.email || '')}"></div><div class="col-md-6"><label class="form-label" for="customerBroker">Corretor</label><select id="customerBroker" name="brokerId" class="form-select">${optionList(data.brokers, customer?.brokerId, item => item.name)}</select></div><div class="col-md-6"><label class="form-label" for="customerStatus">Status</label><select id="customerStatus" name="status" class="form-select"><option value="prospect" ${customer?.status === 'prospect' ? 'selected' : ''}>Prospect</option><option value="active" ${customer?.status === 'active' ? 'selected' : ''}>Ativo</option></select></div><div class="col-12 d-flex justify-content-end"><button class="btn btn-primary-app" type="submit">Salvar cliente</button></div></form>`, content => content.querySelector('form').addEventListener('submit', event => {
      event.preventDefault(); const form = event.currentTarget; if (!form.checkValidity()) return form.classList.add('was-validated');
      const values = Object.fromEntries(new FormData(form)); const draft = state(); const cpf = normalizeKey(values.cpf); const email = UI.normalize(values.email);
      const duplicate = draft.customers.find(item => item.id !== customer?.id && (normalizeKey(item.cpf) === cpf || UI.normalize(item.email) === email));
      if (duplicate) return UI.showToast('CPF ou e-mail já cadastrado para outro cliente.', 'danger');
      mutate(editing ? 'customer.updated' : 'customer.created', next => { if (editing) Object.assign(next.customers.find(item => item.id === customer.id), values); else { const record = { ...values, id: Store.generateId('customer'), createdAt: new Date().toISOString() }; next.customers.push(record); customer = record; } audit(next, editing ? 'customer.updated' : 'customer.created', 'customer', customer.id, `${customer.name} ${editing ? 'atualizado' : 'cadastrado'}`); notify(next, editing ? 'Cliente atualizado' : 'Novo cliente', customer.name, 'clientes.html'); });
      closeDialog(content); UI.showToast('Cadastro salvo com sucesso.', 'success'); render();
    }));
  }

  function renderCustomers(rootNode) {
    const data = state();
    rootNode.innerHTML = `<div class="section-head"><div><h2>Clientes</h2><p>Cadastro único com validação de CPF e e-mail duplicados.</p></div><button class="btn btn-primary-app" data-new-customer data-action-handled="true">Novo cliente</button></div><div class="card-app"><div class="table-responsive"><table class="table table-app"><thead><tr><th>Cliente</th><th>Telefone</th><th>E-mail</th><th>Status</th><th>Corretor</th><th>Ações</th></tr></thead><tbody>${data.customers.map(item => `<tr><td><div class="object-title">${esc(item.name)}</div><div class="object-sub">CPF ${esc(item.cpf || 'não informado')}</div></td><td>${esc(item.phone)}</td><td>${esc(item.email)}</td><td>${badge(item.status)}</td><td>${esc(rel(data, 'brokers', item.brokerId))}</td><td class="text-nowrap"><button class="btn btn-sm btn-outline-app" data-view-customer="${item.id}">Abrir</button> <button class="btn btn-sm btn-outline-app" data-edit-customer="${item.id}">Editar</button> <button class="btn btn-sm btn-outline-danger" data-delete-customer="${item.id}">Excluir</button></td></tr>`).join('')}</tbody></table></div></div>`;
    rootNode.querySelector('[data-new-customer]').addEventListener('click', () => customerForm(null, () => renderCustomers(rootNode)));
    rootNode.querySelectorAll('[data-edit-customer]').forEach(button => button.addEventListener('click', () => customerForm(data.customers.find(item => item.id === button.dataset.editCustomer), () => renderCustomers(rootNode))));
    rootNode.querySelectorAll('[data-view-customer]').forEach(button => button.addEventListener('click', () => { const item = data.customers.find(entry => entry.id === button.dataset.viewCustomer); const content = document.createElement('div'); const sales = data.sales.filter(entry => entry.customerId === item.id); content.innerHTML = `<dl class="row"><dt class="col-4">CPF</dt><dd class="col-8">${esc(item.cpf || '—')}</dd><dt class="col-4">Contato</dt><dd class="col-8">${esc(item.phone)}<br>${esc(item.email)}</dd><dt class="col-4">Corretor</dt><dd class="col-8">${esc(rel(data, 'brokers', item.brokerId))}</dd><dt class="col-4">Vendas</dt><dd class="col-8">${sales.length}</dd></dl>`; UI.openModal({ title: item.name, content }); }));
    rootNode.querySelectorAll('[data-delete-customer]').forEach(button => button.addEventListener('click', async () => { const item = data.customers.find(entry => entry.id === button.dataset.deleteCustomer); const linked = ['leads', 'proposals', 'reservations', 'sales', 'contracts'].some(name => data[name].some(entry => entry.customerId === item.id)); if (linked) return UI.showToast('Cliente possui vínculos comerciais e não pode ser excluído.', 'danger'); if (!await UI.confirmAction({ title: `Excluir ${item.name}?`, message: 'Essa ação remove o cadastro sem vínculos.', confirmLabel: 'Excluir', danger: true })) return; mutate('customer.deleted', draft => { draft.customers.splice(draft.customers.findIndex(entry => entry.id === item.id), 1); audit(draft, 'customer.deleted', 'customer', item.id, `${item.name} excluído`); notify(draft, 'Cliente excluído', item.name, 'clientes.html'); }); renderCustomers(rootNode); }));
    enhanceLater(rootNode);
  }

  function proposalForm(rootNode, unitId) {
    const data = state(); const unit = unitId && data.units.find(item => item.id === unitId);
    if (unitId && (!unit || ['sold', 'blocked'].includes(unit.status))) return UI.showToast('A unidade informada não existe ou está indisponível para proposta.', 'danger');
    const available = data.units.filter(item => !['sold', 'blocked'].includes(item.status));
    dialogForm('Nova proposta', `<form data-proposal-form novalidate><div data-step="1"><p class="text-secondary">Etapa 1 de 3 · Cliente e unidade</p><div class="mb-3"><label class="form-label" for="proposalCustomer">Cliente</label><select id="proposalCustomer" name="customerId" class="form-select" required>${optionList(data.customers, '', item => item.name)}</select></div><div class="mb-3"><label class="form-label" for="proposalUnit">Unidade disponível</label><select id="proposalUnit" name="unitId" class="form-select" required>${optionList(available, unitId, item => `${rel(data, 'enterprises', item.enterpriseId)} · ${item.code} · ${UI.formatMoney(item.listPrice)}`)}</select></div><div class="mb-3"><label class="form-label" for="proposalBroker">Corretor</label><select id="proposalBroker" name="brokerId" class="form-select" required>${optionList(data.brokers, '', item => item.name)}</select></div></div><div data-step="2" hidden><p class="text-secondary">Etapa 2 de 3 · Condições</p><div class="row g-3"><div class="col-md-6"><label class="form-label" for="proposalDiscount">Desconto</label><input id="proposalDiscount" name="discount" type="number" min="0" step="100" value="0" class="form-control" required></div><div class="col-md-6"><label class="form-label" for="proposalDown">Entrada</label><input id="proposalDown" name="downPayment" type="number" min="0" step="100" value="10000" class="form-control" required></div><div class="col-md-6"><label class="form-label" for="proposalInstallments">Parcelas</label><input id="proposalInstallments" name="installmentCount" type="number" min="1" max="240" value="120" class="form-control" required></div><div class="col-md-6"><label class="form-label" for="proposalValidity">Validade</label><input id="proposalValidity" name="validUntil" type="date" min="${futureDate(0)}" value="${futureDate(Number(data.settings.proposalDays) || 5)}" class="form-control" required></div></div></div><div data-step="3" hidden><p class="text-secondary">Etapa 3 de 3 · Revisão</p><div class="card bg-body-tertiary border-0 p-3" data-proposal-summary></div></div><div class="d-flex justify-content-between mt-4"><button type="button" class="btn btn-outline-app" data-prev disabled>Voltar</button><button type="button" class="btn btn-primary-app" data-next>Continuar</button><button type="submit" class="btn btn-primary-app" data-save hidden>Salvar proposta</button></div></form>`, content => { const form = content.querySelector('form'); let step = 1; const renderStep = () => { content.querySelectorAll('[data-step]').forEach(item => { item.hidden = Number(item.dataset.step) !== step; }); content.querySelector('[data-prev]').disabled = step === 1; content.querySelector('[data-next]').hidden = step === 3; content.querySelector('[data-save]').hidden = step !== 3; summary(); }; const summary = () => { const selectedUnit = state().units.find(item => item.id === form.elements.unitId.value); const price = Number(selectedUnit?.listPrice || 0); const discount = Number(form.elements.discount.value || 0); const down = Number(form.elements.downPayment.value || 0); const negotiated = price - discount; const installments = Number(form.elements.installmentCount.value || 1); const target = content.querySelector('[data-proposal-summary]'); if (target) target.innerHTML = `<strong>${esc(rel(data, 'customers', form.elements.customerId.value))} · ${esc(selectedUnit?.code || 'Unidade não selecionada')}</strong><span class="d-block mt-2">Tabela: ${UI.formatMoney(price)} · Desconto: ${UI.formatMoney(discount)}</span><span class="d-block">Negociado: ${UI.formatMoney(negotiated)} · Entrada: ${UI.formatMoney(down)}</span><span class="d-block">Saldo em ${installments}x de ${UI.formatMoney(Math.max(0, negotiated - down) / Math.max(1, installments))}</span>`; };
      form.addEventListener('input', summary); content.querySelector('[data-next]').addEventListener('click', () => { const controls = Array.from(content.querySelector(`[data-step="${step}"]`).querySelectorAll('input,select')); if (controls.some(input => !input.checkValidity())) { form.classList.add('was-validated'); return controls.find(input => !input.checkValidity()).reportValidity(); } step += 1; renderStep(); }); content.querySelector('[data-prev]').addEventListener('click', () => { step -= 1; renderStep(); });
      form.addEventListener('submit', event => { event.preventDefault(); if (!form.checkValidity()) return form.classList.add('was-validated'); const values = Object.fromEntries(new FormData(form)); const latest = state(); const selectedUnit = latest.units.find(item => item.id === values.unitId); const discount = Number(values.discount); const price = Number(selectedUnit?.listPrice); const down = Number(values.downPayment); const reservation = selectedUnit?.status === 'reserved' && latest.reservations.find(item => item.id === selectedUnit.reservationId && item.status === 'active'); if (!selectedUnit || ['sold', 'blocked'].includes(selectedUnit.status) || (reservation && reservation.customerId !== values.customerId)) return UI.showToast('A unidade deixou de estar disponível para este cliente.', 'danger'); if (latest.proposals.some(item => item.customerId === values.customerId && item.unitId === values.unitId && !['rejected', 'converted'].includes(item.status))) return UI.showToast('Já existe uma proposta aberta para este cliente e unidade.', 'danger'); if (discount < 0 || discount > price || down < 0 || down > price - discount) return UI.showToast('Desconto ou entrada fora dos limites da negociação.', 'danger'); mutate('proposal.created', draft => { const proposal = { ...values, id: Store.generateId('proposal'), number: sequence(draft.proposals, `P-${new Date().getFullYear()}-`, 4), enterpriseId: selectedUnit.enterpriseId, listPrice: price, discount, negotiatedPrice: price - discount, downPayment: down, installmentCount: Number(values.installmentCount), status: 'negotiation', saleId: null }; draft.proposals.push(proposal); audit(draft, 'proposal.created', 'proposal', proposal.id, `${proposal.number} criada`); notify(draft, 'Nova proposta criada', proposal.number, 'propostas.html'); }); closeDialog(content); UI.showToast('Proposta salva com sucesso.', 'success'); renderProposals(rootNode); }); renderStep(); });
  }

  function updateProposal(id, status, rootNode) {
    try { mutate(`proposal.${status}`, data => { const item = data.proposals.find(entry => entry.id === id); if (!item) throw new Error('Proposta não encontrada.'); if (item.saleId || item.status === 'converted') throw new Error('A proposta já foi convertida em venda.'); if (status === 'approved' && item.validUntil < new Date().toISOString().slice(0, 10)) throw new Error('A proposta está vencida e não pode ser aprovada.'); item.status = status; audit(data, `proposal.${status}`, 'proposal', item.id, `${item.number} ${status === 'approved' ? 'aprovada' : 'recusada'}`); notify(data, status === 'approved' ? 'Proposta aprovada' : 'Proposta recusada', item.number, 'propostas.html'); }); UI.showToast('Status da proposta atualizado.', 'success'); renderProposals(rootNode); } catch (error) { UI.showToast(error.message, 'danger'); }
  }

  function renderProposals(rootNode) {
    const data = state();
    rootNode.innerHTML = `<div class="section-head"><div><h2>Propostas</h2><p>Condições comerciais, aprovação, reserva e conversão sem duplicidade.</p></div><button class="btn btn-primary-app" data-new-proposal data-action-handled="true">Nova proposta</button></div><div class="card-app"><div class="table-responsive"><table class="table table-app"><thead><tr><th>Proposta</th><th>Cliente</th><th>Unidade</th><th>Valor</th><th>Validade</th><th>Status</th><th>Ações</th></tr></thead><tbody>${data.proposals.map(item => `<tr><td>${esc(item.number)}</td><td>${esc(rel(data, 'customers', item.customerId))}</td><td>${esc(rel(data, 'units', item.unitId, 'code'))}</td><td><strong>${UI.formatMoney(item.negotiatedPrice)}</strong></td><td>${UI.formatDate(item.validUntil)}</td><td>${badge(item.status)}</td><td class="text-nowrap">${['negotiation', 'sent'].includes(item.status) ? `<button class="btn btn-sm btn-outline-success" data-proposal-approve="${item.id}">Aprovar</button> <button class="btn btn-sm btn-outline-danger" data-proposal-reject="${item.id}">Recusar</button>` : ''}${item.status === 'approved' && !item.saleId ? ` <button class="btn btn-sm btn-outline-app" data-proposal-reserve="${item.id}">Reservar</button> <button class="btn btn-sm btn-primary-app" data-proposal-sale="${item.id}">Converter</button>` : ''}</td></tr>`).join('')}</tbody></table></div></div>`;
    rootNode.querySelector('[data-new-proposal]').addEventListener('click', () => proposalForm(rootNode));
    rootNode.querySelectorAll('[data-proposal-approve]').forEach(button => button.addEventListener('click', () => updateProposal(button.dataset.proposalApprove, 'approved', rootNode)));
    rootNode.querySelectorAll('[data-proposal-reject]').forEach(button => button.addEventListener('click', async () => { if (await UI.confirmAction({ title: 'Recusar proposta?', confirmLabel: 'Recusar', danger: true })) updateProposal(button.dataset.proposalReject, 'rejected', rootNode); }));
    rootNode.querySelectorAll('[data-proposal-reserve]').forEach(button => button.addEventListener('click', () => { const item = state().proposals.find(entry => entry.id === button.dataset.proposalReserve); try { createReservation({ proposalId: item.id, customerId: item.customerId, unitId: item.unitId, brokerId: item.brokerId }); UI.showToast('Unidade reservada para esta proposta.', 'success'); renderProposals(rootNode); } catch (error) { UI.showToast(error.message, 'danger'); } }));
    rootNode.querySelectorAll('[data-proposal-sale]').forEach(button => button.addEventListener('click', async () => { if (!await UI.confirmAction({ title: 'Converter proposta em venda?', message: 'A unidade será vendida e o contrato com parcelas será gerado.', confirmLabel: 'Concluir venda' })) return; try { const sale = convertProposalToSale(button.dataset.proposalSale); UI.showToast(`${sale.number} concluída com contrato gerado.`, 'success'); renderProposals(rootNode); } catch (error) { UI.showToast(error.message, 'danger'); } }));
    enhanceLater(rootNode);
  }

  function reservationForm(rootNode, unitId) {
    const data = state(); const unit = unitId && data.units.find(item => item.id === unitId);
    if (unitId && (!unit || unit.status !== 'available')) return UI.showToast('A unidade informada não existe ou não está disponível.', 'danger');
    dialogForm('Nova reserva', `<form class="row g-3" data-reservation-form novalidate><div class="col-12"><label class="form-label" for="reservationProposal">Proposta aprovada (opcional)</label><select id="reservationProposal" name="proposalId" class="form-select">${optionList(data.proposals.filter(item => item.status === 'approved' && !item.saleId), '', item => item.number, 'Reserva avulsa')}</select></div><div class="col-md-6"><label class="form-label" for="reservationCustomer">Cliente</label><select id="reservationCustomer" name="customerId" class="form-select" required>${optionList(data.customers, '', item => item.name)}</select></div><div class="col-md-6"><label class="form-label" for="reservationUnit">Unidade</label><select id="reservationUnit" name="unitId" class="form-select" required>${optionList(data.units.filter(item => !['sold', 'blocked'].includes(item.status)), unitId, item => `${rel(data, 'enterprises', item.enterpriseId)} · ${item.code}`)}</select></div><div class="col-md-6"><label class="form-label" for="reservationBroker">Corretor</label><select id="reservationBroker" name="brokerId" class="form-select" required>${optionList(data.brokers, '', item => item.name)}</select></div><div class="col-md-6"><label class="form-label" for="reservationHours">Duração em horas</label><input id="reservationHours" name="hours" type="number" min="1" max="168" value="${Number(data.settings.reservationHours) || 48}" class="form-control" required></div><div class="col-12 d-flex justify-content-end"><button class="btn btn-primary-app" type="submit">Confirmar reserva</button></div></form>`, content => { const form = content.querySelector('form'); form.elements.proposalId.addEventListener('change', () => { const proposal = state().proposals.find(item => item.id === form.elements.proposalId.value); if (proposal) { form.elements.customerId.value = proposal.customerId; form.elements.unitId.value = proposal.unitId; form.elements.brokerId.value = proposal.brokerId; } }); form.addEventListener('submit', event => { event.preventDefault(); if (!form.checkValidity()) return form.classList.add('was-validated'); try { createReservation(Object.fromEntries(new FormData(form))); closeDialog(content); UI.showToast('Reserva criada e unidade bloqueada.', 'success'); renderReservations(rootNode); } catch (error) { UI.showToast(error.message, 'danger'); } }); });
  }

  function renderReservations(rootNode) {
    expireReservations(); const data = state(); const active = data.reservations.filter(item => item.status === 'active');
    rootNode.innerHTML = `<div class="section-head"><div><h2>Reservas</h2><p>Expiração automática e liberação segura da unidade.</p></div><button class="btn btn-primary-app" data-new-reservation data-action-handled="true">Nova reserva</button></div><div class="row g-3 mb-3"><div class="col-md-4"><div class="card-app metric gold"><div class="label">Reservas ativas</div><div class="value">${active.length}</div></div></div><div class="col-md-4"><div class="card-app metric red"><div class="label">Expiram em 24h</div><div class="value">${active.filter(item => new Date(item.expiresAt).getTime() - Date.now() <= 86400000).length}</div></div></div></div><div class="card-app"><div class="table-responsive"><table class="table table-app"><thead><tr><th>Unidade</th><th>Cliente</th><th>Corretor</th><th>Início</th><th>Tempo restante</th><th>Status</th><th>Ação</th></tr></thead><tbody>${data.reservations.map(item => `<tr><td><strong>${esc(rel(data, 'units', item.unitId, 'code'))}</strong></td><td>${esc(rel(data, 'customers', item.customerId))}</td><td>${esc(rel(data, 'brokers', item.brokerId))}</td><td>${UI.formatDate(item.createdAt, { dateStyle: 'short', timeStyle: 'short' })}</td><td data-countdown="${esc(item.expiresAt)}" data-status="${item.status}">${item.status === 'active' ? 'Calculando…' : '—'}</td><td>${badge(item.status)}</td><td>${item.status === 'active' ? `<button class="btn btn-sm btn-outline-danger" data-release-reservation="${item.id}">Liberar</button>` : ''}</td></tr>`).join('')}</tbody></table></div></div>`;
    const tick = () => { let ended = false; rootNode.querySelectorAll('[data-countdown][data-status="active"]').forEach(item => { const remaining = Math.max(0, new Date(item.dataset.countdown).getTime() - Date.now()); const hours = Math.floor(remaining / 3600000); const minutes = Math.floor((remaining % 3600000) / 60000); item.textContent = `${hours}h ${String(minutes).padStart(2, '0')}min`; if (!remaining) ended = true; }); if (ended) renderReservations(rootNode); }; tick(); root.clearInterval(rootNode._reservationTimer); rootNode._reservationTimer = root.setInterval(tick, 60000);
    rootNode.querySelector('[data-new-reservation]').addEventListener('click', () => reservationForm(rootNode));
    rootNode.querySelectorAll('[data-release-reservation]').forEach(button => button.addEventListener('click', async () => { if (!await UI.confirmAction({ title: 'Liberar esta unidade?', message: 'A reserva será encerrada e a unidade voltará a ficar disponível.', confirmLabel: 'Liberar', danger: true })) return; try { releaseReservation(button.dataset.releaseReservation); UI.showToast('Reserva liberada.', 'success'); renderReservations(rootNode); } catch (error) { UI.showToast(error.message, 'danger'); } })); enhanceLater(rootNode);
  }

  function saleDetail(sale) {
    const data = state(); const contract = data.contracts.find(item => item.id === sale.contractId); const installments = data.installments.filter(item => item.contractId === sale.contractId);
    const content = document.createElement('div'); content.innerHTML = `<dl class="row"><dt class="col-4">Cliente</dt><dd class="col-8">${esc(rel(data, 'customers', sale.customerId))}</dd><dt class="col-4">Unidade</dt><dd class="col-8">${esc(rel(data, 'units', sale.unitId, 'code'))}</dd><dt class="col-4">Valor</dt><dd class="col-8">${UI.formatMoney(sale.amount)}</dd><dt class="col-4">Contrato</dt><dd class="col-8">${esc(contract?.number || '—')}</dd></dl><h3 class="h6">Linha do tempo</h3><ul class="list-group"><li class="list-group-item">Venda concluída · ${UI.formatDate(sale.soldAt, { dateStyle: 'short', timeStyle: 'short' })}</li><li class="list-group-item">Contrato gerado · ${installments.length} parcelas</li>${data.audits.filter(item => item.entityId === sale.id).map(item => `<li class="list-group-item">${esc(item.detail)}</li>`).join('')}</ul>`; UI.openModal({ title: sale.number, content });
  }

  function saleForm(rootNode) {
    const data = state(); const proposals = data.proposals.filter(item => item.status === 'approved' && !item.saleId); const reservations = data.reservations.filter(item => item.status === 'active' && !data.sales.some(sale => sale.reservationId === item.id));
    dialogForm('Registrar venda', `<form class="row g-3" data-sale-form novalidate><div class="col-12"><label class="form-label" for="saleSource">Origem da venda</label><select id="saleSource" name="source" class="form-select" required><option value="">Selecione</option>${proposals.map(item => `<option value="p:${item.id}">Proposta ${esc(item.number)} · ${esc(rel(data, 'units', item.unitId, 'code'))}</option>`).join('')}${reservations.map(item => `<option value="r:${item.id}">Reserva · ${esc(rel(data, 'units', item.unitId, 'code'))} · ${esc(rel(data, 'customers', item.customerId))}</option>`).join('')}</select></div><div class="col-md-4"><label class="form-label" for="saleAmount">Valor</label><input id="saleAmount" name="amount" type="number" min="1" step="100" class="form-control" required></div><div class="col-md-4"><label class="form-label" for="saleDown">Entrada</label><input id="saleDown" name="downPayment" type="number" min="0" step="100" class="form-control" required></div><div class="col-md-4"><label class="form-label" for="saleInstallments">Parcelas</label><input id="saleInstallments" name="installmentCount" type="number" min="1" max="240" class="form-control" required></div><div class="col-12 d-flex justify-content-end"><button class="btn btn-primary-app" type="submit">Concluir venda</button></div></form>`, content => { const form = content.querySelector('form'); form.elements.source.addEventListener('change', () => { const [kind, id] = form.elements.source.value.split(':'); const reservation = kind === 'r' && state().reservations.find(item => item.id === id); const proposal = (kind === 'p' && state().proposals.find(item => item.id === id)) || (reservation?.proposalId && state().proposals.find(item => item.id === reservation.proposalId)); const unit = reservation && state().units.find(item => item.id === reservation.unitId); form.elements.amount.value = proposal?.negotiatedPrice || unit?.listPrice || ''; form.elements.downPayment.value = proposal?.downPayment || Math.round((unit?.listPrice || 0) * .1); form.elements.installmentCount.value = proposal?.installmentCount || 120; }); form.addEventListener('submit', async event => { event.preventDefault(); if (!form.checkValidity()) return form.classList.add('was-validated'); const [kind, id] = form.elements.source.value.split(':'); if (!await UI.confirmAction({ title: 'Concluir esta venda?', message: 'Contrato, parcelas e contas a receber serão gerados.', confirmLabel: 'Concluir venda' })) return; try { const sale = convertProposalToSale(kind === 'p' ? id : null, kind === 'r' ? id : null, { amount: Number(form.elements.amount.value), downPayment: Number(form.elements.downPayment.value), installmentCount: Number(form.elements.installmentCount.value) }); closeDialog(content); UI.showToast(`${sale.number} concluída sem duplicidade.`, 'success'); renderSales(rootNode); } catch (error) { UI.showToast(error.message, 'danger'); } }); });
  }

  function renderSales(rootNode) {
    const data = state(); const total = data.sales.reduce((sum, item) => sum + Number(item.amount), 0);
    rootNode.innerHTML = `<div class="section-head"><div><h2>Vendas</h2><p>Negócios concluídos com contrato e financeiro gerados atomicamente.</p></div><button class="btn btn-primary-app" data-new-sale data-action-handled="true">Registrar venda</button></div><div class="row g-3 mb-3"><div class="col-md-4"><div class="card-app metric"><div class="label">Vendas</div><div class="value">${data.sales.length}</div></div></div><div class="col-md-4"><div class="card-app metric gold"><div class="label">VGV realizado</div><div class="value">${UI.formatMoney(total)}</div></div></div><div class="col-md-4"><div class="card-app metric blue"><div class="label">Ticket médio</div><div class="value">${UI.formatMoney(total / Math.max(1, data.sales.length))}</div></div></div></div><div class="card-app"><div class="table-responsive"><table class="table table-app"><thead><tr><th>Venda</th><th>Cliente</th><th>Empreendimento</th><th>Unidade</th><th>Valor</th><th>Corretor</th><th>Data</th><th></th></tr></thead><tbody>${data.sales.map(item => `<tr><td>${esc(item.number)}</td><td>${esc(rel(data, 'customers', item.customerId))}</td><td>${esc(rel(data, 'enterprises', item.enterpriseId))}</td><td>${esc(rel(data, 'units', item.unitId, 'code'))}</td><td><strong>${UI.formatMoney(item.amount)}</strong></td><td>${esc(rel(data, 'brokers', item.brokerId))}</td><td>${UI.formatDate(item.soldAt)}</td><td><button class="btn btn-sm btn-outline-app" data-view-sale="${item.id}">Abrir</button></td></tr>`).join('')}</tbody></table></div></div>`;
    rootNode.querySelector('[data-new-sale]').addEventListener('click', () => saleForm(rootNode)); rootNode.querySelectorAll('[data-view-sale]').forEach(button => button.addEventListener('click', () => saleDetail(data.sales.find(item => item.id === button.dataset.viewSale)))); enhanceLater(rootNode);
  }

  function enhanceLater(rootNode) { root.requestAnimationFrame(() => rootNode.querySelectorAll('.table-app').forEach(UI.enhanceTable)); }

  function init(page) {
    const supported = ['crm', 'clientes', 'propostas', 'reservas', 'vendas'];
    if (!supported.includes(page)) return false;
    const rootNode = document.querySelector('.content');
    if (!rootNode || rootNode.dataset.commercialInitialized === 'true') return false;
    rootNode.dataset.commercialInitialized = 'true';
    const renderers = { crm: renderCrm, clientes: renderCustomers, propostas: renderProposals, reservas: renderReservations, vendas: renderSales };
    renderers[page](rootNode);
    const params = new URLSearchParams(root.location.search);
    if (params.get('action') === 'new') {
      const unitId = params.get('unitId');
      if (page === 'crm') leadForm(() => renderCrm(rootNode));
      if (page === 'clientes') customerForm(null, () => renderCustomers(rootNode));
      if (page === 'propostas') proposalForm(rootNode, unitId);
      if (page === 'reservas') reservationForm(rootNode, unitId);
    }
    if (!inventoryListenerBound) { const handleInventory = event => { const action = event.detail?.action; const unitId = event.detail?.unitId; if (page === 'propostas' && ['proposal', 'new-proposal', 'proposal.requested'].includes(action)) proposalForm(rootNode, unitId); if (page === 'reservas' && ['reservation', 'new-reservation', 'reservation.requested'].includes(action)) reservationForm(rootNode, unitId); }; root.addEventListener('urbanix:inventory-action', handleInventory); root.addEventListener('urbanix:commercial-action', handleInventory); inventoryListenerBound = true; }
    return true;
  }

  Urbanix.Commercial = Object.freeze({ init, convertLead, createReservation, convertProposalToSale, expireReservations, releaseReservation });
})(window);
