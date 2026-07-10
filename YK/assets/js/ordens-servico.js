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

  function setItemNames(row, type, index) {
    const group = type === 'servico' ? 'services' : type === 'produto' ? 'products' : 'others';
    row.querySelectorAll('[data-field]').forEach(function (input) {
      input.name = group + '[' + index + '][' + input.dataset.field + ']';
    });
  }

  function teamField(row, name) {
    return row.querySelector('[data-team-field="' + name + '"]');
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
    teamField(row, 'funcao').value = member?.role || member?.funcao || 'Técnico';
    teamField(row, 'principal').checked = Boolean(member?.primary || member?.principal);

    row.querySelector('.js-os-remove-team-member').addEventListener('click', function () {
      row.remove();
      setTeamNames(container);
      updateTeamDuplicates(container);
    });
    row.addEventListener('change', function () {
      setTeamNames(container);
      updateTeamDuplicates(container);
    });

    container.appendChild(row);
    if (!container.querySelector('[data-team-field="principal"]:checked')) {
      teamField(row, 'principal').checked = true;
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
    if (type === 'outro') {
      referenceWrap.classList.add('d-none');
      select.appendChild(new Option('Personalizado', ''));
    } else {
      select.appendChild(new Option('Selecione', ''));
      optionsFor(type).forEach(function (option) {
        const opt = new Option(option.name, option.id);
        opt.dataset.description = option.description || option.name;
        opt.dataset.unit = option.unit || 'un';
        opt.dataset.value = option.value || '0.00';
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
      field(row, 'unit').value = item.unit || 'un';
      field(row, 'quantity').value = item.quantity || '1';
      field(row, 'unit_price').value = item.unit_price || '0,00';
      field(row, 'discount').value = item.discount || '0,00';
    }
    select.addEventListener('change', function () {
      const opt = select.selectedOptions[0];
      if (!opt) return;
      if (!field(row, 'description').value) field(row, 'description').value = opt.dataset.description || opt.textContent;
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
      ['os-scheduled-start', 'agendado_inicio'],
      ['os-scheduled-end', 'agendado_fim'],
    ].forEach(function (pair) {
      setValue(pair[0], data[pair[1]]);
    });
    setValue('os-creation-mode', data.creation_mode || 'manual');
    if (hasRecoveredItems(data)) restoreItems(form, data);
    restoreTeamMembers(form, data.team_members || data.equipe || legacyTeamFromData(data));
    updateBudgetMode(form);
    recalc(form);
  }

  function restoreTeamForm(data) {
    setValue('os-team-id', data.id);
    setValue('os-team-start', toLocalInput(data.agendado_inicio));
    setValue('os-team-end', toLocalInput(data.agendado_fim));
    restoreTeamMembers(document.getElementById('modal-os-team'), data.team_members || data.equipe || legacyTeamFromData(data));
  }

  function restoreFinalizeForm(data) {
    setValue('os-finalize-id', data.id);
    const modal = document.getElementById('modal-os-finalize');
    if (!modal) return;
    const fields = {
      valor_recebido: data.valor_recebido,
      forma_pagamento: data.forma_pagamento,
      vencimento_em: data.vencimento_em,
      proximo_lembrete_em: data.proximo_lembrete_em,
      observacao: data.observacao,
    };
    Object.entries(fields).forEach(function ([name, value]) {
      const input = modal.querySelector('[name="' + name + '"]');
      if (input) input.value = value || '';
    });
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

  document.querySelectorAll('.js-os-view').forEach(function (button) {
    button.addEventListener('click', async function () {
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
        [item.type, item.description, item.quantity, money(parseNumber(item.unit_price)), money(parseNumber(item.subtotal))].forEach(function (value) {
          const cell = document.createElement('td');
          cell.textContent = value;
          row.appendChild(cell);
        });
        tbody.appendChild(row);
      });
    });
  });

  document.querySelectorAll('.js-os-edit').forEach(function (button) {
    button.addEventListener('click', async function () {
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
        setValue('os-scheduled-start', toLocalInput(order.scheduled_start));
        setValue('os-scheduled-end', toLocalInput(order.scheduled_end));
        setValue('os-status', order.status);
        const status = document.getElementById('os-status');
        if (status) status.disabled = true;
        data.items.forEach(function (item) { addRow(form, item.type, item); });
        restoreTeamMembers(form, data.team || legacyTeamFromData(order));
        updateBudgetMode(form);
        recalc(form);
      } catch (error) {
        showOrderFormError('Não foi possível carregar os dados da OS.');
      }
    });
  });

  const orderModal = document.getElementById('modal-os');
  if (orderModal) {
    orderModal.addEventListener('show.bs.modal', function (event) {
      if (event.relatedTarget && !event.relatedTarget.classList.contains('js-os-edit')) {
        const form = orderModal.querySelector('form');
        if (form) resetOrderModal(form);
      }
    });
  }

  document.querySelectorAll('.js-os-team').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('os-team-id', button.dataset.orderId);
      setValue('os-team-start', toLocalInput(button.dataset.start));
      setValue('os-team-end', toLocalInput(button.dataset.end));
      let team = [];
      try { team = JSON.parse(button.dataset.team || '[]'); } catch (error) { team = []; }
      restoreTeamMembers(document.getElementById('modal-os-team'), team);
    });
  });

  document.querySelectorAll('.js-os-status').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('os-status-id', button.dataset.orderId);
      setValue('os-status-operation', button.dataset.operation);
      document.getElementById('os-status-title').textContent = button.dataset.label || 'Alterar status';
      document.getElementById('os-status-message').textContent = 'Confirmar operação "' + (button.dataset.label || 'alterar status') + '"?';
    });
  });

  document.querySelectorAll('.js-os-finalize').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('os-finalize-id', button.dataset.orderId);
    });
  });

  document.querySelectorAll('.js-os-cancel').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('os-cancel-id', button.dataset.orderId);
    });
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

  if (recoveryModal === 'finalize' && window.bootstrap) {
    restoreFinalizeForm(recoveryData);
    const modal = document.getElementById('modal-os-finalize');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }
});
