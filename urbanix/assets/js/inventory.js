(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const Store = Urbanix.Store;
  const UI = Urbanix.UI;
  const initialized = new Set();
  const enterpriseStatus = {
    launch: ['Lançamento', 'soft-warning'], selling: ['Em comercialização', 'soft-success'],
    building: ['Em obras', 'soft-info'], delivered: ['Entregue', 'soft-success'], archived: ['Arquivado', 'soft-muted']
  };
  const unitStatus = {
    available: ['Disponível', 'soft-success'], reserved: ['Reservado', 'soft-warning'],
    sold: ['Vendido', 'soft-danger'], blocked: ['Bloqueado', 'soft-muted']
  };

  function attr(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;')
      .replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#39;');
  }

  function handled(element, callback, eventName) {
    if (!element) return;
    element.dataset.actionHandled = 'true';
    element.removeAttribute('data-demo-toast');
    element.addEventListener(eventName || 'click', event => {
      event.preventDefault();
      event.stopPropagation();
      callback(event);
    });
  }

  function logChange(state, action, entity, entityId, detail, notification) {
    const now = new Date().toISOString();
    state.audits.push({
      id: Store.generateId('audit'), userId: state.session?.userId || null, action,
      entity, entityId, detail, createdAt: now
    });
    if (notification) state.notifications.push({
      id: Store.generateId('notification'), title: notification.title, detail: notification.detail,
      href: notification.href, read: false, createdAt: now
    });
  }

  function relationUnits(state, enterpriseId) {
    return state.units.filter(unit => unit.enterpriseId === enterpriseId);
  }

  function enterpriseForm(record, onSaved) {
    const item = record || {};
    const form = document.createElement('form');
    form.dataset.actionHandled = 'true';
    form.innerHTML = `<div class="row g-3">
      <div class="col-12"><label class="form-label" for="invEntName">Nome</label><input id="invEntName" name="name" class="form-control" maxlength="120" required value="${attr(item.name)}"></div>
      <div class="col-md-7"><label class="form-label" for="invEntCity">Cidade</label><input id="invEntCity" name="city" class="form-control" maxlength="80" required value="${attr(item.city)}"></div>
      <div class="col-md-5"><label class="form-label" for="invEntState">UF</label><input id="invEntState" name="state" class="form-control" minlength="2" maxlength="2" pattern="[A-Za-z]{2}" required value="${attr(item.state)}"></div>
      <div class="col-md-6"><label class="form-label" for="invEntType">Tipo</label><select id="invEntType" name="type" class="form-select" required>${['loteamento', 'condomínio', 'casas', 'edifício'].map(value => `<option value="${value}" ${item.type === value ? 'selected' : ''}>${value[0].toUpperCase() + value.slice(1)}</option>`).join('')}</select></div>
      <div class="col-md-6"><label class="form-label" for="invEntStatus">Status</label><select id="invEntStatus" name="status" class="form-select" required>${['launch', 'selling', 'building', 'delivered'].concat(item.status === 'archived' ? ['archived'] : []).map(value => `<option value="${value}" ${item.status === value ? 'selected' : ''}>${enterpriseStatus[value][0]}</option>`).join('')}</select></div>
      <div class="col-md-4"><label class="form-label" for="invEntUnits">Total de unidades</label><input id="invEntUnits" name="unitCount" class="form-control" type="number" min="0" step="1" required value="${Number(item.unitCount) || 0}"></div>
      <div class="col-md-4"><label class="form-label" for="invEntProgress">Avanço (%)</label><input id="invEntProgress" name="progress" class="form-control" type="number" min="0" max="100" step="1" required value="${Number(item.progress) || 0}"></div>
      <div class="col-md-4"><label class="form-label" for="invEntVgv">VGV estimado</label><input id="invEntVgv" name="estimatedVgv" class="form-control" type="number" min="0" step="0.01" required value="${Number(item.estimatedVgv) || 0}"></div>
      <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary-app">${record ? 'Salvar alterações' : 'Criar empreendimento'}</button></div>
    </div>`;
    form.addEventListener('submit', event => {
      event.preventDefault();
      event.stopPropagation();
      if (!form.checkValidity()) { form.reportValidity(); return; }
      const data = new FormData(form);
      const values = {
        name: String(data.get('name')).trim(), city: String(data.get('city')).trim(),
        state: String(data.get('state')).trim().toUpperCase(), type: String(data.get('type')),
        status: String(data.get('status')), unitCount: Number(data.get('unitCount')),
        progress: Number(data.get('progress')), estimatedVgv: Number(data.get('estimatedVgv'))
      };
      const allowedTypes = ['loteamento', 'condomínio', 'casas', 'edifício'];
      const allowedStatuses = ['launch', 'selling', 'building', 'delivered', 'archived'];
      if (!values.name || values.name.length > 120 || !values.city || values.city.length > 80 || !/^[A-Z]{2}$/.test(values.state) || !allowedTypes.includes(values.type) || !allowedStatuses.includes(values.status) || !Number.isInteger(values.unitCount) || values.unitCount < 0 || !Number.isFinite(values.progress) || values.progress < 0 || values.progress > 100 || !Number.isFinite(values.estimatedVgv) || values.estimatedVgv < 0) {
        UI.showToast('Revise os campos obrigatórios e os valores informados.', 'danger');
        return;
      }
      try {
        Store.mutate(record ? 'enterprise.updated' : 'enterprise.created', state => {
          const id = record?.id || Store.generateId('enterprise');
          const current = state.enterprises.find(entry => entry.id === id);
          if (record && !current) throw new Error('Empreendimento não encontrado.');
          const saved = { ...(current || {}), ...values, id };
          if (current) Object.assign(current, saved); else state.enterprises.push(saved);
          logChange(state, record ? 'enterprise.updated' : 'enterprise.created', 'enterprise', id,
            `${record ? 'Empreendimento atualizado' : 'Empreendimento criado'}: ${values.name}`,
            { title: record ? 'Empreendimento atualizado' : 'Novo empreendimento', detail: values.name, href: 'empreendimentos.html' });
        });
        form.closest('.urbanix-dialog')?.querySelector('[data-dialog-close]')?.click();
        UI.showToast('Empreendimento salvo com sucesso.', 'success');
        onSaved();
      } catch (error) { UI.showToast(error.message, 'danger'); }
    });
    UI.openModal({ eyebrow: 'Portfólio', title: record ? 'Editar empreendimento' : 'Novo empreendimento', content: form });
  }

  function enterpriseDetails(enterprise) {
    const state = Store.getState();
    const units = relationUnits(state, enterprise.id);
    const counts = Object.keys(unitStatus).reduce((all, status) => ({ ...all, [status]: units.filter(unit => unit.status === status).length }), {});
    const content = document.createElement('div');
    content.innerHTML = `<p class="mb-3">${UI.escapeHtml(enterprise.city)}/${UI.escapeHtml(enterprise.state)} · ${UI.escapeHtml(enterprise.type)}</p><div class="stat-list">
      <div class="stat-row"><span>Status</span><strong>${enterpriseStatus[enterprise.status]?.[0] || UI.escapeHtml(enterprise.status)}</strong></div>
      <div class="stat-row"><span>Total cadastral</span><strong>${Number(enterprise.unitCount).toLocaleString('pt-BR')}</strong></div>
      <div class="stat-row"><span>Unidades registradas</span><strong>${units.length}</strong></div>
      <div class="stat-row"><span>Disponíveis / reservadas</span><strong>${counts.available || 0} / ${counts.reserved || 0}</strong></div>
      <div class="stat-row"><span>Vendidas / bloqueadas</span><strong>${counts.sold || 0} / ${counts.blocked || 0}</strong></div>
      <div class="stat-row"><span>VGV estimado</span><strong>${UI.formatMoney(enterprise.estimatedVgv)}</strong></div>
    </div>`;
    UI.openModal({ eyebrow: 'Empreendimento', title: enterprise.name, content });
  }

  function initEnterprises() {
    const cards = document.querySelector('[data-enterprise-list]');
    const search = document.querySelector('[data-enterprise-search]');
    const statusFilter = document.querySelector('[data-enterprise-status]');
    const typeFilter = document.querySelector('[data-enterprise-type]');
    if (!cards) return;
    function render() {
      const state = Store.getState();
      const term = UI.normalize(search?.value);
      const enterprises = state.enterprises.filter(item => (!term || UI.normalize(`${item.name} ${item.city} ${item.state} ${item.type}`).includes(term)) && (!statusFilter?.value || item.status === statusFilter.value) && (!typeFilter?.value || item.type === typeFilter.value));
      cards.innerHTML = enterprises.map(item => {
        const units = relationUnits(state, item.id);
        const available = units.filter(unit => unit.status === 'available').length;
        const sold = units.filter(unit => unit.status === 'sold').length;
        const badge = enterpriseStatus[item.status] || [item.status, 'soft-muted'];
        return `<div class="col-xl-4 col-md-6"><article class="card-app h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start gap-2"><span class="badge-soft ${badge[1]}">${UI.escapeHtml(badge[0])}</span><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-app" data-enterprise-edit="${attr(item.id)}" aria-label="Editar ${attr(item.name)}"><i class="bi bi-pencil"></i></button>${item.status === 'archived' ? '' : `<button type="button" class="btn btn-sm btn-outline-app" data-enterprise-archive="${attr(item.id)}" aria-label="Arquivar ${attr(item.name)}"><i class="bi bi-archive"></i></button>`}</div></div><h4 class="mt-3 mb-1 fw-bold">${UI.escapeHtml(item.name)}</h4><div class="text-secondary">${UI.escapeHtml(item.city)} - ${UI.escapeHtml(item.state)} · ${UI.escapeHtml(item.type)}</div><div class="row g-2 mt-3"><div class="col-4"><small class="text-secondary">Total</small><strong class="d-block">${Number(item.unitCount).toLocaleString('pt-BR')}</strong></div><div class="col-4"><small class="text-secondary">Vendidas*</small><strong class="d-block">${sold}</strong></div><div class="col-4"><small class="text-secondary">Disponíveis*</small><strong class="d-block">${available}</strong></div></div><div class="mt-4"><div class="d-flex justify-content-between small mb-1"><span>Avanço da obra</span><strong>${Number(item.progress)}%</strong></div><div class="progress progress-thin"><div class="progress-bar" style="width:${Math.max(0, Math.min(100, Number(item.progress)))}%"></div></div></div><hr><div class="d-flex justify-content-between align-items-end"><div><small class="text-secondary">VGV estimado</small><strong class="d-block">${UI.formatMoney(item.estimatedVgv)}</strong></div><button type="button" class="btn btn-outline-app" data-enterprise-detail="${attr(item.id)}">Detalhar <i class="bi bi-arrow-right ms-1"></i></button></div><small class="d-block text-secondary mt-2">*Unidades registradas no protótipo</small></div></article></div>`;
      }).join('');
      if (!enterprises.length) cards.appendChild(UI.emptyState('Nenhum empreendimento encontrado', 'Ajuste os filtros ou crie um novo empreendimento.'));
      cards.querySelectorAll('[data-enterprise-edit]').forEach(button => handled(button, () => enterpriseForm(Store.find('enterprises', button.dataset.enterpriseEdit), render)));
      cards.querySelectorAll('[data-enterprise-detail]').forEach(button => handled(button, () => { const item = Store.find('enterprises', button.dataset.enterpriseDetail); if (item) enterpriseDetails(item); }));
      cards.querySelectorAll('[data-enterprise-archive]').forEach(button => handled(button, async () => {
        const item = Store.find('enterprises', button.dataset.enterpriseArchive);
        if (!item || !await UI.confirmAction({ title: `Arquivar ${item.name}?`, message: 'O cadastro e suas unidades serão preservados, mas o empreendimento ficará inativo para novos fluxos.', confirmLabel: 'Arquivar', danger: true })) return;
        try {
          Store.mutate('enterprise.archived', state => {
            const target = state.enterprises.find(entry => entry.id === item.id);
            if (!target) throw new Error('Empreendimento não encontrado.');
            target.status = 'archived';
            logChange(state, 'enterprise.archived', 'enterprise', target.id, `Empreendimento arquivado: ${target.name}`, { title: 'Empreendimento arquivado', detail: target.name, href: 'empreendimentos.html' });
          });
          UI.showToast('Empreendimento arquivado sem excluir seus vínculos.', 'success'); render();
        } catch (error) { UI.showToast(error.message, 'danger'); }
      }));
    }
    handled(document.querySelector('[data-enterprise-new]'), () => enterpriseForm(null, render));
    [search, statusFilter, typeFilter].forEach(control => control?.addEventListener(control === search ? 'input' : 'change', render));
    render();
  }

  function dispatchCommercial(action, unit) {
    root.dispatchEvent(new CustomEvent('urbanix:commercial-action', { detail: { action, unitId: unit.id, enterpriseId: unit.enterpriseId } }));
    const destination = action === 'reservation.requested' ? 'reservas.html' : 'propostas.html';
    root.location.assign(`${destination}?action=new&unitId=${encodeURIComponent(unit.id)}`);
  }

  function initMap() {
    const grid = document.querySelector('[data-unit-map]');
    const enterpriseSelect = document.querySelector('[data-map-enterprise]');
    if (!grid || !enterpriseSelect) return;
    let selectedUnitId = null;
    const state = Store.getState();
    const withUnits = state.enterprises.filter(item => item.status !== 'archived' && state.units.some(unit => unit.enterpriseId === item.id));
    enterpriseSelect.innerHTML = withUnits.map(item => `<option value="${attr(item.id)}">${UI.escapeHtml(item.name)}</option>`).join('');
    function selectedUnit() { return Store.find('units', selectedUnitId); }
    function renderDetail(unit) {
      const current = Store.getState();
      const enterprise = current.enterprises.find(item => item.id === unit.enterpriseId);
      const block = current.blocks.find(item => item.id === unit.blockId);
      const badge = unitStatus[unit.status] || [unit.status, 'soft-muted'];
      document.querySelector('[data-lot-status]').textContent = badge[0];
      document.querySelector('[data-lot-status]').className = `badge-soft ${badge[1]}`;
      document.querySelector('[data-lot-code]').textContent = unit.code;
      document.querySelector('[data-lot-context]').textContent = `${block ? `Quadra ${block.code} · ` : ''}${enterprise?.name || 'Empreendimento'}`;
      document.querySelector('[data-lot-area]').textContent = `${Number(unit.area).toLocaleString('pt-BR')} m²`;
      document.querySelector('[data-lot-frontage]').textContent = unit.frontage ? `${unit.frontage} m` : '—';
      document.querySelector('[data-lot-depth]').textContent = unit.depth ? `${unit.depth} m` : '—';
      document.querySelector('[data-lot-value]').textContent = UI.formatMoney(unit.listPrice);
      document.querySelector('[data-lot-square-price]').textContent = UI.formatMoney(unit.area ? unit.listPrice / unit.area : 0);
      const reserve = document.querySelector('[data-unit-reserve]');
      const propose = document.querySelector('[data-unit-propose]');
      const blockButton = document.querySelector('[data-unit-block]');
      reserve.disabled = unit.status !== 'available';
      propose.disabled = unit.status === 'sold' || unit.status === 'blocked';
      blockButton.disabled = unit.status === 'sold' || unit.status === 'reserved';
      blockButton.textContent = unit.status === 'blocked' ? 'Desbloquear unidade' : 'Bloquear unidade';
    }
    function render() {
      const current = Store.getState();
      const enterprise = current.enterprises.find(item => item.id === enterpriseSelect.value);
      const units = current.units.filter(unit => unit.enterpriseId === enterpriseSelect.value);
      document.querySelectorAll('[data-map-enterprise-name]').forEach(item => { item.textContent = enterprise?.name || 'Empreendimento'; });
      document.querySelector('[data-map-subtitle]').textContent = `${new Set(units.map(unit => unit.blockId).filter(Boolean)).size} quadras · ${units.length} unidades registradas`;
      if (!units.some(unit => unit.id === selectedUnitId)) selectedUnitId = units[0]?.id || null;
      grid.innerHTML = units.map(unit => `<button type="button" class="lot ${attr(unit.status)} ${unit.id === selectedUnitId ? 'selected' : ''}" data-unit-id="${attr(unit.id)}" data-action-handled="true" style="text-align:center"><strong>${UI.escapeHtml(unit.code)}</strong><small>${Number(unit.area).toLocaleString('pt-BR')} m²</small></button>`).join('');
      if (!units.length) grid.appendChild(UI.emptyState('Sem unidades cadastradas', 'Escolha outro empreendimento ou cadastre suas unidades.'));
      grid.querySelectorAll('[data-unit-id]').forEach(lot => handled(lot, () => { selectedUnitId = lot.dataset.unitId; render(); }));
      const unit = units.find(item => item.id === selectedUnitId);
      document.querySelector('[data-lot-detail]').hidden = !unit;
      if (unit) renderDetail(unit);
    }
    enterpriseSelect.addEventListener('change', () => { selectedUnitId = null; render(); });
    handled(document.querySelector('[data-map-print]'), () => root.print());
    handled(document.querySelector('[data-unit-reserve]'), () => { const unit = selectedUnit(); if (unit?.status === 'available') dispatchCommercial('reservation.requested', unit); });
    handled(document.querySelector('[data-map-new-reservation]'), () => { const unit = selectedUnit(); if (unit?.status === 'available') dispatchCommercial('reservation.requested', unit); else UI.showToast('Selecione uma unidade disponível para reservar.', 'info'); });
    handled(document.querySelector('[data-unit-propose]'), () => { const unit = selectedUnit(); if (unit && !['sold', 'blocked'].includes(unit.status)) dispatchCommercial('proposal.requested', unit); });
    handled(document.querySelector('[data-unit-block]'), async () => {
      const unit = selectedUnit();
      if (!unit || !['available', 'blocked'].includes(unit.status)) return;
      const willBlock = unit.status === 'available';
      if (!await UI.confirmAction({ title: `${willBlock ? 'Bloquear' : 'Desbloquear'} a unidade ${unit.code}?`, message: willBlock ? 'A unidade deixará de aceitar novas reservas e propostas.' : 'A unidade voltará a ficar disponível para o fluxo comercial.', confirmLabel: willBlock ? 'Bloquear' : 'Desbloquear', danger: willBlock })) return;
      try {
        Store.mutate(willBlock ? 'unit.blocked' : 'unit.unblocked', current => {
          const target = current.units.find(item => item.id === unit.id);
          if (!target || !['available', 'blocked'].includes(target.status)) throw new Error('A situação da unidade foi alterada. Atualize a seleção.');
          target.status = willBlock ? 'blocked' : 'available';
          logChange(current, willBlock ? 'unit.blocked' : 'unit.unblocked', 'unit', target.id, `Unidade ${target.code} ${willBlock ? 'bloqueada' : 'desbloqueada'}`, { title: willBlock ? 'Unidade bloqueada' : 'Unidade disponível', detail: target.code, href: 'mapa-unidades.html' });
        });
        UI.showToast(`Unidade ${willBlock ? 'bloqueada' : 'desbloqueada'} com sucesso.`, 'success'); render();
      } catch (error) { UI.showToast(error.message, 'danger'); }
    });
    render();
  }

  function initPrices() {
    const table = document.querySelector('[data-price-table]');
    if (!table) return;
    table.dataset.enhanced = 'true';
    const tbody = table.tBodies[0];
    const enterprise = document.querySelector('[data-price-enterprise]');
    const block = document.querySelector('[data-price-block]');
    const search = document.querySelector('[data-price-search]');
    const selected = new Set();
    const state = Store.getState();
    enterprise.innerHTML = `<option value="">Todos os empreendimentos</option>${state.enterprises.filter(item => item.status !== 'archived' && state.units.some(unit => unit.enterpriseId === item.id)).map(item => `<option value="${attr(item.id)}">${UI.escapeHtml(item.name)}</option>`).join('')}`;
    function syncBlocks() {
      const current = Store.getState();
      const blocks = current.blocks.filter(item => !enterprise.value || item.enterpriseId === enterprise.value);
      const old = block.value;
      block.innerHTML = `<option value="">Todas as quadras</option>${blocks.map(item => `<option value="${attr(item.id)}">Quadra ${UI.escapeHtml(item.code)}</option>`).join('')}`;
      if (blocks.some(item => item.id === old)) block.value = old;
    }
    function eligible(unit) { return unit.status !== 'sold'; }
    function render() {
      const current = Store.getState();
      const units = current.units.filter(unit => (!enterprise.value || unit.enterpriseId === enterprise.value) && (!block.value || unit.blockId === block.value) && (!search.value || UI.normalize(unit.code).includes(UI.normalize(search.value))));
      tbody.innerHTML = units.map(unit => {
        const status = unitStatus[unit.status] || [unit.status, 'soft-muted'];
        return `<tr data-unit-row="${attr(unit.id)}"><td><input class="form-check-input me-2" type="checkbox" data-price-select="${attr(unit.id)}" aria-label="Selecionar unidade ${attr(unit.code)}" ${selected.has(unit.id) ? 'checked' : ''} ${eligible(unit) ? '' : 'disabled'}><strong>${UI.escapeHtml(unit.code)}</strong></td><td>${Number(unit.area).toLocaleString('pt-BR')} m²</td><td>${UI.formatMoney(unit.listPrice)}</td><td>${UI.formatMoney(unit.area ? unit.listPrice / unit.area : 0)}</td><td><span class="badge-soft ${status[1]}">${status[0]}</span></td><td><button type="button" class="btn btn-sm btn-outline-app" data-price-simulate="${attr(unit.id)}" data-action-handled="true">Simular</button></td></tr>`;
      }).join('');
      document.querySelector('[data-price-empty]').hidden = units.length > 0;
      table.closest('.table-responsive').hidden = units.length === 0;
      tbody.querySelectorAll('[data-price-select]').forEach(input => input.addEventListener('change', () => { if (input.checked) selected.add(input.dataset.priceSelect); else selected.delete(input.dataset.priceSelect); updateSelection(); }));
      tbody.querySelectorAll('[data-price-simulate]').forEach(button => handled(button, () => simulate(Store.find('units', button.dataset.priceSimulate))));
      updateSelection();
    }
    function updateSelection() {
      const visible = Array.from(tbody.querySelectorAll('[data-price-select]:not(:disabled)'));
      const checked = visible.filter(input => input.checked);
      const selectAll = document.querySelector('[data-price-select-all]');
      selectAll.checked = visible.length > 0 && checked.length === visible.length;
      selectAll.indeterminate = checked.length > 0 && checked.length < visible.length;
      document.querySelector('[data-price-selected-count]').textContent = `${selected.size} selecionada${selected.size === 1 ? '' : 's'}`;
    }
    function simulate(unit) {
      if (!unit) return;
      const content = document.createElement('div');
      content.innerHTML = `<p><strong>${UI.escapeHtml(unit.code)}</strong> · Preço atual ${UI.formatMoney(unit.listPrice)}</p><div class="row g-3"><div class="col-md-6"><label class="form-label" for="simDown">Entrada (%)</label><input id="simDown" class="form-control" type="number" min="0" max="90" step="0.1" value="20"></div><div class="col-md-6"><label class="form-label" for="simTerms">Parcelas</label><input id="simTerms" class="form-control" type="number" min="1" max="240" step="1" value="120"></div></div><div class="stat-list mt-4"><div class="stat-row"><span>Entrada estimada</span><strong data-sim-down></strong></div><div class="stat-row"><span>Saldo simulado</span><strong data-sim-balance></strong></div><div class="stat-row"><span>Parcela sem juros</span><strong data-sim-payment></strong></div></div><small class="d-block mt-3">Simulação informativa; não cria proposta nem aplica regras financeiras.</small>`;
      const calculate = () => {
        const percent = Math.max(0, Math.min(90, Number(content.querySelector('#simDown').value) || 0));
        const terms = Math.max(1, Math.min(240, Math.trunc(Number(content.querySelector('#simTerms').value) || 1)));
        const down = unit.listPrice * percent / 100; const balance = unit.listPrice - down;
        content.querySelector('[data-sim-down]').textContent = UI.formatMoney(down);
        content.querySelector('[data-sim-balance]').textContent = UI.formatMoney(balance);
        content.querySelector('[data-sim-payment]').textContent = UI.formatMoney(balance / terms);
      };
      content.querySelectorAll('input').forEach(input => input.addEventListener('input', calculate)); calculate();
      UI.openModal({ eyebrow: 'Simulação comercial', title: `Unidade ${unit.code}`, content });
    }
    function bulkAdjustment() {
      const validIds = Array.from(selected).filter(id => { const unit = Store.find('units', id); return unit && eligible(unit); });
      if (!validIds.length) { UI.showToast('Selecione ao menos uma unidade não vendida.', 'info', 'Reajuste de preços'); return; }
      const form = document.createElement('form');
      form.dataset.actionHandled = 'true';
      form.innerHTML = `<p>O reajuste será aplicado a <strong>${validIds.length}</strong> unidade${validIds.length === 1 ? '' : 's'}.</p><label class="form-label" for="bulkPercent">Percentual de reajuste</label><div class="input-group"><input id="bulkPercent" name="percent" class="form-control" type="number" min="-99" max="100" step="0.01" required><span class="input-group-text">%</span></div><small class="d-block mt-2">Use percentual negativo para redução. Unidades vendidas não podem ser alteradas.</small><div class="d-flex justify-content-end mt-4"><button type="submit" class="btn btn-primary-app">Aplicar reajuste</button></div>`;
      form.addEventListener('submit', event => {
        event.preventDefault(); event.stopPropagation();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const percent = Number(new FormData(form).get('percent'));
        if (!Number.isFinite(percent) || percent <= -100 || percent > 100 || percent === 0) { UI.showToast('Informe um percentual entre -99% e 100%, diferente de zero.', 'danger'); return; }
        try {
          Store.mutate('units.prices-adjusted', current => {
            validIds.forEach(id => {
              const unit = current.units.find(item => item.id === id);
              if (!unit || unit.status === 'sold') throw new Error('Uma das unidades selecionadas não pode mais ser reajustada.');
              const adjusted = Math.round(Number(unit.listPrice) * (1 + percent / 100) * 100) / 100;
              if (!Number.isFinite(adjusted) || adjusted <= 0) throw new Error('O reajuste produziria um preço inválido.');
              unit.listPrice = adjusted;
            });
            logChange(current, 'units.prices-adjusted', 'unit', validIds.join(','), `Reajuste de ${percent}% aplicado em ${validIds.length} unidade(s)`, { title: 'Tabela de preços atualizada', detail: `${validIds.length} unidade(s) · ${percent}%`, href: 'tabela-precos.html' });
          });
          form.closest('.urbanix-dialog')?.querySelector('[data-dialog-close]')?.click();
          UI.showToast('Preços reajustados e registrados na auditoria.', 'success'); selected.clear(); render();
        } catch (error) { UI.showToast(error.message, 'danger'); }
      });
      UI.openModal({ eyebrow: 'Tabela de preços', title: 'Reajuste em massa', content: form });
    }
    handled(document.querySelector('[data-price-adjust]'), bulkAdjustment);
    handled(document.querySelector('[data-price-select-all]'), event => {
      tbody.querySelectorAll('[data-price-select]:not(:disabled)').forEach(input => { input.checked = event.currentTarget.checked; if (input.checked) selected.add(input.dataset.priceSelect); else selected.delete(input.dataset.priceSelect); }); updateSelection();
    }, 'change');
    enterprise.addEventListener('change', () => { syncBlocks(); render(); });
    block.addEventListener('change', render); search.addEventListener('input', render);
    syncBlocks(); render();
  }

  function init(page) {
    const name = page || document.body?.dataset.page;
    if (!name || initialized.has(name)) return false;
    const handlers = { empreendimentos: initEnterprises, 'mapa-unidades': initMap, 'tabela-precos': initPrices };
    if (!handlers[name]) return false;
    initialized.add(name); handlers[name](); return true;
  }

  Urbanix.Inventory = Object.freeze({ init });
})(window);
