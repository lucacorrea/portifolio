(function () {
  'use strict';

  const form = document.querySelector('[data-company-logo-form]');
  if (!form) return;

  const input = form.querySelector('input[name="logo_file"]');
  const remove = form.querySelector('[data-remove-company-logo]');
  const preview = form.querySelector('[data-company-logo-preview]');
  const image = preview?.querySelector('img');
  const fallback = preview?.querySelector('span');
  const status = document.getElementById('company-logo-status');
  const currentUrl = form.dataset.currentLogoUrl || '';
  const allowedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
  const maxBytes = 5 * 1024 * 1024;
  let objectUrl = '';

  const releaseObjectUrl = () => {
    if (objectUrl === '') return;
    URL.revokeObjectURL(objectUrl);
    objectUrl = '';
  };

  const showPreview = (source, message) => {
    if (!image || !fallback) return;
    if (source === '') image.removeAttribute('src');
    else image.setAttribute('src', source);
    image.classList.toggle('show', source !== '');
    fallback.classList.toggle('d-none', source !== '');
    if (status) status.textContent = message;
  };

  input?.addEventListener('change', () => {
    releaseObjectUrl();
    const file = input.files?.[0];
    if (!file) {
      showPreview(remove?.checked ? '' : currentUrl, 'Nenhuma nova logo selecionada.');
      return;
    }

    if ((file.type !== '' && !allowedTypes.has(file.type)) || file.size <= 0 || file.size > maxBytes) {
      input.value = '';
      showPreview(currentUrl, 'Arquivo inválido. Selecione uma imagem JPEG, PNG ou WebP de até 5 MB.');
      return;
    }

    objectUrl = URL.createObjectURL(file);
    if (remove) remove.checked = false;
    showPreview(objectUrl, 'Prévia da nova logo selecionada.');
  });

  remove?.addEventListener('change', () => {
    if (remove.checked && input) input.value = '';
    releaseObjectUrl();
    showPreview(remove.checked ? '' : currentUrl, remove.checked ? 'A logo atual será removida.' : 'Logo atual mantida.');
  });

  image?.addEventListener('error', () => {
    if (!image.hasAttribute('src')) return;
    image.classList.remove('show');
    fallback?.classList.remove('d-none');
    if (status) status.textContent = 'Não foi possível exibir a prévia da logo.';
  });

  window.addEventListener('beforeunload', releaseObjectUrl);
})();
