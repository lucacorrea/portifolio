(function () {
  'use strict';

  const form = document.querySelector('[data-fiscal-certificate-form]');
  if (!(form instanceof HTMLFormElement)) return;

  const button = form.querySelector('[data-fiscal-certificate-submit]');
  const feedback = form.querySelector('[data-fiscal-certificate-feedback]');
  const idleLabel = button?.innerHTML || '';

  function showError(message) {
    if (!(feedback instanceof HTMLElement)) return;
    feedback.textContent = message;
    feedback.classList.remove('d-none');
  }

  function resetButton() {
    if (!(button instanceof HTMLButtonElement)) return;
    button.disabled = false;
    button.removeAttribute('aria-busy');
    button.innerHTML = idleLabel;
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;

    feedback?.classList.add('d-none');
    if (button instanceof HTMLButtonElement) {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Validando certificado…';
    }

    const controller = new AbortController();
    const timeout = window.setTimeout(function () {
      controller.abort();
    }, 30000);

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        signal: controller.signal
      });
      const payload = await response.json().catch(function () {
        return null;
      });
      if (!response.ok || !payload?.ok) {
        throw new Error(payload?.message || 'O servidor não conseguiu validar o certificado.');
      }

      window.location.reload();
    } catch (error) {
      const timedOut = error instanceof DOMException && error.name === 'AbortError';
      showError(timedOut
        ? 'A validação ultrapassou 30 segundos e foi interrompida na tela. Atualize a página antes de tentar novamente.'
        : (error instanceof Error ? error.message : 'Não foi possível validar o certificado.'));
      resetButton();
    } finally {
      window.clearTimeout(timeout);
    }
  });
})();
