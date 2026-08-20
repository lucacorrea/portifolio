document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function setValue(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value || '';
  }
  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value || '';
  }


  function selectedAccounts() {
    return Array.from(document.querySelectorAll('.js-cr-batch-account:checked'));
  }

  function cents(value) {
    const normalized = String(value || '0').trim().replace(/\s/g, '').replace(/\.(?=\d{3}(?:\D|$))/g, '').replace(',', '.');
    const parts = normalized.split('.');
    return (Number.parseInt(parts[0] || '0', 10) * 100) + Number.parseInt((parts[1] || '').padEnd(2, '0').slice(0, 2) || '0', 10);
  }

  function moneyFromCents(value) {
    return (value / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  function updateAccountEditPreview() {
    const form = document.getElementById('cr-account-edit-form');
    const totalInput = document.getElementById('cr-account-edit-total');
    const balanceInput = document.getElementById('cr-account-edit-balance');
    const help = document.getElementById('cr-account-edit-total-help');
    if (!form || !totalInput || !balanceInput) return;

    const received = Number.parseInt(form.dataset.receivedCents || '0', 10) || 0;
    const total = cents(totalInput.value);
    const valid = total > 0 && total >= received;
    totalInput.setCustomValidity(valid ? '' : 'O valor total deve ser maior que zero e não pode ser menor que o valor já recebido.');
    balanceInput.value = moneyFromCents(Math.max(0, total - received));
    if (help) help.textContent = received > 0
      ? 'Mínimo permitido: ' + moneyFromCents(received) + ' (valor já recebido).'
      : 'Informe o valor total negociado para esta conta.';
  }

  function updateBatchSelection(message) {
    const selected = selectedAccounts();
    const activeClient = selected[0]?.dataset.clientId || '';
    document.querySelectorAll('.js-cr-batch-account').forEach(function (checkbox) {
      const incompatible = activeClient !== '' && checkbox.dataset.clientId !== activeClient && !checkbox.checked;
      checkbox.disabled = incompatible;
      checkbox.closest('tr')?.classList.toggle('cr-batch-incompatible', incompatible);
      checkbox.closest('tr')?.classList.toggle('cr-batch-selected', checkbox.checked);
    });

    const total = selected.reduce(function (sum, checkbox) { return sum + cents(checkbox.dataset.balance); }, 0);
    const openButton = document.getElementById('cr-batch-open');
    if (openButton) openButton.disabled = selected.length < 2;
    const status = document.getElementById('cr-batch-selection');
    if (!status) return;
    if (message) status.textContent = message;
    else if (selected.length === 0) status.textContent = 'Selecione pelo menos duas contas em aberto do mesmo cliente para dar baixa de uma só vez.';
    else status.textContent = selected.length + ' conta(s) de ' + selected[0].dataset.clientName + ' selecionada(s) — total ' + moneyFromCents(total) + '.';
  }

  function prepareBatchModal(event) {
    const selected = selectedAccounts();
    if (selected.length < 2) { event?.preventDefault(); updateBatchSelection('Selecione pelo menos duas contas para realizar a baixa em lote.'); return; }
    const clientId = selected[0].dataset.clientId;
    if (selected.some(function (checkbox) { return checkbox.dataset.clientId !== clientId; })) {
      event?.preventDefault(); updateBatchSelection('Selecione somente contas do mesmo cliente.'); return;
    }

    setValue('cr-batch-payment-date', '');

    const hiddenHost = document.getElementById('cr-batch-hidden-ids');
    const list = document.getElementById('cr-batch-account-list');
    const summary = document.getElementById('cr-batch-summary');
    hiddenHost?.replaceChildren(); list?.replaceChildren();
    let total = 0;
    selected.forEach(function (checkbox) {
      const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'account_ids[]'; hidden.value = checkbox.value; hiddenHost?.appendChild(hidden);
      const row = document.createElement('div'); row.className = 'cr-batch-account-item';
      const label = document.createElement('span'); label.textContent = checkbox.dataset.order;
      const value = document.createElement('strong'); value.textContent = moneyFromCents(cents(checkbox.dataset.balance));
      row.append(label, value); list?.appendChild(row); total += cents(checkbox.dataset.balance);
    });
    if (summary) summary.textContent = selected.length + ' contas de ' + selected[0].dataset.clientName + ' — total a receber ' + moneyFromCents(total) + '.';
  }

  document.addEventListener('click', function (event) {
    const accountEditButton = event.target.closest?.('.js-cr-account-edit');
    if (accountEditButton) {
      const form = document.getElementById('cr-account-edit-form');
      const receivedValue = cents(accountEditButton.dataset.received);
      if (form) form.dataset.receivedCents = String(receivedValue);
      setValue('cr-account-edit-id', accountEditButton.dataset.id);
      setValue('cr-account-edit-client', accountEditButton.dataset.client);
      setValue('cr-account-edit-order', accountEditButton.dataset.order);
      setValue('cr-account-edit-status', accountEditButton.dataset.status);
      setValue('cr-account-edit-total', accountEditButton.dataset.total);
      setValue('cr-account-edit-received', moneyFromCents(receivedValue));
      setValue('cr-account-edit-due', accountEditButton.dataset.due);
      setValue('cr-account-edit-reminder', accountEditButton.dataset.reminder);
      setValue('cr-account-edit-notes', accountEditButton.dataset.notes);
      setText('cr-account-edit-subtitle', (accountEditButton.dataset.order || '') + ' — ' + (accountEditButton.dataset.client || ''));
      updateAccountEditPreview();
      return;
    }

    const paymentButton = event.target.closest?.('.js-cr-payment');
    if (paymentButton) {
      setValue('cr-payment-id', paymentButton.dataset.id);
      setValue('cr-payment-value', paymentButton.dataset.balance);
      setValue('cr-payment-date', '');
      return;
    }
    const editPaymentButton = event.target.closest?.('.js-cr-payment-edit');
    if (editPaymentButton) {
      setValue('cr-payment-edit-id', editPaymentButton.dataset.paymentId);
      setValue('cr-payment-edit-value', editPaymentButton.dataset.value);
      setValue('cr-payment-edit-form', editPaymentButton.dataset.form);
      setValue('cr-payment-edit-date', editPaymentButton.dataset.date);
      setValue('cr-payment-edit-notes', editPaymentButton.dataset.notes);
      setText('cr-payment-edit-label', editPaymentButton.dataset.paymentLabel);
      return;
    }

    const deletePaymentButton = event.target.closest?.('.js-cr-payment-delete');
    if (deletePaymentButton) {
      setValue('cr-payment-delete-id', deletePaymentButton.dataset.paymentId);
      setValue('cr-payment-delete-reason', '');
      setText('cr-payment-delete-label', deletePaymentButton.dataset.paymentLabel);
      return;
    }

    const receiptButton = event.target.closest?.('.js-cr-receipt');
    if (receiptButton) {
      setValue('cr-receipt-payment-id', receiptButton.dataset.paymentId);
      setText('cr-receipt-order-number', receiptButton.dataset.orderNumber);
      setText('cr-receipt-payment-label', receiptButton.dataset.paymentLabel);
      return;
    }

    if (event.target.closest?.('#cr-batch-open')) prepareBatchModal(event);
  });

  document.addEventListener('change', function (event) {
    const checkbox = event.target.closest?.('.js-cr-batch-account');
    if (!checkbox) return;
    const selected = selectedAccounts();
    if (selected.some(function (item) { return item.dataset.clientId !== checkbox.dataset.clientId; })) {
      checkbox.checked = false;
      updateBatchSelection('As contas da baixa em lote devem pertencer ao mesmo cliente.');
      return;
    }
    updateBatchSelection();
  });

  document.getElementById('cr-account-edit-total')?.addEventListener('input', updateAccountEditPreview);
  document.getElementById('cr-account-edit-form')?.addEventListener('submit', function (event) {
    updateAccountEditPreview();
    const totalInput = document.getElementById('cr-account-edit-total');
    if (!totalInput?.checkValidity()) {
      event.preventDefault();
      totalInput?.reportValidity();
      totalInput?.focus();
      return;
    }
    const submit = event.currentTarget.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
  });

  document.querySelector('#modal-cr-payment-edit form')?.addEventListener('submit', function (event) {
    const submit = event.currentTarget.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
  });
  document.querySelector('#modal-cr-payment-delete form')?.addEventListener('submit', function (event) {
    const reason = event.currentTarget.querySelector('[name="motivo"]');
    if (!reason || String(reason.value || '').trim() === '') {
      event.preventDefault();
      reason?.focus();
      return;
    }
    const submit = event.currentTarget.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
  });

  document.getElementById('modal-cr-batch')?.addEventListener('show.bs.modal', prepareBatchModal);
  document.getElementById('cr-batch-form')?.addEventListener('submit', function (event) {
    const ids = event.currentTarget.querySelectorAll('input[name="account_ids[]"]');
    if (ids.length < 2) { event.preventDefault(); updateBatchSelection('Seleção inválida. Escolha novamente as contas.'); return; }
    const submit = event.currentTarget.querySelector('[type="submit"]'); if (submit) submit.disabled = true;
  });
  document.addEventListener('osmais:live-filter-updated', function (event) {
    if (event.detail?.key === 'receivables') updateBatchSelection();
  });
  updateBatchSelection();
});
