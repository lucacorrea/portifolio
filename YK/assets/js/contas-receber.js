document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function setValue(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value || '';
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest?.('.js-cr-payment');
    if (!button) return;
    setValue('cr-payment-id', button.dataset.id);
    setValue('cr-payment-value', button.dataset.balance);
  });
});
