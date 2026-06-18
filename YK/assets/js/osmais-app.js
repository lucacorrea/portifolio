document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('app-sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  const toggles = document.querySelectorAll('[data-sidebar-toggle]');

  const closeSidebar = () => {
    sidebar?.classList.remove('is-open');
    backdrop?.classList.remove('is-visible');
    document.body.classList.remove('sidebar-open');
  };

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      sidebar?.classList.toggle('is-open');
      backdrop?.classList.toggle('is-visible');
      document.body.classList.toggle('sidebar-open');
    });
  });

  backdrop?.addEventListener('click', closeSidebar);

  document.querySelectorAll('.os-sidebar a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.matchMedia('(max-width: 920px)').matches) closeSidebar();
    });
  });

  document.querySelectorAll('[title]').forEach((el) => {
    new bootstrap.Tooltip(el);
  });

  document.querySelectorAll('[data-weekly-fullscreen-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const page = document.querySelector('.weekly-page');
      if (!page) return;
      const isFull = page.classList.toggle('weekly-fullscreen');
      const label = button.querySelector('span');
      const icon = button.querySelector('i');
      if (label) label.textContent = isFull ? 'Sair tela cheia' : 'Tela cheia';
      if (icon) icon.className = isFull ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
    });
  });
});
