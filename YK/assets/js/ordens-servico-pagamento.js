document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const modal = document.getElementById('modal-os-pay');
  const form = modal?.querySelector('form');
  if (!modal || !form || !window.bootstrap) return;

  const dataNode = document.getElementById('os-page-data');
  const pageData = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
  const recoveryModal = pageData.recoveryModal || new URLSearchParams(window.location.search).get('modal');
  const recoveryData = pageData.recoveryData || {};
  const prompt = pageData.postCompletionPaymentPrompt || null;
  const method = document.getElementById('os-pay-method');
  const installments = document.getElementById('os-pay-installments');
  const installmentGroup = document.getElementById('os-pay-installments-group');
  const boletoWarning = document.getElementById('os-pay-boleto-warning');
  const question = document.getElementById('os-pay-question');
  const fields = document.getElementById('os-pay-fields');
  const questionActions = document.getElementById('os-pay-question-actions');
  const formActions = document.getElementById('os-pay-form-actions');

  function setValue(id, value) {
    const input = document.getElementById(id);
    if (input) input.value = value == null ? '' : String(value);
  }

  function setText(id, value) {
    const node = document.getElementById(id);
    if (node) node.textContent = String(value || '');
  }

  function parseNumber(value) {
    value = String(value || '0').replace(/\s/g, '');
    if (value.includes(',')) value = value.replace(/\./g, '').replace(',', '.');
    return Math.max(0, Number.parseFloat(value) || 0);
  }

  function money(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  function paymentToken() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
      const random = Math.floor(Math.random() * 16);
      const value = character === 'x' ? random : ((random & 3) | 8);
      return value.toString(16);
    });
  }

  function clearModalQuery() {
    const url = new URL(window.location.href);
    if (!url.searchParams.has('modal')) return;
    url.searchParams.delete('modal');
    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
  }

  function updateInstallments() {
    const usesInstallments = method.value === 'boleto' || method.value === 'cartao_credito';
    installmentGroup.hidden = !usesInstallments;
    installments.required = usesInstallments;
    if (!usesInstallments) installments.value = '1';
    boletoWarning.hidden = method.value !== 'boleto';
  }

  function showQuestion() {
    question.hidden = false;
    fields.hidden = true;
    questionActions.hidden = false;
    formActions.hidden = true;
    setText('os-pay-title', 'Serviço concluído');
    setText('os-pay-subtitle', 'A conclusão foi confirmada. Falta informar a situação do pagamento.');
  }

  function showPaymentForm() {
    question.hidden = true;
    fields.hidden = false;
    questionActions.hidden = true;
    formActions.hidden = false;
    setText('os-pay-title', 'Registrar pagamento da OS');
    setText('os-pay-subtitle', 'O recebimento será lançado no Caixa e gerará um recibo.');
    updateInstallments();
    if (modal.classList.contains('show')) document.getElementById('os-pay-value')?.focus();
  }

  function preparePayment(data, askAfterCompletion) {
    const hasRecoveredValue = Object.prototype.hasOwnProperty.call(data, 'valor');
    const total = hasRecoveredValue
      ? String(data.valor || '')
      : String(data.balance ?? data.total ?? '0').replace('.', ',');
    setValue('os-pay-id', data.order_id ?? data.id);
    setValue('os-pay-value', total);
    setValue('os-pay-token', data.payment_token || paymentToken());
    setValue('os-pay-notes', data.observacao || '');
    setValue('os-pay-method', data.forma_pagamento || 'dinheiro');
    setValue('os-pay-installments', data.quantidade_parcelas || '1');
    setText('os-pay-summary', (data.order_number || 'OS') + ' — saldo ' + money(parseNumber(total)) + '. Informe o valor efetivamente recebido.');
    const completion = document.getElementById('os-pay-completion');
    completion.hidden = !askAfterCompletion;
    completion.textContent = askAfterCompletion
      ? (data.order_number || 'OS') + ' foi concluída com sucesso.'
      : '';
    const error = document.getElementById('os-pay-error');
    error.hidden = !data.error;
    error.textContent = data.error || '';
    askAfterCompletion ? showQuestion() : showPaymentForm();
  }

  document.addEventListener('click', function (event) {
    const payButton = event.target.closest?.('.js-os-pay');
    if (payButton) {
      preparePayment({
        order_id: payButton.dataset.orderId,
        order_number: payButton.dataset.orderNumber,
        balance: payButton.dataset.orderTotal,
      }, false);
      return;
    }
    if (event.target.closest?.('#os-pay-confirm-paid')) showPaymentForm();
  });

  method.addEventListener('change', updateInstallments);
  modal.addEventListener('shown.bs.modal', function () {
    if (!question.hidden) {
      document.getElementById('os-pay-leave-pending')?.focus();
      return;
    }
    document.getElementById('os-pay-value')?.focus();
  });
  form.addEventListener('submit', function (event) {
    if (!form.checkValidity()) return;
    const submit = event.submitter || form.querySelector('[type="submit"]');
    if (submit) {
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
    }
  });

  if (recoveryModal === 'pay' && recoveryData.id) {
    preparePayment(Object.assign({}, recoveryData, { error: pageData.recoveryError || '' }), false);
    bootstrap.Modal.getOrCreateInstance(modal).show();
    clearModalQuery();
  } else if (prompt) {
    preparePayment(prompt, true);
    bootstrap.Modal.getOrCreateInstance(modal).show();
  } else if (recoveryModal === 'pay') {
    clearModalQuery();
  }
});
