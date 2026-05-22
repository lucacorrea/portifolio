/* ===== app.js ===== */
document.addEventListener('DOMContentLoaded', function () {
  // Sidebar toggle para mobile
  const menuToggle = document.getElementById('menu-toggle');
  const sidebar = document.querySelector('.sidebar');
  const menuOverlay = document.getElementById('menu-overlay');

  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (menuOverlay) menuOverlay.classList.toggle('hidden');
    });
  }

  if (menuOverlay) {
    menuOverlay.addEventListener('click', function () {
      sidebar.classList.remove('open');
      menuOverlay.classList.add('hidden');
    });
  }

  // Dropdown simples (perfil)
  const profileBtn = document.getElementById('profile-btn');
  const profileDropdown = document.getElementById('profile-dropdown');
  if (profileBtn && profileDropdown) {
    profileBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      profileDropdown.classList.toggle('hidden');
    });
    document.addEventListener('click', function () {
      profileDropdown.classList.add('hidden');
    });
  }

  // Atualiza ano no footer
  const yearSpan = document.getElementById('current-year');
  if (yearSpan) {
    yearSpan.textContent = new Date().getFullYear();
  }
});

// Função AJAX genérica para buscar dados do dashboard
function fetchDashboardData() {
  fetch('/refri/api/dashboard_data.php')
    .then(response => response.json())
    .then(data => {
      // Atualiza métricas
      document.getElementById('metric-total-os').innerText = data.total_os || 0;
      document.getElementById('metric-os-abertas').innerText = data.os_abertas || 0;
      document.getElementById('metric-faturamento').innerText = 'R$ ' + (data.faturamento || 0).toFixed(2);
      document.getElementById('metric-clientes').innerText = data.clientes || 0;
    })
    .catch(error => console.error('Erro ao carregar dashboard:', error));
}

// Chamada inicial
document.addEventListener('DOMContentLoaded', function () {
  if (document.querySelector('.dashboard-grid')) {
    fetchDashboardData();
    // Atualiza a cada 60 segundos se desejar
    setInterval(fetchDashboardData, 60000);
  }
});

// Função para aplicar filtros via AJAX em tabelas
function aplicarFiltros() {
  const form = document.getElementById('filtros-form');
  const formData = new FormData(form);
  const params = new URLSearchParams(formData).toString();

  fetch('/refri/api/filtrar_os.php?' + params)
    .then(res => res.text())
    .then(html => {
      document.getElementById('tabela-os-body').innerHTML = html;
    })
    .catch(err => console.error(err));
}

// Permite que o formulário chame aplicarFiltros sem reload
document.addEventListener('submit', function (e) {
  if (e.target && e.target.id === 'filtros-form') {
    e.preventDefault();
    aplicarFiltros();
  }
});