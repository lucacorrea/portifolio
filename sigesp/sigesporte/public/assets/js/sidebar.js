const sidebar = document.querySelector('#sidebar');
const backdrop = document.querySelector('.app-backdrop');
const menuButton = document.querySelector('[data-sidebar-toggle]');
const compactButton = document.querySelector('[data-sidebar-compact]');
const fullscreenButton = document.querySelector('[data-fullscreen-toggle]');
const appMain = document.querySelector('.app-main');
const skipLink = document.querySelector('.skip-link');
const desktopMedia = window.matchMedia('(min-width: 961px)');
const compactStorageKey = 'sigesp.sidebar.compact';

function getCompactPreference() {
    try {
        return window.localStorage.getItem(compactStorageKey) === 'true';
    } catch {
        return false;
    }
}

function setCompactPreference(compact) {
    try {
        window.localStorage.setItem(compactStorageKey, String(compact));
    } catch {
        // O layout continua funcional quando o navegador bloqueia armazenamento local.
    }
}

function syncCompactState() {
    const compact = desktopMedia.matches && getCompactPreference();
    sidebar?.classList.toggle('is-compact', compact);
    compactButton?.setAttribute('aria-expanded', String(!compact));
    compactButton?.setAttribute('aria-label', compact ? 'Expandir menu' : 'Recolher menu');
    if (compactButton) compactButton.textContent = compact ? '›' : '‹';
}

function setBackgroundInert(inert) {
    if (appMain) {
        appMain.inert = inert;
        if (inert) appMain.setAttribute('aria-hidden', 'true');
        else appMain.removeAttribute('aria-hidden');
    }
    if (skipLink) skipLink.inert = inert;
}

function setSidebarAvailable(available) {
    if (!sidebar) return;
    sidebar.inert = !available;
    if (available) sidebar.removeAttribute('aria-hidden');
    else sidebar.setAttribute('aria-hidden', 'true');
}

function closeMobileMenu({ restoreFocus = false } = {}) {
    if (desktopMedia.matches) return;
    sidebar?.classList.remove('is-open');
    backdrop?.classList.remove('is-open');
    document.body.classList.remove('sidebar-open');
    setBackgroundInert(false);
    menuButton?.setAttribute('aria-expanded', 'false');
    menuButton?.setAttribute('aria-label', 'Abrir menu');
    compactButton?.setAttribute('aria-expanded', 'false');
    compactButton?.setAttribute('aria-label', 'Fechar menu');
    if (compactButton) compactButton.textContent = '×';
    if (restoreFocus) menuButton?.focus();
    setSidebarAvailable(false);
}

function openMobileMenu() {
    if (desktopMedia.matches) return;
    sidebar?.classList.add('is-open');
    setSidebarAvailable(true);
    backdrop?.classList.add('is-open');
    document.body.classList.add('sidebar-open');
    menuButton?.setAttribute('aria-expanded', 'true');
    menuButton?.setAttribute('aria-label', 'Fechar menu');
    compactButton?.setAttribute('aria-expanded', 'true');
    compactButton?.setAttribute('aria-label', 'Fechar menu');
    if (compactButton) compactButton.textContent = '×';
    compactButton?.focus();
    setBackgroundInert(true);
}

menuButton?.addEventListener('click', () => {
    if (sidebar?.classList.contains('is-open')) {
        closeMobileMenu();
        return;
    }
    openMobileMenu();
});

document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => closeMobileMenu({ restoreFocus: true }));

sidebar?.querySelectorAll('.sidebar__nav a').forEach((link) => {
    link.addEventListener('click', () => {
        const keepsCurrentPage = link.hasAttribute('data-toast') || link.getAttribute('href') === '#';
        if (keepsCurrentPage) closeMobileMenu({ restoreFocus: true });
    });
});

compactButton?.addEventListener('click', () => {
    if (!desktopMedia.matches) {
        closeMobileMenu({ restoreFocus: true });
        return;
    }
    const compact = !sidebar?.classList.contains('is-compact');
    setCompactPreference(compact);
    syncCompactState();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sidebar?.classList.contains('is-open')) {
        closeMobileMenu({ restoreFocus: true });
    }
});

desktopMedia.addEventListener('change', () => {
    sidebar?.classList.remove('is-open');
    backdrop?.classList.remove('is-open');
    document.body.classList.remove('sidebar-open');
    setBackgroundInert(false);
    menuButton?.setAttribute('aria-expanded', 'false');
    menuButton?.setAttribute('aria-label', 'Abrir menu');
    setSidebarAvailable(desktopMedia.matches);
    syncCompactState();
});

function syncFullscreenState() {
    const active = Boolean(document.fullscreenElement);
    fullscreenButton?.setAttribute('aria-pressed', String(active));
    fullscreenButton?.setAttribute('aria-label', active ? 'Sair da tela cheia' : 'Entrar em tela cheia');
}

fullscreenButton?.addEventListener('click', async () => {
    try {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else {
            await document.documentElement.requestFullscreen?.();
        }
    } catch {
        // O navegador pode bloquear tela cheia sem alterar a navegação principal.
    }
});

document.addEventListener('fullscreenchange', syncFullscreenState);
syncCompactState();
setSidebarAvailable(desktopMedia.matches);
syncFullscreenState();
