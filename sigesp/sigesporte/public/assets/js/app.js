import './sidebar.js';
import './dropdown.js';
import './modal.js';
import './tabs.js';
import './filters.js';
import './form-stepper.js';
import './upload.js';
import './masks.js';
import './toast.js';
import './charts.js';

export function appUrl(path = '') {
    const basePath = String(window.SIGESP_CONFIG?.basePath ?? '').replace(/\/+$/, '');
    const normalizedPath = `/${String(path).replace(/^\/+/, '')}`;

    if (normalizedPath === '/') {
        return basePath ? `${basePath}/` : '/';
    }
    if (basePath && (normalizedPath === basePath || normalizedPath.startsWith(`${basePath}/`))) {
        return normalizedPath;
    }

    return `${basePath}${normalizedPath}`;
}
