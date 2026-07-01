document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function setValue(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value || '';
  }

  document.querySelectorAll('.js-cr-payment').forEach(function (button) {
    button.addEventListener('click', function () {
      setValue('cr-payment-id', button.dataset.id);
      setValue('cr-payment-value', button.dataset.balance);
    });
  });
});
