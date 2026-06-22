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
        setValue('week-status-operation', select.value);
        select.onchange = function () { setValue('week-status-operation', select.value); };
      }
    });
  });
});
