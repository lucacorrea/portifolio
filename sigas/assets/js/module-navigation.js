'use strict';

/* A configuração vem de ModuleRegistry no servidor. Ocultar menus não autoriza rotas. */
(() => {
    const aliases = { 'assistencia-social': 'protecao-social-basica', 'comida-mesa': 'comida-mesa', 'primeiro-emprego': 'primeiro-emprego' };
    const escapeHTML = value => String(value || '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
    const prefixUrl = (href, prefix) => prefix && href.startsWith('primeiro-emprego/') ? href.slice('primeiro-emprego/'.length) : `${prefix}${href}`;

    function itemMarkup(item, page, prefix) {
        const active = item.page === page;
        return `<a class="module-nav-link${active ? ' active' : ''}" href="${escapeHTML(prefixUrl(item.href, prefix))}"${active ? ' aria-current="page"' : ''}><i class="bi bi-${escapeHTML(item.icon)}"></i><span>${escapeHTML(item.label)}</span></a>`;
    }

    function hydrate() {
        const body = document.body;
        const key = aliases[body.dataset.module] || body.dataset.module;
        const menu = window.SIGAS_CONTEXT?.navigation?.[key];
        const sidebar = document.querySelector('#appSidebar');
        const topbar = document.querySelector('#appTopbar');
        const bottom = document.querySelector('#bottomNavigation');
        if (!menu || !sidebar || !topbar) return;

        const page = body.dataset.modulePage || body.dataset.page || '';
        const prefix = body.dataset.moduleUrlPrefix || (key === 'primeiro-emprego' ? '../' : '');
        const portal = body.dataset.portalUrl || prefixUrl('portal.php', prefix);
        const items = menu.items || [];
        const mobile = items.filter(item => item.mobile).slice(0, 4);
        sidebar.classList.add('module-sidebar');
        sidebar.innerHTML = `<div class="module-sidebar-head"><a class="module-brand" href="${escapeHTML(prefixUrl(menu.home, prefix))}"><i class="bi bi-${escapeHTML(menu.icon)}"></i><span><small>${menu.kind === 'module' ? 'Módulo SIGAS' : 'Setor SEMAS'}</small><strong>${escapeHTML(menu.name)}</strong></span></a><button class="btn btn-light btn-icon sidebar-close" type="button" data-sidebar-close aria-label="Fechar menu"><i class="bi bi-x-lg"></i></button></div><nav class="module-nav" aria-label="Navegação de ${escapeHTML(menu.name)}">${items.map(item => itemMarkup(item, page, prefix)).join('')}</nav><div class="module-sidebar-footer"><a href="${escapeHTML(portal)}" class="module-switch-link"><i class="bi bi-grid"></i>Trocar setor ou módulo</a></div>`;
        topbar.innerHTML = `<button class="btn btn-light btn-icon" type="button" data-sidebar-toggle aria-label="Abrir menu" aria-expanded="false"><i class="bi bi-list"></i></button><div class="module-existing-title"><span class="module-topbar-kicker">SIGAS / ${menu.kind === 'module' ? 'Módulo' : 'Setor'}</span><strong>${escapeHTML(menu.name)}</strong></div><a class="btn btn-outline-secondary btn-sm module-switch-button ms-auto" href="${escapeHTML(portal)}"><i class="bi bi-grid"></i><span>Trocar setor ou módulo</span></a>`;
        if (bottom) bottom.innerHTML = `<nav class="module-mobile-nav module-mobile-nav--legacy" aria-label="Navegação móvel de ${escapeHTML(menu.name)}">${mobile.map(item => itemMarkup(item, page, prefix)).join('')}<button type="button" data-sidebar-toggle aria-label="Mais opções"><i class="bi bi-three-dots"></i><span>Mais</span></button></nav>`;
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', hydrate, { once: true });
    else hydrate();
})();
