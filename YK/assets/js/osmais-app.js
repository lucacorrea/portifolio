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
});
