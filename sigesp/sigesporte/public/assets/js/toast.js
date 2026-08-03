const DEFAULT_DURATION = 4200;

function toastRegion() {
    let region = document.querySelector('[data-toast-region]');
    if (region) return region;

    region = document.createElement('div');
    region.className = 'toast-region';
    region.dataset.toastRegion = '';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    region.setAttribute('aria-relevant', 'additions');
    document.body.append(region);
    return region;
}

export function showToast(message, options = {}) {
    const text = String(message || 'Operação simulada com sucesso no ambiente de demonstração.');
    const toast = document.createElement('div');
    toast.className = `toast${options.tone ? ` toast--${options.tone}` : ''}`;
    toast.setAttribute('role', options.tone === 'error' ? 'alert' : 'status');
    toast.textContent = text;
    toastRegion().append(toast);

    const duration = Number(options.duration ?? DEFAULT_DURATION);
    if (duration > 0) {
        window.setTimeout(() => toast.remove(), duration);
    }
    return toast;
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-toast]');
    if (!trigger) return;
    if (trigger.matches('a')) event.preventDefault();
    showToast(trigger.dataset.toast, { tone: trigger.dataset.toastTone });
});

window.sigespToast = showToast;
