(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    var button = event.target.closest('.js-reverse-sale');
    if (!button) return;
    var id = document.getElementById('cash-reversal-sale-id');
    var number = document.getElementById('cash-reversal-sale-number');
    if (id) id.value = button.dataset.saleId || '';
    if (number) number.textContent = button.dataset.saleNumber || '—';
  });
}());
