document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const dataNode = document.getElementById('os-page-data');
  const pageData = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
  const serviceOptions = pageData.services || [];
  const productOptions = pageData.products || [];
  const recoveryModal = pageData.recoveryModal || new URLSearchParams(window.location.search).get('modal');
  const recoveryData = pageData.recoveryData || {};

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
      ['os-primary', 'funcionario_principal_id'],
      ['os-support', 'funcionario_apoio_id'],
      ['os-scheduled-start', 'agendado_inicio'],
      ['os-scheduled-end', 'agendado_fim'],
    ].forEach(function (pair) {
      setValue(pair[0], data[pair[1]]);
    });
    if (hasRecoveredItems(data)) restoreItems(form, data);
    updateEmployeeOptions(form);
    recalc(form);
  }

  function restoreTeamForm(data) {
    setValue('os-team-id', data.id);
    setValue('os-team-primary', data.funcionario_principal_id);
    setValue('os-team-support', data.funcionario_apoio_id);
    setValue('os-team-start', toLocalInput(data.agendado_inicio));
    setValue('os-team-end', toLocalInput(data.agendado_fim));
    updateEmployeeOptions(document.getElementById('modal-os-team'));
  }

  function updateEmployeeOptions(scope) {
    const primary = scope.querySelector('.js-primary-employee');
    const support = scope.querySelector('.js-support-employee');
    if (!primary || !support) return;
    const primaryValue = primary.value;
    const supportValue = support.value;
    support.querySelectorAll('option').forEach(function (option) { option.disabled = option.value !== '' && option.value === primaryValue; });
    primary.querySelectorAll('option').forEach(function (option) { option.disabled = option.value !== '' && option.value === supportValue; });
  }

  document.querySelectorAll('.js-primary-employee,.js-support-employee').forEach(function (select) {
    select.addEventListener('change', function () { updateEmployeeOptions(select.closest('form') || document); });
  });

  document.querySelectorAll('.js-os-form').forEach(function (form) {
    form.querySelectorAll('.js-os-add-item').forEach(function (button) {
      button.addEventListener('click', function () { addRow(form, button.dataset.type); });
    });
    form.querySelectorAll('.js-os-discount,.js-os-increase').forEach(function (input) {
      input.addEventListener('input', function () { recalc(form); });
    });
    if (!form.querySelector('.os-item-row') && !(hasRecoveredItems(recoveryData) && (recoveryModal === 'create' || recoveryModal === 'edit'))) addRow(form, 'servico');
  });

  async function loadOrder(id) {
    const response = await fetch('actions/os-detalhes.php?id=' + encodeURIComponent(id), { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('Falha ao carregar OS.');
    return response.json();
  }

  function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value || '';
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
      const data = await loadOrder(button.dataset.orderId);
      const order = data.order;
      const modal = document.getElementById('modal-os');
      const form = modal.querySelector('form');
      form.querySelectorAll('.os-items').forEach(function (box) { box.replaceChildren(); });
      document.getElementById('modal-os-title').textContent = 'Editar OS';
      setValue('os-id', order.id);
      setValue('os-client', order.client_id);
      setValue('os-budget-id', order.budget_id);
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
      setValue('os-primary', order.primary_employee_id);
      setValue('os-support', order.support_employee_id);
      setValue('os-scheduled-start', toLocalInput(order.scheduled_start));
      setValue('os-scheduled-end', toLocalInput(order.scheduled_end));
      setValue('os-status', 'aberta');
      data.items.forEach(function (item) { addRow(form, item.type, item); });
      updateEmployeeOptions(form);
      recalc(form);
    });
  });

  document.querySelectorAll('.js-os-team').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('os-team-id', button.dataset.orderId);
      setValue('os-team-primary', button.dataset.primaryId);
      setValue('os-team-support', button.dataset.supportId);
      setValue('os-team-start', toLocalInput(button.dataset.start));
      setValue('os-team-end', toLocalInput(button.dataset.end));
      updateEmployeeOptions(document.getElementById('modal-os-team'));
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
});
