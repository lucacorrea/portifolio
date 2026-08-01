document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-password-toggle]');
  const password = document.getElementById('password');
  const form = document.querySelector('[data-auth-form]');

  toggle?.addEventListener('click', () => {
    if (!password) return;
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
    const icon = toggle.querySelector('i');
    if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  form?.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    button?.setAttribute('aria-busy', 'true');
  });
});
