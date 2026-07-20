document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const pageDataNode = document.getElementById('agenda-page-data');
  const pageData = pageDataNode ? JSON.parse(pageDataNode.textContent || '{}') : {};
  const recoveryModal = pageData.recoveryModal || new URLSearchParams(window.location.search).get('modal');
  const recoveryData = pageData.recoveryData || {};
  const recoveryError = pageData.recoveryError || '';
  const statusOperations = {
    agendada: ['start_travel', 'start_execution', 'wait_part', 'cancel'],
    em_deslocamento: ['start_execution', 'wait_part', 'cancel'],
    em_execucao: ['wait_part', 'cancel'],
    aguardando_peca: ['start_execution', 'cancel'],
  };

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
    setValue('agenda-status-operation', select.value);
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

  document.querySelectorAll('.js-primary-employee,.js-support-employee').forEach(function (select) {
    select.addEventListener('change', function () { updateEmployeeOptions(select.closest('form') || document); });
  });

  document.addEventListener('click', function (event) {
    const button = event.target.closest?.('.js-reminder-edit, .js-reminder-cancel, .js-agenda-schedule, .js-agenda-team, .js-agenda-status');
    if (!button) return;

    if (button.classList.contains('js-reminder-edit')) {
      setValue('reminder-edit-id', button.dataset.id);
      setValue('reminder-edit-title', button.dataset.title);
      setValue('reminder-edit-description', button.dataset.description);
      setValue('reminder-edit-start', toLocalInput(button.dataset.start));
      setValue('reminder-edit-end', toLocalInput(button.dataset.end));
    } else if (button.classList.contains('js-reminder-cancel')) {
      setValue('reminder-cancel-id', button.dataset.id);
      const message = document.getElementById('reminder-cancel-message');
      if (message) message.textContent = 'Cancelar o lembrete "' + (button.dataset.title || '') + '"?';
    } else if (button.classList.contains('js-agenda-schedule')) {
      setValue('agenda-schedule-id', button.dataset.orderId);
      setValue('agenda-schedule-start', toLocalInput(button.dataset.start));
      setValue('agenda-schedule-end', toLocalInput(button.dataset.end));
    } else if (button.classList.contains('js-agenda-team')) {
      setValue('agenda-team-id', button.dataset.orderId);
      setValue('agenda-team-primary', button.dataset.primaryId);
      setValue('agenda-team-support', button.dataset.supportId);
      updateEmployeeOptions(document.getElementById('modal-agenda-team'));
    } else if (button.classList.contains('js-agenda-status')) {
      setValue('agenda-status-id', button.dataset.orderId);
      const select = document.getElementById('agenda-status-select');
      if (select) {
        filterStatusOptions(select, button.dataset.currentStatus || '');
        setValue('agenda-status-operation', select.value);
        select.onchange = function () { setValue('agenda-status-operation', select.value); };
      } else {
        setValue('agenda-status-operation', button.dataset.operation || 'start_execution');
      }
      const message = document.getElementById('agenda-status-message');
      if (message) message.textContent = 'Escolha a operação de status para esta OS.';
    }
  });

  function restoreRecovery() {
    if (!recoveryModal || !window.bootstrap) return;
    const modalMap = {
      reminder: 'modal-lembrete',
      reminder_edit: 'modal-lembrete-edit',
      reschedule: 'modal-agenda-schedule',
      team: 'modal-agenda-team',
      status: 'modal-agenda-status',
      cancel: 'modal-lembrete-cancel',
    };
    const modal = document.getElementById(modalMap[recoveryModal]);
    if (!modal) return;

    setValue('reminder-edit-id', recoveryData.id);
    setValue('reminder-edit-title', recoveryData.title);
    setValue('reminder-edit-description', recoveryData.description);
    setValue('reminder-edit-start', toLocalInput(recoveryData.start));
    setValue('reminder-edit-end', toLocalInput(recoveryData.end));
    const createReminder = modal.querySelector('form');
    if (recoveryModal === 'reminder' && createReminder) {
      const title = createReminder.querySelector('[name="title"]');
      const description = createReminder.querySelector('[name="description"]');
      const start = createReminder.querySelector('[name="start"]');
      const end = createReminder.querySelector('[name="end"]');
      if (title) title.value = recoveryData.title || '';
      if (description) description.value = recoveryData.description || '';
      if (start) start.value = toLocalInput(recoveryData.start);
      if (end) end.value = toLocalInput(recoveryData.end);
    }
    setValue('agenda-schedule-id', recoveryData.id);
    setValue('agenda-schedule-start', toLocalInput(recoveryData.agendado_inicio));
    setValue('agenda-schedule-end', toLocalInput(recoveryData.agendado_fim));
    setValue('agenda-team-id', recoveryData.id);
    setValue('agenda-team-primary', recoveryData.funcionario_principal_id);
    setValue('agenda-team-support', recoveryData.funcionario_apoio_id);
    setValue('agenda-status-id', recoveryData.id);
    setValue('agenda-status-operation', recoveryData.operation);
    setValue('reminder-cancel-id', recoveryData.id);
    updateEmployeeOptions(modal);
    showRecoveryError(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  restoreRecovery();
});
