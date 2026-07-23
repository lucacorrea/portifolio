document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const pageDataNode = document.getElementById('agenda-page-data');
  const pageData = pageDataNode ? JSON.parse(pageDataNode.textContent || '{}') : {};
  const recoveryModal = pageData.recoveryModal || new URLSearchParams(window.location.search).get('modal');
  const recoveryData = pageData.recoveryData || {};
  const recoveryError = pageData.recoveryError || '';

  function toLocalInput(value) {
    if (!value) return '';
    return String(value).replace(' ', 'T').slice(0, 16);
  }

  function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value || '';
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

  document.addEventListener('click', function (event) {
    const button = event.target.closest?.('.js-reminder-edit, .js-reminder-cancel');
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
      if (message) message.textContent = 'Cancelar o compromisso "' + (button.dataset.title || '') + '"?';
    }
  });

  function restoreRecovery() {
    if (!recoveryModal || !window.bootstrap) return;
    const modalMap = {
      reminder: 'modal-lembrete',
      reminder_edit: 'modal-lembrete-edit',
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
    setValue('reminder-cancel-id', recoveryData.id);
    showRecoveryError(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  restoreRecovery();
});
