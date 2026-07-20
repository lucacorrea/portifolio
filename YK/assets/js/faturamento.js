document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const mode = document.getElementById('standalone-receipt-mode');
  const registeredWrap = document.querySelector('[data-receipt-registered-client]');
  const standaloneWrap = document.querySelector('[data-receipt-standalone-client]');
  const registered = document.getElementById('standalone-receipt-client');
  const name = document.getElementById('standalone-receipt-name');
  const documentField = document.getElementById('standalone-receipt-document');

  function syncClientMode() {
    if (!mode) return;
    const usesRegisteredClient = mode.value === 'registered' && registered?.options.length > 1;
    if (!usesRegisteredClient && mode.value === 'registered') mode.value = 'standalone';
    if (registeredWrap) registeredWrap.hidden = !usesRegisteredClient;
    if (standaloneWrap) standaloneWrap.hidden = usesRegisteredClient;
    if (registered) {
      registered.disabled = !usesRegisteredClient;
      registered.required = usesRegisteredClient;
    }
    if (name) {
      name.disabled = usesRegisteredClient;
      name.required = !usesRegisteredClient;
    }
    if (documentField) documentField.disabled = usesRegisteredClient;
  }

  mode?.addEventListener('change', syncClientMode);
  syncClientMode();

  document.querySelector('#modal-standalone-receipt form')?.addEventListener('submit', function (event) {
    if (!event.currentTarget.checkValidity()) return;
    const submit = event.currentTarget.querySelector('[type="submit"]');
    if (submit) {
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      window.setTimeout(function () {
        submit.disabled = false;
        submit.removeAttribute('aria-busy');
      }, 1500);
    }
  });
});
