document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const formStates = new WeakMap();
  const actionDialog = document.getElementById('row-actions-dialog');
  const actionHost = actionDialog?.querySelector('[data-row-actions-menu-host]');
  const actionTitle = document.getElementById('row-actions-dialog-title');
  const actionSelector = '.dropdown-item:not(.disabled):not([disabled]):not([aria-disabled="true"])';
  let activeRecord = null;

  const interactiveTarget = (target, record) => {
    const interactive = target.closest(
      'a, button, input, select, textarea, label, form, summary, [contenteditable="true"], [data-bs-toggle], [role="button"], [role="link"]'
    );
    return Boolean(interactive && interactive !== record);
  };

  const restoreRecordActions = ({ focus = true } = {}) => {
    if (!activeRecord) return;
    const { record, menu, parent, next } = activeRecord;
    activeRecord = null;
    menu.classList.remove('row-actions-menu', 'show', 'action-menu-portal');
    ['position', 'inset', 'top', 'right', 'bottom', 'left', 'transform', 'margin', 'z-index', 'visibility']
      .forEach((property) => menu.style.removeProperty(property));
    if (next?.parentNode === parent) parent.insertBefore(menu, next);
    else parent?.appendChild(menu);
    if (actionDialog?.open) actionDialog.close();
    if (focus && record.isConnected) record.focus({ preventScroll: true });
  };

  const openRecordActions = (record) => {
    if (!actionDialog || !actionHost || typeof actionDialog.showModal !== 'function') return;
    const menu = record._recordActionsMenu;
    if (!menu) return;
    restoreRecordActions({ focus: false });
    activeRecord = { record, menu, parent: menu.parentNode, next: menu.nextSibling };
    menu.classList.remove('show', 'action-menu-portal');
    menu.removeAttribute('style');
    menu.classList.add('row-actions-menu');
    actionHost.appendChild(menu);
    if (actionTitle) actionTitle.textContent = record.dataset.recordActionsTitle;
    actionDialog.showModal();
    window.requestAnimationFrame(() => menu.querySelector(actionSelector)?.focus());
  };

  const enhanceRecordActions = (root = document) => {
    restoreRecordActions({ focus: false });
    root.querySelectorAll('[data-record-actions]:not(.record-actions-trigger)').forEach((record) => {
      const menu = record.querySelector('.dropdown-menu');
      const toggle = record.querySelector('.btn-action[aria-label]');
      if (!menu?.querySelector(actionSelector)) return;
      record._recordActionsMenu = menu;
      const label = toggle?.getAttribute('aria-label') || 'Ações do registro';
      const context = record.querySelector('.week-service-client')?.textContent.trim();
      record.dataset.recordActionsTitle = context && !label.includes(context) ? `${label} — ${context}` : label;
      record.classList.add('record-actions-trigger', 'row-actions-trigger');
      record.tabIndex = 0;
      record.setAttribute('role', 'button');
      record.setAttribute('aria-haspopup', 'dialog');
      record.setAttribute('aria-controls', 'row-actions-dialog');
      record.setAttribute('aria-label', record.dataset.recordActionsTitle);
    });
  };

  if (actionDialog && actionHost && typeof actionDialog.showModal === 'function') {
    window.OSMais = window.OSMais || {};
    window.OSMais.refreshRecordActions = enhanceRecordActions;
    document.addEventListener('click', (event) => {
      const record = event.target.closest('[data-record-actions].record-actions-trigger');
      const selection = window.getSelection()?.toString().trim();
      if (!record || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey
        || event.shiftKey || event.altKey || selection || interactiveTarget(event.target, record)) return;
      openRecordActions(record);
    });
    document.addEventListener('keydown', (event) => {
      const record = event.target.closest?.('[data-record-actions].record-actions-trigger');
      if (!record || event.target !== record || !['Enter', ' '].includes(event.key)) return;
      event.preventDefault();
      openRecordActions(record);
    });
    actionDialog.addEventListener('cancel', (event) => { if (activeRecord) { event.preventDefault(); restoreRecordActions(); } });
    actionDialog.addEventListener('close', () => restoreRecordActions());
    actionDialog.addEventListener('click', (event) => {
      if (!activeRecord) return;
      const action = event.target.closest('.dropdown-item');
      if (action) {
        const returnRecord = activeRecord.record;
        const targetSelector = action.getAttribute('data-bs-target');
        const targetModal = targetSelector?.startsWith('#') ? document.querySelector(targetSelector) : null;
        targetModal?.addEventListener('hidden.bs.modal', () => {
          if (returnRecord.isConnected) returnRecord.focus({ preventScroll: true });
        }, { once: true });
        restoreRecordActions({ focus: !action.matches('[data-bs-toggle="modal"]') });
      } else if (event.target.closest('[data-row-actions-close]')) restoreRecordActions();
      else if (event.target === actionDialog) restoreRecordActions();
    }, true);
    enhanceRecordActions();
  }

  const formParams = (form) => {
    const params = new URLSearchParams();
    new FormData(form).forEach((value, key) => {
      if (typeof value === 'string' && value !== '') params.append(key, value);
    });
    return params;
  };

  const requestUrl = (form, params = formParams(form)) => {
    const url = new URL(form.action || window.location.href, window.location.href);
    url.search = params.toString();
    return url;
  };

  const syncForm = (form, params) => {
    form.querySelectorAll('[name]').forEach((field) => {
      const values = params.getAll(field.name);
      if (field instanceof HTMLInputElement && ['checkbox', 'radio'].includes(field.type)) {
        field.checked = values.includes(field.value);
        return;
      }
      if (field instanceof HTMLSelectElement && field.multiple) {
        Array.from(field.options).forEach((option) => { option.selected = values.includes(option.value); });
        return;
      }
      field.value = values[0] ?? (field instanceof HTMLInputElement && field.type === 'hidden' ? field.defaultValue : '');
    });
  };

  const regionSelector = (name) => `[data-live-region="${CSS.escape(name)}"]`;

  const enhanceForm = (form) => {
    const key = form.dataset.liveFilter;
    const regionNames = (form.dataset.liveRegions || 'results').split(/\s+/).filter(Boolean);
    if (!key || regionNames.length === 0) return;

    const status = document.createElement('p');
    status.className = 'text-muted small mt-2 mb-0';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');

    const errorBox = document.createElement('div');
    errorBox.className = 'alert alert-danger mt-3 mb-0 d-none';
    errorBox.setAttribute('role', 'alert');
    const errorText = document.createElement('span');
    const retry = document.createElement('button');
    retry.className = 'btn btn-link alert-link p-0 ms-1 align-baseline';
    retry.type = 'button';
    retry.textContent = 'Tentar novamente';
    errorBox.append(errorText, retry);
    form.after(status, errorBox);

    const state = { controller: null, sequence: 0, timer: null, composing: false, lastUrl: null };
    formStates.set(form, state);

    const setBusy = (busy) => {
      regionNames.forEach((name) => {
        document.querySelector(regionSelector(name))?.setAttribute('aria-busy', String(busy));
      });
    };

    const invalidate = () => {
      window.clearTimeout(state.timer);
      state.controller?.abort();
      state.controller = null;
      state.sequence += 1;
    };

    const run = async ({ url = requestUrl(form), updateHistory = true } = {}) => {
      invalidate();
      const sequence = ++state.sequence;
      const controller = new AbortController();
      state.controller = controller;
      state.lastUrl = url;
      setBusy(true);
      status.textContent = 'Atualizando resultados…';
      errorBox.classList.add('d-none');

      try {
        const response = await fetch(url.href, {
          method: 'GET',
          headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
          cache: 'no-store',
          signal: controller.signal,
        });
        if (response.redirected && new URL(response.url).pathname.endsWith('/login.php')) {
          window.location.assign(response.url);
          return;
        }
        if (!response.ok) throw new Error('Não foi possível atualizar os resultados.');

        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacements = regionNames.map((name) => {
          const current = document.querySelector(regionSelector(name));
          const incoming = parsed.querySelector(regionSelector(name));
          if (!current || !incoming) throw new Error('A página não retornou uma região de resultados válida.');
          return { current, incoming: document.importNode(incoming, true) };
        });
        if (sequence !== state.sequence) return;

        window.OSMais?.refreshActionTables?.();
        window.OSMais?.refreshRecordActions?.();
        replacements.forEach(({ current, incoming }) => current.replaceWith(incoming));
        window.OSMais?.refreshActionTables?.();
        window.OSMais?.refreshRecordActions?.();

        const relativeUrl = url.pathname.split('/').pop() + url.search;
        if (updateHistory) window.history.replaceState({ liveFilter: key }, '', relativeUrl);
        document.querySelectorAll('input[name="return_to"]').forEach((field) => { field.value = relativeUrl; });
        status.textContent = 'Resultados atualizados.';
        errorBox.classList.add('d-none');
        document.dispatchEvent(new CustomEvent('osmais:live-filter-updated', {
          detail: { key, form, regions: replacements.map(({ incoming }) => incoming) },
        }));
      } catch (error) {
        if (error.name === 'AbortError' || sequence !== state.sequence) return;
        errorText.textContent = error.message || 'Não foi possível atualizar os resultados.';
        errorBox.classList.remove('d-none');
        status.textContent = 'Os resultados anteriores foram mantidos.';
      } finally {
        if (sequence === state.sequence) {
          state.controller = null;
          setBusy(false);
        }
      }
    };

    const schedule = () => {
      invalidate();
      state.timer = window.setTimeout(() => run(), 300);
    };

    form.addEventListener('compositionstart', () => { state.composing = true; });
    form.addEventListener('compositionend', (event) => {
      state.composing = false;
      if (event.target.matches('input[type="search"], input[type="text"], input:not([type])')) schedule();
    });
    form.addEventListener('input', (event) => {
      if (state.composing || !event.target.matches('input[type="search"], input[type="text"], input:not([type])')) return;
      schedule();
    });
    form.addEventListener('change', (event) => {
      if (event.target.matches('select, input[type="date"], input[type="datetime-local"], input[type="checkbox"], input[type="radio"]')) run();
    });
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      run();
    });
    form.querySelectorAll('[data-live-filter-clear]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const url = new URL(link.href, window.location.href);
        syncForm(form, url.searchParams);
        run({ url });
        form.querySelector('input[type="search"], input[name="search"]')?.focus();
      });
    });
    retry.addEventListener('click', () => run({ url: state.lastUrl || requestUrl(form) }));

    window.addEventListener('popstate', () => {
      const url = new URL(window.location.href);
      syncForm(form, url.searchParams);
      run({ url, updateHistory: false });
    });
  };

  document.querySelectorAll('form[data-live-filter]').forEach(enhanceForm);
});
