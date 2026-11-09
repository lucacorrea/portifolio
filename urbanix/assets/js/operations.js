(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const Store = Urbanix.Store;
  const UI = Urbanix.UI;
  const money = value => UI.formatMoney(value, { maximumFractionDigits: 2 });
  const currentPage = () => document.body.dataset.page;
  const getState = () => Store.getState();
  const find = (items, id) => items.find(item => item.id === id);
  const name = (items, id) => find(items, id)?.name || 'Nao informado';
  const total = items => items.reduce((sum, item) => sum + Number(item.amount || 0), 0);

  function badge(status) {
    const map = {
      active: ['Em execucao', 'soft-success'], attention: ['Atencao', 'soft-danger'], draft: ['Rascunho', 'soft-muted'],
      submitted: ['Aguardando aprovacao', 'soft-warning'], approved: ['Aprovada', 'soft-success'], rejected: ['Rejeitada', 'soft-danger'],
      paid: ['Paga', 'soft-success'], quotation: ['Cotacao', 'soft-warning'], approval: ['Aprovacao', 'soft-info'],
      order: ['Pedido emitido', 'soft-info'], received: ['Recebido', 'soft-success'], cancelled: ['Cancelado', 'soft-muted']
    };
    const item = map[status] || [status || 'Pendente', 'soft-muted'];
    return `<span class="badge-soft ${item[1]}">${item[0]}</span>`;
  }

  async function formDialog(title, fields, submitLabel, eyebrow) {
    const form = document.createElement('form');
    form.className = 'row g-3';
    form.innerHTML = fields;
    const promise = UI.openModal({
      eyebrow: eyebrow || 'Operacao de obra', title, content: form,
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

  function audit(state, action, entity, entityId, detail) {
    state.audits.push({ id: Store.generateId('audit'), userId: state.session?.userId || 'user-admin', action, entity, entityId, detail, createdAt: new Date().toISOString() });
  }

  function workDetail(workId) {
    const state = getState();
    const work = find(state.works, workId);
    if (!work) return;
    const enterprise = find(state.enterprises, work.enterpriseId);
    const services = state.services.filter(item => item.workId === work.id);
    const measurements = state.measurements.filter(item => item.workId === work.id);
    const purchases = state.purchaseRequests.filter(item => item.workId === work.id);
    const body = document.createElement('div');
    body.innerHTML = `<div class="detail-hero"><div><small>${UI.escapeHtml(enterprise?.name || '')}</small><h3>${UI.escapeHtml(work.name)}</h3><p>Execucao fisica, cronograma, medicoes, compras e custos.</p></div>${badge(work.status)}</div>
      <div class="detail-kpis"><div><span>Avanco</span><strong>${work.progress}%</strong></div><div><span>Orcado</span><strong>${money(work.budget)}</strong></div><div><span>Executado</span><strong>${money(work.executedCost)}</strong></div><div><span>Comprometido</span><strong>${money(total(state.accountsPayable.filter(item => item.workId === work.id && item.status !== 'cancelled')))}</strong></div></div>
      <div class="tabs-row mt-3" role="tablist"><button class="active" data-view="schedule">Cronograma</button><button data-view="measurements">Medicoes</button><button data-view="purchases">Compras</button></div><div data-work-view></div>`;
    const renderTab = tab => {
      body.querySelectorAll('[data-view]').forEach(button => button.classList.toggle('active', button.dataset.view === tab));
      const target = body.querySelector('[data-work-view]');
      if (tab === 'schedule') target.innerHTML = `<div class="gantt-list">${services.map((service, index) => `<div class="gantt-row"><div><strong>${UI.escapeHtml(service.name)}</strong><small>${index % 2 ? '01/04/2026 - 30/09/2026' : '15/02/2026 - 31/08/2026'}</small></div><div class="gantt-track"><span style="width:${service.progress}%"></span></div><strong>${service.progress}%</strong></div>`).join('')}</div>`;
      if (tab === 'measurements') target.innerHTML = `<div class="compact-list">${measurements.map(item => `<div><span><strong>${item.number}</strong><small>${UI.escapeHtml(name(state.services, item.serviceId))}</small></span><span>${money(item.amount)} ${badge(item.status)}</span></div>`).join('') || '<p class="empty-note">Nenhuma medicao cadastrada.</p>'}</div>`;
      if (tab === 'purchases') target.innerHTML = `<div class="compact-list">${purchases.map(item => `<div><span><strong>${item.number}</strong><small>${UI.escapeHtml(item.material)} · ${item.quantity} ${item.unit}</small></span><span>${money(item.estimatedAmount)} ${badge(item.status)}</span></div>`).join('') || '<p class="empty-note">Nenhuma compra vinculada.</p>'}</div>`;
    };
    body.querySelectorAll('[data-view]').forEach(button => button.addEventListener('click', () => renderTab(button.dataset.view)));
    renderTab('schedule');
    UI.openModal({ eyebrow: 'View da obra', title: work.name, content: body, actions: [{ label: 'Imprimir', variant: 'outline', value: 'print' }, { label: 'Fechar', variant: 'primary', value: null }] }).then(result => { if (result === 'print') root.print(); });
  }

  async function newMeasurement() {
    const state = getState();
    const data = await formDialog('Nova medicao', `<div class="col-md-6"><label class="form-label" for="measurementWork">Obra</label><select id="measurementWork" name="workId" class="form-select" required>${state.works.map(item => `<option value="${item.id}">${UI.escapeHtml(item.name)}</option>`).join('')}</select></div><div class="col-md-6"><label class="form-label" for="measurementService">Servico</label><select id="measurementService" name="serviceId" class="form-select" required>${state.services.map(item => `<option value="${item.id}">${UI.escapeHtml(item.name)}</option>`).join('')}</select></div><div class="col-md-4"><label class="form-label" for="measurementQuantity">Quantidade atual</label><input id="measurementQuantity" name="quantity" class="form-control" type="number" min="0.01" step="0.01" required></div><div class="col-md-4"><label class="form-label" for="measurementProgress">Avanco (%)</label><input id="measurementProgress" name="progress" class="form-control" type="number" min="0.01" max="100" step="0.01" required></div><div class="col-md-4"><label class="form-label" for="measurementAmount">Valor</label><input id="measurementAmount" name="amount" class="form-control" type="number" min="0.01" step="0.01" required></div><div class="col-12"><label class="form-label" for="measurementResponsible">Responsavel</label><input id="measurementResponsible" name="responsible" class="form-control" maxlength="100" required></div><div class="col-12"><label class="form-label" for="measurementPhotos">Fotos da medicao</label><input id="measurementPhotos" name="photos" class="form-control" type="file" accept="image/*" multiple><small class="form-text">Arquivos sao simulados e somente os nomes ficam registrados.</small></div>`, 'Cadastrar medicao');
    if (!data) return;
    Store.mutate('measurement.created', entryState => {
      const id = Store.generateId('measurement');
      entryState.measurements.push({ id, workId: data.get('workId'), serviceId: data.get('serviceId'), number: `MED-${String(82 + entryState.measurements.length).padStart(4, '0')}`, quantity: Number(data.get('quantity')), amount: Number(data.get('amount')), progress: Number(data.get('progress')), responsible: data.get('responsible').trim(), photos: data.getAll('photos').filter(file => file?.name).map(file => file.name), status: 'draft', createdAt: new Date().toISOString(), accountedAt: null });
      audit(entryState, 'measurement.created', 'measurements', id, 'Nova medicao cadastrada');
    });
    UI.showToast('Medicao criada como rascunho.', 'success');
    renderEngineering();
  }

  async function measurementAction(id, action) {
    const state = getState();
    const item = find(state.measurements, id);
    if (!item) return;
    const rules = { submit: ['submitted', 'Enviar medicao para aprovacao?'], approve: ['approved', 'Aprovar esta medicao?'], reject: ['rejected', 'Rejeitar esta medicao?'] };
    if (!rules[action]) return;
    if (!await UI.confirmAction({ title: rules[action][1], message: `${item.number} · ${money(item.amount)}`, confirmLabel: action === 'reject' ? 'Rejeitar' : 'Confirmar', danger: action === 'reject' })) return;
    Store.mutate(`measurement.${action}`, entryState => {
      const target = find(entryState.measurements, id);
      if (action === 'submit' && target.status !== 'draft') throw new Error('Somente rascunhos podem ser enviados.');
      if ((action === 'approve' || action === 'reject') && target.status !== 'submitted') throw new Error('A medicao nao aguarda aprovacao.');
      target.status = rules[action][0];
      if (action === 'approve' && !entryState.accountsPayable.some(account => account.measurementId === id)) {
        entryState.accountsPayable.push({ id: Store.generateId('payable'), supplierId: entryState.suppliers[0]?.id, workId: target.workId, measurementId: id, description: `Medicao ${target.number} · ${name(entryState.services, target.serviceId)}`, dueDate: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10), amount: target.amount, status: 'pending' });
      }
      audit(entryState, `measurement.${action}`, 'measurements', id, `Medicao ${target.number}: ${rules[action][0]}`);
    });
    UI.showToast('Status da medicao atualizado.', 'success');
    renderEngineering();
  }

  function measurementDetail(id) {
    const state = getState();
    const item = find(state.measurements, id);
    if (!item) return;
    const service = find(state.services, item.serviceId);
    const previous = state.measurements.filter(entry => entry.serviceId === item.serviceId && entry.id !== id && entry.status !== 'rejected').reduce((sum, entry) => sum + Number(entry.progress || 0), 0);
    const body = document.createElement('div');
    body.innerHTML = `<div class="detail-hero"><div><small>${UI.escapeHtml(name(state.works, item.workId))}</small><h3>${item.number}</h3><p>${UI.escapeHtml(service?.name || '')} · ${UI.escapeHtml(item.responsible || 'Responsavel nao informado')}</p></div>${badge(item.status)}</div><div class="detail-kpis"><div><span>Valor</span><strong>${money(item.amount)}</strong></div><div><span>Anterior</span><strong>${previous}%</strong></div><div><span>Atual</span><strong>${item.progress}%</strong></div><div><span>Acumulado</span><strong>${Math.min(100, previous + Number(item.progress || 0))}%</strong></div></div><div class="mt-3"><strong>Fotos anexadas</strong><p class="text-secondary mt-1">${item.photos?.length ? item.photos.map(UI.escapeHtml).join(', ') : 'Nenhuma foto anexada.'}</p></div>`;
    UI.openModal({ eyebrow: 'Medicao de obra', title: item.number, content: body });
  }

  async function newDiary() {
    const state = getState();
    const data = await formDialog('Novo diario de obra', `<div class="col-md-6"><label class="form-label" for="diaryWork">Obra</label><select id="diaryWork" name="workId" class="form-select" required>${state.works.map(item => `<option value="${item.id}">${UI.escapeHtml(item.name)}</option>`).join('')}</select></div><div class="col-md-3"><label class="form-label" for="diaryDate">Data</label><input id="diaryDate" name="date" class="form-control" type="date" value="${new Date().toISOString().slice(0, 10)}" required></div><div class="col-md-3"><label class="form-label" for="diaryWeather">Clima</label><select id="diaryWeather" name="weather" class="form-select"><option>Ensolarado</option><option>Nublado</option><option>Chuvoso</option></select></div><div class="col-md-4"><label class="form-label" for="diaryTeam">Equipe</label><input id="diaryTeam" name="team" class="form-control" type="number" min="0" required></div><div class="col-md-8"><label class="form-label" for="diaryEquipment">Equipamentos</label><input id="diaryEquipment" name="equipment" class="form-control" maxlength="160"></div><div class="col-12"><label class="form-label" for="diaryServices">Servicos realizados</label><textarea id="diaryServices" name="services" class="form-control" rows="3" maxlength="500" required></textarea></div><div class="col-12"><label class="form-label" for="diaryOccurrences">Ocorrencias / visitantes</label><textarea id="diaryOccurrences" name="occurrences" class="form-control" rows="2" maxlength="400"></textarea></div>`, 'Salvar diario');
    if (!data) return;
    Store.mutate('work-diary.created', entryState => {
      entryState.workDiaries ||= [];
      const id = Store.generateId('diary');
      entryState.workDiaries.push({ id, workId: data.get('workId'), date: data.get('date'), weather: data.get('weather'), team: Number(data.get('team')), equipment: data.get('equipment').trim(), services: data.get('services').trim(), occurrences: data.get('occurrences').trim() });
      audit(entryState, 'work-diary.created', 'workDiaries', id, 'Diario de obra cadastrado');
    });
    UI.showToast('Diario de obra registrado.', 'success');
    renderEngineering();
  }

  function renderEngineering() {
    const section = document.querySelector('.content');
    if (!section) return;
    const state = getState();
    const budget = state.works.reduce((sum, item) => sum + item.budget, 0);
    const executed = state.works.reduce((sum, item) => sum + item.executedCost, 0);
    const average = Math.round(state.works.reduce((sum, item) => sum + item.progress, 0) / Math.max(1, state.works.length));
    section.innerHTML = `<div class="section-head"><div><h2>Engenharia / Obras</h2><p>Planejamento fisico, cronograma, medicoes, diario e custos integrados.</p></div><div class="d-flex gap-2"><button class="btn btn-outline-app" data-new-diary>Novo diario</button><button class="btn btn-primary-app" data-new-measurement>Nova medicao</button></div></div><div class="row g-3 mb-3"><div class="col-xl-3 col-md-6"><div class="card-app metric"><div class="label">Obras ativas</div><div class="value">${state.works.filter(item => item.status === 'active').length}</div><div class="delta">${state.works.length} obras monitoradas</div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric blue"><div class="label">Avanco medio</div><div class="value">${average}%</div><div class="delta">Evolucao fisica</div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric gold"><div class="label">Orcamento</div><div class="value">${money(budget)}</div><div class="delta">${money(executed)} executados</div></div></div><div class="col-xl-3 col-md-6"><div class="card-app metric red"><div class="label">Pendencias</div><div class="value">${state.measurements.filter(item => item.status === 'submitted').length}</div><div class="delta">Medicoes aguardando decisao</div></div></div></div><div class="row g-3 mb-3">${state.works.map(work => `<div class="col-xl-6"><article class="card-app h-100"><div class="card-body"><div class="d-flex justify-content-between"><div><small class="text-secondary">${UI.escapeHtml(name(state.enterprises, work.enterpriseId))}</small><h3 class="h5 mt-1">${UI.escapeHtml(work.name)}</h3></div>${badge(work.status)}</div><div class="d-flex justify-content-between small mt-3"><span>Avanco fisico</span><strong>${work.progress}%</strong></div><div class="progress progress-thin mt-1"><div class="progress-bar" style="width:${work.progress}%"></div></div><div class="detail-kpis mt-3"><div><span>Orcado</span><strong>${money(work.budget)}</strong></div><div><span>Executado</span><strong>${money(work.executedCost)}</strong></div></div><button class="btn btn-outline-app mt-3" data-work="${work.id}">Abrir obra</button></div></article></div>`).join('')}</div><div class="card-app"><div class="card-header"><strong>Medicoes</strong></div><div class="table-responsive"><table class="table table-app"><thead><tr><th>Medicao</th><th>Obra / servico</th><th>Avanco</th><th>Valor</th><th>Status</th><th>Acoes</th></tr></thead><tbody>${state.measurements.map(item => `<tr><td><strong>${item.number}</strong></td><td>${UI.escapeHtml(name(state.works, item.workId))}<div class="object-sub">${UI.escapeHtml(name(state.services, item.serviceId))}</div></td><td>${item.progress}%</td><td>${money(item.amount)}</td><td>${badge(item.status)}</td><td><button class="btn btn-sm btn-outline-app" data-measurement="${item.id}">Ver</button> ${item.status === 'draft' ? `<button class="btn btn-sm btn-primary-app" data-measurement-action="submit" data-id="${item.id}">Enviar</button>` : item.status === 'submitted' ? `<button class="btn btn-sm btn-primary-app" data-measurement-action="approve" data-id="${item.id}">Aprovar</button> <button class="btn btn-sm btn-outline-danger" data-measurement-action="reject" data-id="${item.id}">Rejeitar</button>` : ''}</td></tr>`).join('')}</tbody></table></div></div>`;
    section.querySelector('[data-new-measurement]').addEventListener('click', newMeasurement);
    section.querySelector('[data-new-diary]').addEventListener('click', newDiary);
    section.querySelectorAll('[data-work]').forEach(button => button.addEventListener('click', () => workDetail(button.dataset.work)));
    section.querySelectorAll('[data-measurement]').forEach(button => button.addEventListener('click', () => measurementDetail(button.dataset.measurement)));
    section.querySelectorAll('[data-measurement-action]').forEach(button => button.addEventListener('click', () => measurementAction(button.dataset.id, button.dataset.measurementAction)));
    section.querySelectorAll('.table-app').forEach(UI.enhanceTable);
  }

  async function newRequest() {
    const state = getState();
    const data = await formDialog('Nova solicitacao de compra', `<div class="col-md-6"><label class="form-label" for="requestWork">Obra</label><select id="requestWork" name="workId" class="form-select" required>${state.works.map(item => `<option value="${item.id}">${UI.escapeHtml(item.name)}</option>`).join('')}</select></div><div class="col-md-6"><label class="form-label" for="requestMaterial">Material</label><input id="requestMaterial" name="material" class="form-control" maxlength="120" required></div><div class="col-md-4"><label class="form-label" for="requestQuantity">Quantidade</label><input id="requestQuantity" name="quantity" class="form-control" type="number" min="0.01" step="0.01" required></div><div class="col-md-4"><label class="form-label" for="requestUnit">Unidade</label><select id="requestUnit" name="unit" class="form-select"><option>saco</option><option>metro</option><option>unidade</option><option>m3</option><option>kg</option></select></div><div class="col-md-4"><label class="form-label" for="requestAmount">Valor estimado</label><input id="requestAmount" name="amount" class="form-control" type="number" min="0.01" step="0.01" required></div>`, 'Criar solicitacao', 'Suprimentos');
    if (!data) return;
    Store.mutate('purchase-request.created', entryState => {
      const id = Store.generateId('request');
      entryState.purchaseRequests.push({ id, workId: data.get('workId'), number: `SC-${String(320 + entryState.purchaseRequests.length).padStart(5, '0')}`, material: data.get('material').trim(), quantity: Number(data.get('quantity')), unit: data.get('unit'), estimatedAmount: Number(data.get('amount')), status: 'quotation', createdAt: new Date().toISOString() });
      audit(entryState, 'purchase-request.created', 'purchaseRequests', id, 'Solicitacao de compra criada');
    });
    UI.showToast('Solicitacao enviada para cotacao.', 'success');
    renderPurchases();
  }

  async function quotation(requestId) {
    const state = getState();
    const request = find(state.purchaseRequests, requestId);
    if (!request) return;
    const existing = state.quotations.filter(item => item.purchaseRequestId === requestId);
    const rows = state.suppliers.map(supplier => {
      const quote = existing.find(item => item.supplierId === supplier.id);
      return `<option value="${supplier.id}" data-amount="${quote?.amount || request.estimatedAmount}">${UI.escapeHtml(supplier.name)} · ${money(quote?.amount || request.estimatedAmount)}</option>`;
    }).join('');
    const data = await formDialog('Comparar cotacoes', `<div class="col-12"><div class="quotation-summary"><strong>${UI.escapeHtml(request.material)}</strong><span>${request.quantity} ${request.unit} · estimado em ${money(request.estimatedAmount)}</span></div></div><div class="col-md-8"><label class="form-label" for="quoteSupplier">Fornecedor vencedor</label><select id="quoteSupplier" name="supplierId" class="form-select" required>${rows}</select></div><div class="col-md-4"><label class="form-label" for="quoteAmount">Valor final</label><input id="quoteAmount" name="amount" class="form-control" type="number" min="0.01" step="0.01" value="${existing[0]?.amount || request.estimatedAmount}" required></div>`, 'Selecionar vencedor', 'Cotacao');
    if (!data) return;
    Store.mutate('quotation.selected', entryState => {
      entryState.quotations.filter(item => item.purchaseRequestId === requestId).forEach(item => { item.selected = false; });
      let quote = entryState.quotations.find(item => item.purchaseRequestId === requestId && item.supplierId === data.get('supplierId'));
      if (!quote) { quote = { id: Store.generateId('quotation'), purchaseRequestId: requestId, supplierId: data.get('supplierId'), amount: Number(data.get('amount')), selected: true }; entryState.quotations.push(quote); }
      quote.amount = Number(data.get('amount')); quote.selected = true;
      find(entryState.purchaseRequests, requestId).status = 'approval';
      audit(entryState, 'quotation.selected', 'purchaseRequests', requestId, `Fornecedor ${name(entryState.suppliers, quote.supplierId)} selecionado`);
    });
    UI.showToast('Fornecedor selecionado; compra aguarda aprovacao.', 'success');
    renderPurchases();
  }

  async function advancePurchase(requestId) {
    const state = getState();
    const request = find(state.purchaseRequests, requestId);
    if (!request) return;
    if (request.status === 'quotation') return quotation(requestId);
    const next = request.status === 'approval' ? 'order' : request.status === 'order' ? 'received' : null;
    if (!next) return;
    const message = next === 'order' ? 'Aprovar e emitir o pedido de compra?' : 'Confirmar recebimento e entrada no estoque?';
    if (!await UI.confirmAction({ title: message, message: `${request.number} · ${request.material}`, confirmLabel: next === 'order' ? 'Aprovar' : 'Receber' })) return;
    Store.mutate(`purchase.${next}`, entryState => {
      const target = find(entryState.purchaseRequests, requestId);
      if (target.status !== request.status) throw new Error('A etapa da compra foi alterada em outra acao.');
      target.status = next;
      if (next === 'received') {
        const selected = entryState.quotations.find(item => item.purchaseRequestId === requestId && item.selected);
        let stock = entryState.inventory.find(item => item.workId === target.workId && UI.normalize(item.material) === UI.normalize(target.material));
        if (!stock) { stock = { id: Store.generateId('stock'), workId: target.workId, material: target.material, unit: target.unit, balance: 0, minimum: Math.ceil(target.quantity * 0.2) }; entryState.inventory.push(stock); }
        stock.balance += Number(target.quantity);
        if (selected && !entryState.accountsPayable.some(item => item.purchaseRequestId === requestId)) entryState.accountsPayable.push({ id: Store.generateId('payable'), purchaseRequestId: requestId, supplierId: selected.supplierId, workId: target.workId, description: `${target.number} · ${target.material}`, dueDate: new Date(Date.now() + 15 * 86400000).toISOString().slice(0, 10), amount: selected.amount, status: 'pending' });
      }
      audit(entryState, `purchase.${next}`, 'purchaseRequests', requestId, `Compra avancou para ${next}`);
    });
    UI.showToast(next === 'received' ? 'Material recebido, estoque e financeiro atualizados.' : 'Pedido de compra emitido.', 'success');
    renderPurchases();
  }

  async function newSupplier() {
    const data = await formDialog('Novo fornecedor', `<div class="col-md-6"><label class="form-label" for="supplierName">Razao social / fantasia</label><input id="supplierName" name="name" class="form-control" maxlength="120" required></div><div class="col-md-6"><label class="form-label" for="supplierCnpj">CNPJ</label><input id="supplierCnpj" name="cnpj" class="form-control" data-mask="cnpj" required></div><div class="col-md-6"><label class="form-label" for="supplierPhone">Telefone / WhatsApp</label><input id="supplierPhone" name="phone" class="form-control" data-mask="phone" required></div><div class="col-md-6"><label class="form-label" for="supplierCategory">Categoria</label><input id="supplierCategory" name="category" class="form-control" maxlength="80" required></div><div class="col-12"><label class="form-label" for="supplierEmail">E-mail</label><input id="supplierEmail" name="email" class="form-control" type="email"></div>`, 'Cadastrar fornecedor', 'Suprimentos');
    if (!data) return;
    Store.create('suppliers', { name: data.get('name').trim(), cnpj: data.get('cnpj'), phone: data.get('phone'), category: data.get('category').trim(), email: data.get('email').trim(), rating: 5 });
    UI.showToast('Fornecedor cadastrado.', 'success');
    renderPurchases();
  }

  function renderPurchases() {
    const section = document.querySelector('.content');
    if (!section) return;
    const state = getState();
    const low = state.inventory.filter(item => item.balance < item.minimum);
    section.innerHTML = `<div class="section-head"><div><h2>Compras / Estoque</h2><p>Solicitacao, cotacao, aprovacao, pedido, recebimento e saldo por obra.</p></div><div class="d-flex gap-2"><button class="btn btn-outline-app" data-new-supplier>Novo fornecedor</button><button class="btn btn-primary-app" data-new-request>Nova solicitacao</button></div></div><div class="row g-3 mb-3"><div class="col-md-3"><div class="card-app metric"><div class="label">Solicitacoes abertas</div><div class="value">${state.purchaseRequests.filter(item => !['received', 'cancelled'].includes(item.status)).length}</div><div class="delta">Fluxo de suprimentos</div></div></div><div class="col-md-3"><div class="card-app metric gold"><div class="label">Em cotacao</div><div class="value">${state.purchaseRequests.filter(item => item.status === 'quotation').length}</div><div class="delta">${money(total(state.purchaseRequests.filter(item => item.status === 'quotation').map(item => ({ amount: item.estimatedAmount }))))}</div></div></div><div class="col-md-3"><div class="card-app metric blue"><div class="label">Pedidos emitidos</div><div class="value">${state.purchaseRequests.filter(item => item.status === 'order').length}</div><div class="delta">Aguardando entrega</div></div></div><div class="col-md-3"><div class="card-app metric red"><div class="label">Estoque baixo</div><div class="value">${low.length}</div><div class="delta">${low.map(item => item.material).join(', ') || 'Sem alertas'}</div></div></div></div><div class="card-app mb-3"><div class="card-header"><strong>Fluxo de compras</strong><span class="process-flow"><span>Solicitacao</span><i class="bi bi-arrow-right"></i><span>Cotacao</span><i class="bi bi-arrow-right"></i><span>Aprovacao</span><i class="bi bi-arrow-right"></i><span>Pedido</span><i class="bi bi-arrow-right"></i><span>Recebimento</span></span></div><div class="table-responsive"><table class="table table-app"><thead><tr><th>Solicitacao</th><th>Obra</th><th>Material</th><th>Valor</th><th>Status</th><th>Acoes</th></tr></thead><tbody>${state.purchaseRequests.map(item => `<tr><td><strong>${item.number}</strong></td><td>${UI.escapeHtml(name(state.works, item.workId))}</td><td>${UI.escapeHtml(item.material)}<div class="object-sub">${item.quantity} ${item.unit}</div></td><td>${money(item.estimatedAmount)}</td><td>${badge(item.status)}</td><td>${!['received', 'cancelled'].includes(item.status) ? `<button class="btn btn-sm btn-primary-app" data-advance-purchase="${item.id}">${item.status === 'quotation' ? 'Comparar cotacoes' : item.status === 'approval' ? 'Aprovar' : 'Receber'}</button>` : '<button class="btn btn-sm btn-outline-app" data-receipt-print>Comprovante</button>'}</td></tr>`).join('')}</tbody></table></div></div><div class="row g-3"><div class="col-xl-7"><div class="card-app"><div class="card-header"><strong>Estoque por obra</strong></div><div class="table-responsive"><table class="table table-app"><thead><tr><th>Material</th><th>Obra</th><th>Unidade</th><th>Saldo</th><th>Minimo</th><th>Situacao</th></tr></thead><tbody>${state.inventory.map(item => `<tr><td>${UI.escapeHtml(item.material)}</td><td>${UI.escapeHtml(name(state.works, item.workId))}</td><td>${item.unit}</td><td><strong>${item.balance}</strong></td><td>${item.minimum}</td><td>${item.balance < item.minimum ? '<span class="badge-soft soft-danger">Estoque baixo</span>' : '<span class="badge-soft soft-success">Normal</span>'}</td></tr>`).join('')}</tbody></table></div></div></div><div class="col-xl-5"><div class="card-app"><div class="card-header"><strong>Fornecedores</strong></div><div class="compact-list">${state.suppliers.map(item => `<div><span><strong>${UI.escapeHtml(item.name)}</strong><small>${UI.escapeHtml(item.category)} · ${UI.escapeHtml(item.cnpj)}</small></span><span>${'★'.repeat(Math.round(item.rating || 4))}</span></div>`).join('')}</div></div></div></div>`;
    section.querySelector('[data-new-request]').addEventListener('click', newRequest);
    section.querySelector('[data-new-supplier]').addEventListener('click', newSupplier);
    section.querySelectorAll('[data-advance-purchase]').forEach(button => button.addEventListener('click', () => advancePurchase(button.dataset.advancePurchase)));
    section.querySelectorAll('[data-receipt-print]').forEach(button => button.addEventListener('click', () => root.print()));
    section.querySelectorAll('.table-app').forEach(UI.enhanceTable);
  }

  function init() {
    if (currentPage() === 'engenharia') renderEngineering();
    if (currentPage() === 'compras') renderPurchases();
  }

  document.addEventListener('DOMContentLoaded', init);
  Urbanix.Operations = Object.freeze({ init, renderEngineering, renderPurchases });
})(window);
