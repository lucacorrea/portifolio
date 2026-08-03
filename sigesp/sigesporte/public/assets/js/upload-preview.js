import { showToast } from './toast.js';

const objectUrls = new Set();

function formatSize(bytes) {
    if (!Number.isFinite(bytes) || bytes < 1) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

function revoke(url) {
    if (!url) return;
    URL.revokeObjectURL(url);
    objectUrls.delete(url);
}

document.querySelectorAll('[data-upload]').forEach((root) => {
    const input = root.querySelector('[data-upload-input]');
    const preview = root.querySelector('[data-upload-preview]');
    const image = root.querySelector('[data-upload-image]');
    const icon = root.querySelector('[data-upload-icon]');
    const name = root.querySelector('[data-upload-name]');
    const meta = root.querySelector('[data-upload-meta]');
    const replace = root.querySelector('[data-upload-replace]');
    const remove = root.querySelector('[data-upload-remove]');
    if (!input || !preview) return;

    let currentUrl = '';
    const reset = () => {
        revoke(currentUrl);
        currentUrl = '';
        input.value = '';
        preview.hidden = true;
        if (image) {
            image.hidden = true;
            image.removeAttribute('src');
        }
        if (icon) icon.hidden = false;
        input.focus();
    };

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;
        revoke(currentUrl);
        currentUrl = URL.createObjectURL(file);
        objectUrls.add(currentUrl);
        if (name) name.textContent = file.name;
        if (meta) meta.textContent = `${formatSize(file.size)} · ${file.type || 'tipo não informado'}`;
        const isImage = file.type.startsWith('image/');
        if (image) {
            image.hidden = !isImage;
            if (isImage) image.src = currentUrl;
        }
        if (icon) icon.hidden = isImage;
        preview.hidden = false;
        showToast('Arquivo selecionado apenas para demonstração.');
    });

    replace?.addEventListener('click', () => input.click());
    remove?.addEventListener('click', reset);
});

window.addEventListener('pagehide', () => [...objectUrls].forEach(revoke), { once: true });

