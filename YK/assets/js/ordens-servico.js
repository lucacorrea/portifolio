document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const dataNode = document.getElementById('os-page-data');
  const pageData = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
  const serviceOptions = pageData.services || [];
  const productOptions = pageData.products || [];
  const employeeOptions = pageData.employees || [];
  const recoveryModal = pageData.recoveryModal || new URLSearchParams(window.location.search).get('modal');
  const recoveryData = pageData.recoveryData || {};
  const recoveryError = pageData.recoveryError || '';

  function parseNumber(value) {
    value = String(value || '0').replace(/\s/g, '');
    if (value.includes(',')) value = value.replace(/\./g, '').replace(',', '.');
    return Math.max(0, Number.parseFloat(value) || 0);
  }

  function money(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  function toLocalInput(value) {
    if (!value) return '';
    return String(value).replace(' ', 'T').slice(0, 16);
  }

  function field(row, name) {
    return row.querySelector('[data-field="' + name + '"]');
  }

  function parseLocalDateTime(value) {
    const normalized = String(value || '').trim().replace(' ', 'T').slice(0, 16);
    const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
    if (!match) return null;
    const date = new Date(
      Number(match[1]),
      Number(match[2]) - 1,
      Number(match[3]),
      Number(match[4]),
      Number(match[5]),
      0,
      0
    );
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function localInputFromDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const pad = function (value) { return String(value).padStart(2, '0'); };
    return date.getFullYear()
      + '-' + pad(date.getMonth() + 1)
      + '-' + pad(date.getDate())
      + 'T' + pad(date.getHours())
      + ':' + pad(date.getMinutes());
  }

  function durationBetween(startValue, endValue) {
    const start = parseLocalDateTime(startValue);
    const end = parseLocalDateTime(endValue);
    if (!start || !end || end <= start) return 0;
    return Math.max(0, Math.round((end.getTime() - start.getTime()) / 60000));
  }

  function scheduleFields(form) {
    if (!form) return {};
    return {
      start: form.querySelector('[data-os-schedule-start]'),
      duration: form.querySelector('[data-os-schedule-duration]'),
      end: form.querySelector('[data-os-schedule-end]'),
      summary: form.querySelector('[data-os-schedule-summary]'),
    };
  }

  function suggestedServiceDuration(form) {
    if (!form) return 60;
    let total = 0;
    form.querySelectorAll('.os-item-row').forEach(function (row) {
      const typeInput = field(row, 'type');
      if (!typeInput || typeInput.value !== 'servico') return;
      const select = field(row, 'reference_id');
      const option = select?.selectedOptions?.[0];
      const duration = Number.parseInt(option?.dataset.durationMinutes || '0', 10);
      if (!Number.isFinite(duration) || duration <= 0) return;
      const quantity = Math.max(1, parseNumber(field(row, 'quantity')?.value || '1'));
      total += duration * quantity;
    });
    if (!Number.isFinite(total) || total <= 0) return 60;
    return Math.min(1440, Math.max(5, Math.round(total)));
  }

  function syncScheduleEnd(form) {
    const controls = scheduleFields(form);
    if (!controls.start || !controls.duration || !controls.end) return;

    const startDate = parseLocalDateTime(controls.start.value);
    const duration = Number.parseInt(String(controls.duration.value || ''), 10);

    if (!startDate || !Number.isFinite(duration) || duration < 5 || duration > 1440) {
      controls.end.value = '';
      if (controls.summary) {
        controls.summary.textContent = 'Informe o horário e uma duração entre 5 minutos e 24 horas.';
      }
      return;
    }

    const endDate = new Date(startDate.getTime() + duration * 60000);
    controls.end.value = localInputFromDate(endDate);

    if (controls.summary) {
      const dateLabel = startDate.toLocaleDateString('pt-BR');
      const startLabel = startDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
      const endLabel = endDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
      controls.summary.textContent = 'Agenda protegida em ' + dateLabel + ', das ' + startLabel + ' às ' + endLabel + '. Outras OS fora desse intervalo não bloqueiam o funcionário.';
    }
  }

  function refreshScheduleSuggestion(form, force) {
    const controls = scheduleFields(form);
    if (!controls.duration) return;
    const canSuggest = force === true
      || controls.duration.value === ''
      || controls.duration.dataset.userEdited !== 'true';
    if (canSuggest) {
      controls.duration.value = String(suggestedServiceDuration(form));
      controls.duration.dataset.userEdited = 'false';
    }
    syncScheduleEnd(form);
  }

  function restoreSchedule(form, startValue, endValue, durationValue) {
    const controls = scheduleFields(form);
    if (!controls.start || !controls.duration || !controls.end) return;

    controls.start.value = toLocalInput(startValue);
    const explicitDuration = Number.parseInt(String(durationValue || ''), 10);
    const storedDuration = durationBetween(startValue, endValue);
    const duration = Number.isFinite(explicitDuration) && explicitDuration >= 5
      ? explicitDuration
      : storedDuration >= 5
        ? storedDuration
        : suggestedServiceDuration(form);

    controls.duration.value = String(Math.min(1440, Math.max(5, duration || 60)));
    controls.duration.dataset.userEdited = storedDuration >= 5 || explicitDuration >= 5 ? 'true' : 'false';
    syncScheduleEnd(form);
  }

  function bindScheduleControls(form) {
    const controls = scheduleFields(form);
    if (!controls.start || !controls.duration || !controls.end) return;

    if (controls.duration.value === '') {
      controls.duration.value = String(suggestedServiceDuration(form));
      controls.duration.dataset.userEdited = 'false';
    }

    controls.start.addEventListener('input', function () {
      syncScheduleEnd(form);
    });

    controls.duration.addEventListener('input', function () {
      controls.duration.dataset.userEdited = 'true';
      syncScheduleEnd(form);
    });

    form.addEventListener('submit', function () {
      syncScheduleEnd(form);
    });
  }

  function setItemNames(row, type, index) {
    const group = type === 'servico' ? 'services' : type === 'produto' ? 'products' : 'others';
    row.querySelectorAll('[data-field]').forEach(function (input) {
      input.name = group + '[' + index + '][' + input.dataset.field + ']';
    });
  }

  function teamField(row, name) {
    return row.querySelector('[data-team-field="' + name + '"]');
  }

  function canonicalTeamRole(value) {
    const role = String(value || 'Técnico').trim();
    const normalized = role.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    if (normalized === 'responsavel tecnico') return 'Responsável técnico';
    if (normalized === 'tecnico') return 'Técnico';
    return role || 'Técnico';
  }

  function setTeamNames(container) {
    container.querySelectorAll('.os-team-member-row').forEach(function (row, index) {
      row.querySelectorAll('[data-team-field]').forEach(function (input) {
        input.name = 'team_members[' + index + '][' + input.dataset.teamField + ']';
      });
      const radio = teamField(row, 'principal');
      if (radio) radio.name = 'team_members[' + index + '][principal]';
    });
  }

  function updateTeamDuplicates(container) {
    const values = Array.from(container.querySelectorAll('[data-team-field="funcionario_id"]')).map(function (select) { return select.value; });
    container.querySelectorAll('[data-team-field="funcionario_id"]').forEach(function (select) {
      select.querySelectorAll('option').forEach(function (option) {
        option.disabled = option.value !== '' && option.value !== select.value && values.includes(option.value);
      });
    });
  }

  function addTeamMember(form, member) {
    const template = document.getElementById('os-team-member-template');
    const container = form.querySelector('[data-team-members]');
    if (!template || !container) return;

    const row = template.content.firstElementChild.cloneNode(true);
    const employeeSelect = teamField(row, 'funcionario_id');
    employeeOptions.forEach(function (employee) {
      employeeSelect.appendChild(new Option(employee.name, employee.id));
    });

    employeeSelect.value = member?.employee_id || '';
    const roleSelect = teamField(row, 'funcao');
    const role = canonicalTeamRole(member?.role || member?.funcao);
    if (!Array.from(roleSelect.options).some(function (option) { return option.value === role; })) {
      roleSelect.appendChild(new Option(role, role));
    }
    roleSelect.value = role;
    teamField(row, 'principal').checked = Boolean(member?.primary || member?.principal);

    row.querySelector('.js-os-remove-team-member').addEventListener('click', function () {
      row.remove();
      if (!container.querySelector('[data-team-field="principal"]:checked')) {
        const firstPrincipal = container.querySelector('[data-team-field="principal"]');
        if (firstPrincipal) firstPrincipal.checked = true;
      }
      setTeamNames(container);
      updateTeamDuplicates(container);
    });
    row.addEventListener('change', function (event) {
      if (event.target.matches('[data-team-field="principal"]') && event.target.checked) {
        container.querySelectorAll('[data-team-field="principal"]').forEach(function (radio) {
          if (radio !== event.target) radio.checked = false;
        });
      }
      setTeamNames(container);
      updateTeamDuplicates(container);
    });

    container.appendChild(row);
    if (!member && !container.querySelector('[data-team-field="principal"]:checked')) {
      teamField(row, 'principal').checked = true;
    }
    if (container.dataset.teamEditable === '0') {
      row.querySelectorAll('input, select, button').forEach(function (control) { control.disabled = true; });
    }
    setTeamNames(container);
    updateTeamDuplicates(container);
  }

  function restoreTeamMembers(form, members) {
    const container = form.querySelector('[data-team-members]');
    if (!container) return;
    container.replaceChildren();
    (members || []).forEach(function (member) { addTeamMember(form, member); });
  }

  function optionsFor(type) {
    return type === 'servico' ? serviceOptions : type === 'produto' ? productOptions : [];
  }

  function recalc(form) {
    const sums = { servico: 0, produto: 0, outro: 0 };
    form.querySelectorAll('.os-item-row').forEach(function (row) {
      const type = field(row, 'type').value;
      const subtotal = Math.max(0, parseNumber(field(row, 'quantity').value) * parseNumber(field(row, 'unit_price').value) - parseNumber(field(row, 'discount').value));
      field(row, 'subtotal').value = money(subtotal);
      sums[type] += subtotal;
    });
    const discount = parseNumber(form.querySelector('.js-os-discount')?.value);
    const increase = parseNumber(form.querySelector('.js-os-increase')?.value);
    const total = Math.max(0, sums.servico + sums.produto + sums.outro - discount + increase);
    Object.entries({ servico: sums.servico, produto: sums.produto, outro: sums.outro, discount, increase, total }).forEach(function ([key, value]) {
      const target = form.querySelector('[data-summary="' + key + '"]');
      if (target) target.textContent = money(value);
    });
    refreshScheduleSuggestion(form, false);
  }

  function addRow(form, type, item) {
    const template = document.getElementById('os-item-template');
    if (!template) return;
    const row = template.content.firstElementChild.cloneNode(true);
    const container = form.querySelector('[data-os-items="' + type + '"]');
    const index = container.children.length;
    field(row, 'type').value = type;
    setItemNames(row, type, index);
    const select = field(row, 'reference_id');
    const referenceWrap = row.querySelector('.os-reference-wrap');
    const referenceLabel = row.querySelector('[data-os-reference-label]');
    const descriptionWrap = row.querySelector('.os-description-wrap');
    const descriptionLabel = row.querySelector('[data-os-description-label]');
    const descriptionInput = field(row, 'description');
    const locationWrap = row.querySelector('.os-execution-location-wrap');
    const locationInput = field(row, 'execution_location');

    row.classList.add('is-' + type);

    if (type === 'servico') {
      if (referenceLabel) referenceLabel.textContent = 'Serviço realizado';
      descriptionWrap?.classList.add('d-none');
      locationWrap?.classList.remove('d-none');
      if (locationInput) locationInput.disabled = false;
      descriptionInput.readOnly = true;
    } else if (type === 'produto') {
      if (referenceLabel) referenceLabel.textContent = 'Produto / peça';
      if (descriptionLabel) descriptionLabel.textContent = 'Descrição';
      locationWrap?.classList.add('d-none');
      if (locationInput) locationInput.disabled = true;
    } else {
      if (descriptionLabel) descriptionLabel.textContent = 'Descrição do item';
      locationWrap?.classList.add('d-none');
      if (locationInput) locationInput.disabled = true;
    }

    if (type === 'outro') {
      referenceWrap.classList.add('d-none');
      select.appendChild(new Option('Personalizado', ''));
    } else {
      select.appendChild(new Option('Selecione', ''));
      optionsFor(type).forEach(function (option) {
        const opt = new Option(option.name, option.id);
        opt.dataset.itemName = option.name || '';
        opt.dataset.description = option.description || option.name;
        opt.dataset.unit = option.unit || 'un';
        opt.dataset.value = option.value || '0.00';
        opt.dataset.durationMinutes = String(option.duration_minutes || 0);
        select.appendChild(opt);
      });
    }
    if (item) {
      const referenceValue = item.reference_id || '';
      if (referenceValue && !Array.from(select.options).some(function (option) { return option.value === String(referenceValue); })) {
        select.appendChild(new Option('Item atual — inativo ou indisponível', String(referenceValue)));
      }
      field(row, 'id').value = item.id || '';
      field(row, 'origin').value = item.origin || 'manual';
      field(row, 'budget_item_id').value = item.budget_item_id || '';
      select.value = item.reference_id || '';
      field(row, 'description').value = item.description || '';
      if (locationInput) locationInput.value = item.execution_location || '';
      field(row, 'unit').value = item.unit || 'un';
      field(row, 'quantity').value = item.quantity || '1';
      field(row, 'unit_price').value = item.unit_price || '0,00';
      field(row, 'discount').value = item.discount || '0,00';
    }

    if (type === 'servico' && !field(row, 'description').value && select.selectedOptions[0]?.value) {
      const selectedOption = select.selectedOptions[0];
      field(row, 'description').value = selectedOption.dataset.itemName || selectedOption.textContent || '';
    }

    select.addEventListener('change', function () {
      const opt = select.selectedOptions[0];
      if (!opt) return;
      if (type === 'servico') {
        field(row, 'description').value = opt.value
          ? (opt.dataset.itemName || opt.textContent || '')
          : '';
      } else if (!field(row, 'description').value) {
        field(row, 'description').value = opt.dataset.description || opt.textContent;
      }
      field(row, 'unit').value = opt.dataset.unit || field(row, 'unit').value || 'un';
      field(row, 'unit_price').value = opt.dataset.value || '0,00';
      recalc(form);
    });
    row.addEventListener('input', function () { recalc(form); });
    row.querySelector('.js-os-remove-item').addEventListener('click', function () {
      row.remove();
      recalc(form);
    });
    container.appendChild(row);
    recalc(form);
  }

  function hasRecoveredItems(data) {
    return ['services', 'products', 'others'].some(function (key) {
      return Array.isArray(data[key]) && data[key].length > 0;
    });
  }

  function restoreItems(form, data) {
    form.querySelectorAll('.os-items').forEach(function (box) { box.replaceChildren(); });
    (data.services || []).forEach(function (item) { addRow(form, 'servico', item); });
    (data.products || []).forEach(function (item) { addRow(form, 'produto', item); });
    (data.others || []).forEach(function (item) { addRow(form, 'outro', item); });
    recalc(form);
  }

  function restoreOrderForm(form, data) {
    [
      ['os-id', 'id'],
      ['os-client', 'client_id'],
      ['os-budget-id', 'budget_id'],
      ['os-status', 'status'],
      ['os-priority', 'priority'],
      ['os-equipment-type', 'equipment_type'],
      ['os-equipment-brand', 'equipment_brand'],
      ['os-equipment-model', 'equipment_model'],
      ['os-equipment-capacity', 'equipment_capacity'],
      ['os-equipment-serial-number', 'equipment_serial_number'],
      ['os-equipment-environment', 'equipment_environment'],
      ['os-equipment-location', 'equipment_location'],
      ['os-reported-problem', 'reported_problem'],
      ['os-identified-problem', 'identified_problem'],
      ['os-diagnosis', 'diagnosis'],
      ['os-solution', 'solution'],
      ['os-recommendation', 'recommendation'],
      ['os-internal-notes', 'internal_notes'],
      ['os-notes', 'notes'],
      ['os-discount', 'discount'],
      ['os-increase', 'increase'],
    ].forEach(function (pair) {
      setValue(pair[0], data[pair[1]]);
    });
    setValue('os-creation-mode', data.creation_mode || 'manual');
    if (hasRecoveredItems(data)) restoreItems(form, data);
    restoreTeamMembers(form, data.team_members || data.equipe || legacyTeamFromData(data));
    restoreSchedule(form, data.agendado_inicio, data.agendado_fim, data.agendamento_duracao_minutos);
    updateBudgetMode(form);
    recalc(form);
  }

  function restoreTeamForm(data) {
    setValue('os-team-id', data.id);
    const form = document.querySelector('#modal-os-team form');
    restoreTeamMembers(document.getElementById('modal-os-team'), data.team_members || data.equipe || legacyTeamFromData(data));
    restoreSchedule(form, data.agendado_inicio, data.agendado_fim, data.agendamento_duracao_minutos);
  }

  function restoreFinalizeForm(data) {
    setValue('os-finalize-id', data.id);
    const modal = document.getElementById('modal-os-finalize');
    if (!modal) return;
    const fields = {
      vencimento_em: data.vencimento_em,
      proximo_lembrete_em: data.proximo_lembrete_em,
      observacao: data.observacao,
      saldo_observacao: data.saldo_observacao,
    };
    Object.entries(fields).forEach(function ([name, value]) {
      const input = modal.querySelector('[name="' + name + '"]');
      if (input) input.value = value || '';
    });
    const recoveredItem = Array.isArray(data.execution_items) ? data.execution_items[0] : null;
    if (recoveredItem) {
      ['type', 'description', 'quantity', 'unit_price', 'discount'].forEach(function (field) {
        const input = modal.querySelector('[name="execution_items[0][' + field + ']"]');
        if (input && recoveredItem[field] !== undefined) input.value = recoveredItem[field];
      });
    }
    if (recoveryError) {
      const body = modal.querySelector('.modal-body');
      const alert = document.createElement('div');
      alert.className = 'alert alert-danger';
      alert.setAttribute('role', 'alert');
      alert.textContent = recoveryError;
      body?.prepend(alert);
    }
  }

  function legacyTeamFromData(data) {
    const members = [];
    if (data.funcionario_principal_id) members.push({ employee_id: data.funcionario_principal_id, role: 'Responsável técnico', primary: true });
    if (data.funcionario_apoio_id) members.push({ employee_id: data.funcionario_apoio_id, role: 'Técnico', primary: false });
    return members;
  }

  function updateBudgetMode(form) {
    const mode = document.getElementById('os-creation-mode')?.value || 'manual';
    const budget = document.getElementById('os-budget-id');
    const client = document.getElementById('os-client');
    const preview = document.getElementById('os-budget-preview');
    const fromBudget = mode === 'budget';
    if (budget) budget.required = fromBudget;
    if (client) client.disabled = fromBudget;
    form.querySelectorAll('[data-os-items], .js-os-add-item, .js-os-discount, .js-os-increase').forEach(function (element) {
      element.classList.toggle('d-none', fromBudget);
    });
    if (preview) {
      const selected = budget?.selectedOptions?.[0];
      preview.classList.toggle('d-none', !fromBudget || !selected || !selected.value);
      preview.textContent = selected && selected.value ? ('Orçamento selecionado: ' + selected.textContent + (selected.dataset.summary ? ' | Serviços: ' + selected.dataset.summary : '')) : '';
    }
    if (fromBudget && budget?.selectedOptions?.[0]?.dataset.clientId) {
      setValue('os-client', budget.selectedOptions[0].dataset.clientId);
    }
  }

  document.querySelectorAll('.js-os-form').forEach(function (form) {
    bindScheduleControls(form);
    form.querySelectorAll('.js-os-add-item').forEach(function (button) {
      button.addEventListener('click', function () { addRow(form, button.dataset.type); });
    });
    form.querySelectorAll('.js-os-discount,.js-os-increase').forEach(function (input) {
      input.addEventListener('input', function () { recalc(form); });
    });
    form.querySelectorAll('.js-os-add-team-member').forEach(function (button) {
      button.addEventListener('click', function () { addTeamMember(form); });
    });
    document.getElementById('os-creation-mode')?.addEventListener('change', function () { updateBudgetMode(form); });
    document.getElementById('os-budget-id')?.addEventListener('change', function () { updateBudgetMode(form); });
    if (!form.querySelector('.os-item-row') && !(hasRecoveredItems(recoveryData) && (recoveryModal === 'create' || recoveryModal === 'edit'))) addRow(form, 'servico');
    updateBudgetMode(form);
  });

  document.querySelectorAll('form:not(.js-os-form) .js-os-add-team-member').forEach(function (button) {
    button.addEventListener('click', function () {
      const form = button.closest('form');
      if (form) addTeamMember(form);
    });
  });

  const teamScheduleForm = document.querySelector('#modal-os-team form');
  if (teamScheduleForm) bindScheduleControls(teamScheduleForm);

  async function loadOrder(id) {
    const response = await fetch('actions/os-detalhes.php?id=' + encodeURIComponent(id), { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('Falha ao carregar OS.');
    return response.json();
  }

  function setValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    const normalized = value || '';
    if (el.tagName === 'SELECT' && normalized && !Array.from(el.options).some(function (option) { return option.value === String(normalized); })) {
      el.appendChild(new Option(String(normalized), String(normalized)));
    }
    el.value = normalized;
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '';
  }

  function showOrderFormError(message) {
    const alert = document.querySelector('#modal-os [data-os-form-error]');
    if (!alert) return;
    alert.textContent = message;
    alert.classList.remove('d-none');
  }

  function hideOrderFormError() {
    const alert = document.querySelector('#modal-os [data-os-form-error]');
    if (!alert) return;
    alert.textContent = '';
    alert.classList.add('d-none');
  }

  function resetOrderModal(form) {
    form.reset();
    hideOrderFormError();
    form.querySelectorAll('.os-items').forEach(function (box) { box.replaceChildren(); });
    restoreTeamMembers(form, []);
    setValue('os-id', '');
    setValue('os-creation-mode', 'manual');
    setValue('os-budget-id', '');
    const initialStatus = 'aberta';
    setValue('os-status', initialStatus);
    const status = document.getElementById('os-status');
    if (status) status.disabled = false;
    const budget = document.getElementById('os-budget-id');
    if (budget) budget.disabled = false;
    document.getElementById('modal-os-title').textContent = 'Nova OS';
    addRow(form, 'servico');
    updateBudgetMode(form);
    recalc(form);
  }

  async function prepareOrderView(button) {
    const data = await loadOrder(button.dataset.orderId);
    const order = data.order;
    document.getElementById('os-view-subtitle').textContent = order.number || '';
    const summary = document.getElementById('os-view-summary');
    summary.replaceChildren();
    [
      ['Número', order.number], ['Cliente', order.client_name], ['Equipamento', [order.equipment_type, order.equipment_brand, order.equipment_model].filter(Boolean).join(' ') || '-'],
      ['Status', order.status], ['Prioridade', order.priority], ['Agendamento', ((order.scheduled_start || '-') + ' até ' + (order.scheduled_end || '-'))],
      ['Problema', order.reported_problem || '-'], ['Diagnóstico', order.diagnosis || '-'], ['Observações', order.notes || '-']
    ].forEach(function (pair) {
      const div = document.createElement('div');
      const span = document.createElement('span');
      span.textContent = pair[0];
      const strong = document.createElement('strong');
      strong.textContent = pair[1] || '-';
      div.append(span, strong);
      summary.appendChild(div);
    });
    const tbody = document.getElementById('os-view-items');
    tbody.replaceChildren();
    data.items.forEach(function (item) {
      const row = document.createElement('tr');
      [item.type, item.description, item.execution_location || '-', item.quantity, money(parseNumber(item.unit_price)), money(parseNumber(item.subtotal))].forEach(function (value) {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.appendChild(cell);
      });
      tbody.appendChild(row);
    });
  }

  async function prepareOrderEdit(button) {
    const modal = document.getElementById('modal-os');
    const form = modal.querySelector('form');
    try {
      hideOrderFormError();
      form.querySelectorAll('.os-items').forEach(function (box) { box.replaceChildren(); });
      const data = await loadOrder(button.dataset.orderId);
      const order = data.order;
      document.getElementById('modal-os-title').textContent = 'Editar OS';
      setValue('os-id', order.id);
      setValue('os-client', order.client_id);
      setValue('os-budget-id', order.budget_id);
      const budgetSelect = document.getElementById('os-budget-id');
      if (budgetSelect) budgetSelect.disabled = false;
      if (order.budget_id) {
        setValue('os-creation-mode', 'manual');
        if (budgetSelect) {
          const selected = budgetSelect.querySelector('option[value="' + String(order.budget_id) + '"]');
          if (selected && selected.textContent === String(order.budget_id)) {
            selected.textContent = 'Orçamento vinculado ORC-' + String(order.budget_id).padStart(6, '0');
          }
          budgetSelect.disabled = true;
        }
      }
      setValue('os-priority', order.priority);
      setValue('os-equipment-type', order.equipment_type);
      setValue('os-equipment-brand', order.equipment_brand);
      setValue('os-equipment-model', order.equipment_model);
      setValue('os-equipment-capacity', order.equipment_capacity);
      setValue('os-equipment-serial-number', order.equipment_serial_number);
      setValue('os-equipment-environment', order.equipment_environment);
      setValue('os-equipment-location', order.equipment_location);
      setValue('os-reported-problem', order.reported_problem);
      setValue('os-identified-problem', order.identified_problem);
      setValue('os-diagnosis', order.diagnosis);
      setValue('os-solution', order.solution);
      setValue('os-recommendation', order.recommendation);
      setValue('os-internal-notes', order.internal_notes);
      setValue('os-notes', order.notes);
      setValue('os-discount', order.discount);
      setValue('os-increase', order.increase);
      setValue('os-status', order.status);
      const status = document.getElementById('os-status');
      if (status) status.disabled = true;
      data.items.forEach(function (item) { addRow(form, item.type, item); });
      restoreTeamMembers(form, data.team || legacyTeamFromData(order));
      restoreSchedule(form, order.scheduled_start, order.scheduled_end, null);
      updateBudgetMode(form);
      recalc(form);
    } catch (error) {
      showOrderFormError('Não foi possível carregar os dados da OS.');
    }
  }

  const orderModal = document.getElementById('modal-os');
  if (orderModal) {
    orderModal.addEventListener('show.bs.modal', function (event) {
      if (event.relatedTarget && !event.relatedTarget.classList.contains('js-os-edit')) {
        const form = orderModal.querySelector('form');
        if (form) resetOrderModal(form);
      }
    });
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest?.('.js-os-view, .js-os-edit, .js-os-team, .js-os-status, .js-os-finalize, .js-os-cancel, .js-os-reverse, .js-os-delete, .js-os-receipt');
    if (!button) return;

    if (button.classList.contains('js-os-view')) {
      void prepareOrderView(button);
    } else if (button.classList.contains('js-os-edit')) {
      void prepareOrderEdit(button);
    } else if (button.classList.contains('js-os-team')) {
      setValue('os-team-id', button.dataset.orderId);
      let team = [];
      try { team = JSON.parse(button.dataset.team || '[]'); } catch (error) { team = []; }
      const teamForm = document.querySelector('#modal-os-team form');
      restoreTeamMembers(document.getElementById('modal-os-team'), team);
      restoreSchedule(teamForm, button.dataset.start, button.dataset.end, null);
    } else if (button.classList.contains('js-os-status')) {
      setValue('os-status-id', button.dataset.orderId);
      setValue('os-status-operation', button.dataset.operation);
      document.getElementById('os-status-title').textContent = button.dataset.label || 'Alterar status';
      document.getElementById('os-status-message').textContent = 'Confirmar operação "' + (button.dataset.label || 'alterar status') + '"?';
    } else if (button.classList.contains('js-os-finalize')) {
      setValue('os-finalize-id', button.dataset.orderId);
    } else if (button.classList.contains('js-os-cancel')) {
      setValue('os-cancel-id', button.dataset.orderId);
    } else if (button.classList.contains('js-os-reverse')) {
      setValue('os-reverse-id', button.dataset.orderId);
      setText('os-reverse-number', button.dataset.orderNumber);
      setValue('os-reverse-reason', '');
    } else if (button.classList.contains('js-os-delete')) {
      setValue('os-delete-id', button.dataset.orderId);
      setText('os-delete-number', button.dataset.orderNumber);
      setValue('os-delete-reason', '');
    } else if (button.classList.contains('js-os-receipt')) {
      setValue('os-receipt-payment-id', button.dataset.paymentId);
      setText('os-receipt-order-number', button.dataset.orderNumber);
      setText('os-receipt-payment-label', button.dataset.paymentLabel);
    }
  });

  if ((recoveryModal === 'create' || recoveryModal === 'edit') && window.bootstrap) {
    const modal = document.getElementById('modal-os');
    if (modal) {
      const form = modal.querySelector('form');
      if (form) restoreOrderForm(form, recoveryData);
      document.getElementById('modal-os-title').textContent = recoveryModal === 'edit' ? 'Editar OS' : 'Nova OS';
      bootstrap.Modal.getOrCreateInstance(modal).show();
    }
  }

  if (recoveryModal === 'team' && window.bootstrap) {
    restoreTeamForm(recoveryData);
    const modal = document.getElementById('modal-os-team');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }

if (
  recoveryModal === 'delete'
  && window.bootstrap
) {
  setValue(
    'os-delete-id',
    recoveryData.id
  );

  setValue(
    'os-delete-reason',
    recoveryData.motivo
  );

  setText(
    'os-delete-number',
    recoveryData.id
      ? 'OS #' + recoveryData.id
      : 'OS selecionada'
  );

  const deleteModal = document.getElementById(
    'modal-os-delete'
  );

  if (deleteModal) {
    if (recoveryError) {
      const modalBody = deleteModal.querySelector(
        '.modal-body'
      );

      const oldAlert = deleteModal.querySelector(
        '.js-os-delete-recovery-alert'
      );

      oldAlert?.remove();

      const alert = document.createElement('div');

      alert.className =
        'alert alert-danger js-os-delete-recovery-alert';

      alert.setAttribute(
        'role',
        'alert'
      );

      alert.textContent = recoveryError;

      modalBody?.prepend(alert);
    }

    bootstrap.Modal
      .getOrCreateInstance(deleteModal)
      .show();
  }
}

});
