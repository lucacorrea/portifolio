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

  document.querySelectorAll('.js-week-schedule').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('week-schedule-id', button.dataset.orderId);
      setValue('week-schedule-start', toLocalInput(button.dataset.start));
      setValue('week-schedule-end', toLocalInput(button.dataset.end));
    });
  });

  document.querySelectorAll('.js-week-team').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('week-team-id', button.dataset.orderId);
      setValue('week-team-primary', button.dataset.primaryId);
      setValue('week-team-support', button.dataset.supportId);
      updateEmployeeOptions(document.getElementById('modal-week-team'));
    });
  });

  document.querySelectorAll('.js-week-status').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('week-status-id', button.dataset.orderId);
      const select = document.getElementById('week-status-select');
      if (select) {
        filterStatusOptions(select, button.dataset.currentStatus || '');
        setValue('week-status-operation', select.value);
        select.onchange = function () { setValue('week-status-operation', select.value); };
      }
    });
  });

  document.querySelectorAll('.js-week-cancel').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('week-cancel-id', button.dataset.orderId);
      const message = document.getElementById('week-cancel-message');
      if (message) message.textContent = 'Cancelar a OS ' + (button.dataset.orderNumber || '') + '?';
    });
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
