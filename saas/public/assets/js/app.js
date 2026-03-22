document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.nav-link[href="#"], .btn[href="#"], .quick-card[href="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      alert('Esse módulo será ligado depois.');
    });
  });
});
