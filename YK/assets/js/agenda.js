document.addEventListener('DOMContentLoaded', function () {
  'use strict';

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

  document.querySelectorAll('.js-primary-employee,.js-support-employee').forEach(function (select) {
    select.addEventListener('change', function () { updateEmployeeOptions(select.closest('form') || document); });
  });

  document.querySelectorAll('.js-reminder-edit').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('reminder-edit-id', button.dataset.id);
      setValue('reminder-edit-title', button.dataset.title);
      setValue('reminder-edit-description', button.dataset.description);
      setValue('reminder-edit-start', toLocalInput(button.dataset.start));
      setValue('reminder-edit-end', toLocalInput(button.dataset.end));
    });
  });

  document.querySelectorAll('.js-reminder-cancel').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('reminder-cancel-id', button.dataset.id);
      const message = document.getElementById('reminder-cancel-message');
      if (message) message.textContent = 'Cancelar o lembrete "' + (button.dataset.title || '') + '"?';
    });
  });

  document.querySelectorAll('.js-agenda-schedule').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('agenda-schedule-id', button.dataset.orderId);
      setValue('agenda-schedule-start', toLocalInput(button.dataset.start));
      setValue('agenda-schedule-end', toLocalInput(button.dataset.end));
    });
  });

  document.querySelectorAll('.js-agenda-team').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('agenda-team-id', button.dataset.orderId);
      setValue('agenda-team-primary', button.dataset.primaryId);
      setValue('agenda-team-support', button.dataset.supportId);
      updateEmployeeOptions(document.getElementById('modal-agenda-team'));
    });
  });

  document.querySelectorAll('.js-agenda-status').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('agenda-status-id', button.dataset.orderId);
      const select = document.getElementById('agenda-status-select');
      const operation = button.dataset.operation || 'start_execution';
      if (select) {
        select.value = operation;
        setValue('agenda-status-operation', select.value);
        select.onchange = function () { setValue('agenda-status-operation', select.value); };
      } else {
        setValue('agenda-status-operation', operation);
      }
      const message = document.getElementById('agenda-status-message');
      if (message) message.textContent = 'Escolha a operação de status para esta OS.';
    });
  });
});
