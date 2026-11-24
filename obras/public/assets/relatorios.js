'use strict';
document.addEventListener('DOMContentLoaded', function () {
  const printButton = document.getElementById('print-now');
  if (printButton) printButton.addEventListener('click', () => window.print());
});
