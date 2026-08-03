export function appUrl(path = '') {
    const basePath = String(window.SIGESP_CONFIG?.basePath ?? '').replace(/\/+$/, '');
    const rawPath = String(path).trim();

    if (/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(rawPath) || rawPath.startsWith('#')) {
        return rawPath;
    }

    const normalizedPath = `/${rawPath.replace(/^\/+/, '')}`;
    if (normalizedPath === '/') {
        return basePath ? `${basePath}/` : '/';
    }
    if (basePath && (normalizedPath === basePath || normalizedPath.startsWith(`${basePath}/`))) {
        return normalizedPath;
    }

    return `${basePath}${normalizedPath}`;
}
