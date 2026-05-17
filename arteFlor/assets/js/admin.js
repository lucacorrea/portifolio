(function () {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-demo-form]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();

        if (form.classList.contains('login-card')) {
          ArteFlor.toast('Login demonstrativo. Abrindo painel...');
          window.setTimeout(() => {
            window.location.href = form.getAttribute('action');
          }, 500);
          return;
        }

        ArteFlor.toast('Ação simulada. Nenhum dado foi salvo em backend.');
      });
    });

    document.querySelectorAll('[data-demo-action]').forEach((element) => {
      element.addEventListener('click', () => {
        ArteFlor.toast('Ação administrativa simulada neste MVP.');
      });
      element.addEventListener('change', () => {
        ArteFlor.toast('Filtro visual aplicado apenas para demonstração.');
      });
    });

    document.querySelectorAll('[data-admin-search]').forEach((input) => {
      input.addEventListener('input', () => {
        const term = input.value.toLocaleLowerCase('pt-BR').trim();
        document.querySelectorAll('[data-admin-row]').forEach((row) => {
          row.hidden = term && !row.textContent.toLocaleLowerCase('pt-BR').includes(term);
        });
      });
    });
  });
})();
