document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const pageDataNode = document.getElementById('weekly-page-data');
  const pageData = pageDataNode ? JSON.parse(pageDataNode.textContent || '{}') : {};
  const recoveryModal = pageData.recoveryModal || new URLSearchParams(window.location.search).get('modal');
  const recoveryData = pageData.recoveryData || {};
  const recoveryError = pageData.recoveryError || '';
  const statusOperations = {
    agendada: ['start_travel', 'start_execution', 'wait_part'],
    em_deslocamento: ['start_execution', 'wait_part'],
    em_execucao: ['wait_part'],
    aguardando_peca: ['start_execution'],
  };
  const statusLabels = {
    rascunho: 'Rascunho', aberta: 'Aberta', aguardando_agendamento: 'Aguardando agendamento',
    agendada: 'Agendada', em_deslocamento: 'Em deslocamento', em_execucao: 'Em execução',
    aguardando_peca: 'Aguardando peça', finalizada: 'Finalizada', cancelada: 'Cancelada',
  };
  const priorityLabels = { baixa: 'Baixa', media: 'Média', alta: 'Alta', urgente: 'Urgente' };
  let detailRequest = 0;

  function toLocalInput(value) {
    if (!value) return '';
    return String(value).replace(' ', 'T').slice(0, 16);
  }

  function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value || '';
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

  function filterStatusOptions(select, status) {
    if (!select) return;
    const allowed = statusOperations[status] || ['start_execution'];
    let first = '';
    select.querySelectorAll('option').forEach(function (option) {
      const visible = allowed.includes(option.value);
      option.hidden = !visible;
      option.disabled = !visible;
      if (visible && first === '') first = option.value;
    });
    select.value = allowed.includes(select.value) ? select.value : first;
    setValue('week-status-operation', select.value);
  }

  function showRecoveryError(modal) {
    if (!recoveryError || !modal) return;
    const body = modal.querySelector('.modal-body');
    if (!body) return;
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.setAttribute('role', 'alert');
    alert.textContent = recoveryError;
    body.prepend(alert);
  }

  function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(date);
  }

  function formatSchedule(start, end) {
    if (!start) return 'Não agendado';
    return formatDateTime(start) + (end ? ' até ' + formatDateTime(end) : '');
  }

  function detailItem(label, value) {
    const item = document.createElement('div');
    item.className = 'employee-detail-item';
    const name = document.createElement('span');
    name.textContent = label;
    const content = document.createElement('strong');
    content.textContent = value === null || value === undefined || String(value).trim() === '' ? '-' : String(value);
    item.append(name, content);
    return item;
  }

  function clientAddress(order) {
    const street = [order.client_address, order.client_number].filter(Boolean).join(', ');
    const city = [order.client_district, order.client_city, order.client_state].filter(Boolean).join(' · ');
    return [street, city].filter(Boolean).join(' — ') || '-';
  }

  function renderWeekDetails(data) {
    const order = data.order || {};
    const subtitle = document.getElementById('week-details-subtitle');
    if (subtitle) subtitle.textContent = order.number || '';
    const openOrder = document.getElementById('week-details-open-order');
    if (openOrder) {
      openOrder.href = 'ordens-servico.php?search=' + encodeURIComponent(order.number || '');
      openOrder.classList.remove('disabled');
      openOrder.removeAttribute('aria-disabled');
    }

    const summary = document.getElementById('week-details-summary');
    summary.replaceChildren();
    const equipment = [order.equipment_type, order.equipment_brand, order.equipment_model, order.equipment_capacity].filter(Boolean).join(' ');
    const contact = order.client_whatsapp || order.client_phone || '-';
    [
      ['Número', order.number], ['Cliente', order.client_name], ['Contato', contact], ['Endereço', clientAddress(order)],
      ['Status', statusLabels[order.status] || order.status], ['Prioridade', priorityLabels[order.priority] || order.priority],
      ['Agendamento', formatSchedule(order.scheduled_start, order.scheduled_end)], ['Equipamento', equipment || '-'],
      ['Número de série', order.equipment_serial_number], ['Ambiente', order.equipment_environment], ['Local', order.equipment_location],
      ['Problema relatado', order.reported_problem], ['Problema identificado', order.identified_problem], ['Diagnóstico', order.diagnosis],
      ['Solução', order.solution], ['Recomendação', order.recommendation], ['Observações internas', order.internal_notes], ['Observações', order.notes],
      ['Criada em', formatDateTime(order.created_at)], ['Última atualização', formatDateTime(order.updated_at)],
    ].forEach(function (pair) { summary.appendChild(detailItem(pair[0], pair[1])); });

    const team = document.getElementById('week-details-team');
    team.replaceChildren();
    if (!Array.isArray(data.team) || data.team.length === 0) {
      team.appendChild(detailItem('Equipe', 'Não definida'));
    } else {
      data.team.forEach(function (member) {
        team.appendChild(detailItem(member.primary ? 'Responsável principal' : (member.role || 'Integrante'), member.display || member.employee_name));
      });
    }

    const items = document.getElementById('week-details-items');
    items.replaceChildren();
    if (!Array.isArray(data.items) || data.items.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'text-muted mb-0';
      empty.textContent = 'Nenhum item cadastrado.';
      items.appendChild(empty);
    } else {
      const table = document.createElement('table');
      table.className = 'os-table';
      const head = document.createElement('thead');
      const headRow = document.createElement('tr');
      ['Tipo', 'Descrição', 'Qtd.', 'Unidade'].forEach(function (label) {
        const th = document.createElement('th'); th.textContent = label; headRow.appendChild(th);
      });
      head.appendChild(headRow);
      const body = document.createElement('tbody');
      data.items.forEach(function (item) {
        const row = document.createElement('tr');
        [item.type, item.description, item.quantity, item.unit].forEach(function (value) {
          const cell = document.createElement('td'); cell.textContent = value || '-'; row.appendChild(cell);
        });
        body.appendChild(row);
      });
      table.append(head, body);
      items.appendChild(table);
    }
  }

  async function loadWeekDetails(button) {
    const request = ++detailRequest;
    const loading = document.getElementById('week-details-loading');
    const error = document.getElementById('week-details-error');
    const content = document.getElementById('week-details-content');
    const subtitle = document.getElementById('week-details-subtitle');
    const openOrder = document.getElementById('week-details-open-order');
    if (!loading || !error || !content) return;
    loading.classList.remove('d-none');
    error.classList.add('d-none');
    content.classList.add('d-none');
    if (subtitle) subtitle.textContent = button.dataset.orderNumber || '';
    if (openOrder) {
      openOrder.removeAttribute('href');
      openOrder.classList.add('disabled');
      openOrder.setAttribute('aria-disabled', 'true');
    }
    try {
      const response = await fetch('actions/os-detalhes.php?id=' + encodeURIComponent(button.dataset.orderId || ''), { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok || data.error) throw new Error(data.error || 'Não foi possível carregar os detalhes.');
      if (request !== detailRequest) return;
      renderWeekDetails(data);
      loading.classList.add('d-none');
      content.classList.remove('d-none');
    } catch (loadError) {
      if (request !== detailRequest) return;
      loading.classList.add('d-none');
      error.textContent = 'Não foi possível carregar os detalhes da OS.';
      error.classList.remove('d-none');
    }
  }

  document.querySelectorAll('.js-primary-employee,.js-support-employee').forEach(function (select) {
    select.addEventListener('change', function () { updateEmployeeOptions(select.closest('form') || document); });
  });

  function syncCreateEndFromDuration() {
    const start = document.getElementById('week-create-start');
    const end = document.getElementById('week-create-end');
    const service = document.getElementById('week-create-service');
    if (!start || !end || !service || !start.value || end.value) return;
    const duration = Number.parseInt(service.selectedOptions[0]?.dataset.duration || '60', 10);
    const startDate = new Date(start.value);
    if (Number.isNaN(startDate.getTime())) return;
    startDate.setMinutes(startDate.getMinutes() + (duration > 0 ? duration : 60));
    const pad = (value) => String(value).padStart(2, '0');
    end.value = startDate.getFullYear() + '-' + pad(startDate.getMonth() + 1) + '-' + pad(startDate.getDate()) + 'T' + pad(startDate.getHours()) + ':' + pad(startDate.getMinutes());
  }

  document.getElementById('week-create-start')?.addEventListener('change', syncCreateEndFromDuration);
  document.getElementById('week-create-service')?.addEventListener('change', syncCreateEndFromDuration);

  document.addEventListener('click', function (event) {
    const button = event.target.closest?.('.js-week-details, .js-week-schedule, .js-week-team, .js-week-status, .js-week-cancel');
    if (!button) return;

    if (button.classList.contains('js-week-details')) {
      loadWeekDetails(button);
    } else if (button.classList.contains('js-week-schedule')) {
      setValue('week-schedule-id', button.dataset.orderId);
      setValue('week-schedule-start', toLocalInput(button.dataset.start));
      setValue('week-schedule-end', toLocalInput(button.dataset.end));
    } else if (button.classList.contains('js-week-team')) {
      setValue('week-team-id', button.dataset.orderId);
      setValue('week-team-primary', button.dataset.primaryId);
      setValue('week-team-support', button.dataset.supportId);
      updateEmployeeOptions(document.getElementById('modal-week-team'));
    } else if (button.classList.contains('js-week-status')) {
      setValue('week-status-id', button.dataset.orderId);
      const select = document.getElementById('week-status-select');
      if (select) {
        filterStatusOptions(select, button.dataset.currentStatus || '');
        setValue('week-status-operation', select.value);
        select.onchange = function () { setValue('week-status-operation', select.value); };
      }
    } else if (button.classList.contains('js-week-cancel')) {
      setValue('week-cancel-id', button.dataset.orderId);
      const message = document.getElementById('week-cancel-message');
      if (message) message.textContent = 'Cancelar a OS ' + (button.dataset.orderNumber || '') + '?';
    }
  });

  function restoreRecovery() {
    if (!recoveryModal || !window.bootstrap) return;
    const modalMap = {
      create: 'modal-week-create',
      reschedule: 'modal-week-schedule',
      team: 'modal-week-team',
      status: 'modal-week-status',
      cancel: 'modal-week-cancel',
    };
    const modal = document.getElementById(modalMap[recoveryModal]);
    if (!modal) return;

    setValue('week-create-client', recoveryData.client_id);
    setValue('week-create-service', recoveryData.service_id);
    setValue('week-create-start', toLocalInput(recoveryData.agendado_inicio));
    setValue('week-create-end', toLocalInput(recoveryData.agendado_fim));
    setValue('week-schedule-id', recoveryData.id);
    setValue('week-schedule-start', toLocalInput(recoveryData.agendado_inicio));
    setValue('week-schedule-end', toLocalInput(recoveryData.agendado_fim));
    setValue('week-team-id', recoveryData.id);
    setValue('week-team-primary', recoveryData.funcionario_principal_id);
    setValue('week-team-support', recoveryData.funcionario_apoio_id);
    setValue('week-status-id', recoveryData.id);
    setValue('week-status-operation', recoveryData.operation);
    setValue('week-cancel-id', recoveryData.id);
    updateEmployeeOptions(modal);
    showRecoveryError(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  restoreRecovery();
});
