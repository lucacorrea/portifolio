'use strict';

/*
 * Navegação visual reutilizável dos módulos SIGAS.
 * A permissão definitiva deve ser sempre validada no PHP antes de renderizar
 * ou atender a rota solicitada. Ocultar um item no navegador não é controle de acesso.
 */
(function () {
    const portalUrl = 'portal.php';
    const moduleMenus = {
        assistenciaSocial: {
            label: 'Assistência Social', icon: 'heart-pulse', tone: 'social', home: 'dashboard.php',
            items: [
                { label: 'Painel', icon: 'speedometer2', href: 'dashboard.php', page: 'assistencia-painel' },
                { group: 'Atendimento', items: [
                    { label: 'Pessoas e prontuários', icon: 'people', href: 'pessoas.php', page: 'assistencia-pessoas' },
                    { label: 'Famílias', icon: 'house-heart', href: 'familias.php', page: 'assistencia-familias' },
                    { label: 'Solicitações', icon: 'inboxes', href: 'solicitacoes.php', page: 'assistencia-solicitacoes' },
                    { label: 'Atendimentos', icon: 'clipboard2-pulse', href: 'atendimentos.php', page: 'assistencia-atendimentos' },
                    { label: 'Benefícios', icon: 'gift', href: 'beneficios.php', page: 'assistencia-beneficios' }
                ]},
                { group: 'Programas', items: [
                    { label: 'Auxílio-Natalidade', icon: 'balloon-heart', href: 'natalidade.php', page: 'assistencia-natalidade' },
                    { label: 'Auxílio-Funeral', icon: 'flower1', href: 'funeral.php', page: 'assistencia-funeral' },
                    { label: 'Criança e Adolescente', icon: 'emoji-smile', href: 'crianca.php', page: 'assistencia-crianca' },
                    { label: 'Cidadania', icon: 'person-vcard', href: 'cidadania.php', page: 'assistencia-cidadania' },
                    { label: 'Outros programas', icon: 'grid', href: 'outros.php', page: 'assistencia-outros' }
                ]},
                { group: 'Rede e gestão', items: [
                    { label: 'Unidades', icon: 'buildings', href: 'unidades.php', page: 'assistencia-unidades' },
                    { label: 'CRAS 1', icon: 'geo-alt', href: 'cras1.php', page: 'assistencia-cras1' },
                    { label: 'CRAS 2', icon: 'geo-alt', href: 'cras2.php', page: 'assistencia-cras2' },
                    { label: 'CREAS', icon: 'shield-check', href: 'creas.php', page: 'assistencia-creas' },
                    { label: 'Casa do Cidadão', icon: 'house-door', href: 'casa.php', page: 'assistencia-casa' },
                    { label: 'Integração SEMTH', icon: 'link-45deg', href: 'integracao-semth.php', page: 'assistencia-semth' },
                    { label: 'Relatórios', icon: 'bar-chart-line', href: 'relatorios.php', page: 'assistencia-relatorios' }
                ]}
            ],
            mobile: ['assistencia-painel', 'assistencia-pessoas', 'assistencia-solicitacoes', 'assistencia-atendimentos']
        },
        comidaMesa: {
            label: 'Coari Comida na Mesa', icon: 'basket2', tone: 'food', home: 'modulo.php',
            items: [
                { label: 'Painel', icon: 'speedometer2', href: 'modulo.php', page: 'comida-painel' },
                { label: 'Beneficiários', icon: 'people', href: 'modulo.php', page: 'comida-beneficiarios' },
                { label: 'Nova inscrição', icon: 'person-plus', href: 'modulo.php?action=new', page: 'comida-inscricao' },
                { label: 'Consultar CPF', icon: 'person-bounding-box', href: 'consulta-documento.php', page: 'comida-consulta' },
                { label: 'Registrar entrega', icon: 'box-seam', href: 'consulta-documento.php', page: 'comida-entrega' },
                { group: 'Organização', items: [
                    { label: 'Competências', icon: 'calendar3', href: 'modulo.php', page: 'comida-competencias' },
                    { label: 'Polos', icon: 'geo-alt', href: 'modulo.php', page: 'comida-polos' },
                    { label: 'Documentos', icon: 'folder2-open', href: 'modulo.php', page: 'comida-documentos' },
                    { label: 'Histórico', icon: 'clock-history', href: 'modulo.php', page: 'comida-historico' },
                    { label: 'Relatórios', icon: 'bar-chart-line', href: 'modulo.php', page: 'comida-relatorios' }
                ]}
            ],
            mobile: ['comida-painel', 'comida-beneficiarios', 'comida-consulta', 'comida-entrega']
        },
        primeiroEmprego: {
            label: 'Primeiro Emprego', icon: 'briefcase', tone: 'employment', home: 'primeiro-emprego/index.php',
            items: [
                { label: 'Painel', icon: 'speedometer2', href: 'primeiro-emprego/index.php', page: 'primeiro-emprego-painel' },
                { label: 'Candidatos', icon: 'people', href: 'primeiro-emprego/candidatos.php', page: 'primeiro-emprego-candidatos' },
                { label: 'Novo candidato', icon: 'person-plus', href: 'primeiro-emprego/cadastro-candidato.php', page: 'primeiro-emprego-cadastro' },
                { label: 'Vagas', icon: 'briefcase', href: 'primeiro-emprego/vagas.php', page: 'primeiro-emprego-vagas' },
                { label: 'Empresas', icon: 'buildings', href: 'primeiro-emprego/empresas.php', page: 'primeiro-emprego-empresas' },
                { group: 'Acompanhamento', items: [
                    { label: 'Encaminhamentos', icon: 'send', href: 'primeiro-emprego/encaminhamentos.php', page: 'primeiro-emprego-encaminhamentos' },
                    { label: 'Entrevistas', icon: 'calendar2-check', href: 'primeiro-emprego/entrevistas.php', page: 'primeiro-emprego-entrevistas' },
                    { label: 'Contratações', icon: 'person-check', href: 'primeiro-emprego/contratacoes.php', page: 'primeiro-emprego-contratacoes' },
                    { label: 'Capacitações', icon: 'mortarboard', href: 'primeiro-emprego/capacitacoes.php', page: 'primeiro-emprego-capacitacoes' },
                    { label: 'Relatórios', icon: 'bar-chart-line', href: 'primeiro-emprego/relatorios.php', page: 'primeiro-emprego-relatorios' }
                ]}
            ],
            mobile: ['primeiro-emprego-painel', 'primeiro-emprego-candidatos', 'primeiro-emprego-cadastro', 'primeiro-emprego-vagas']
        },
        administracao: {
            label: 'Administração', icon: 'sliders', tone: 'admin', home: 'administracao.php',
            items: [
                { label: 'Painel administrativo', icon: 'speedometer2', href: 'administracao.php', page: 'admin-painel' },
                { label: 'Usuários', icon: 'people', href: 'usuarios.php', page: 'admin-usuarios' },
                { label: 'Módulos', icon: 'grid-1x2', href: 'portal.php#modulos', page: 'admin-modulos' },
                { label: 'Setores', icon: 'diagram-3', href: 'unidades.php', page: 'admin-setores' },
                { label: 'Perfis e permissões', icon: 'shield-lock', href: 'usuarios.php', page: 'admin-permissoes' },
                { label: 'Configurações', icon: 'gear', href: 'configuracoes.php', page: 'admin-configuracoes' },
                { label: 'Auditoria', icon: 'journal-text', href: 'usuarios.php', page: 'admin-auditoria' },
                { label: 'Relatórios gerais', icon: 'bar-chart-line', href: 'relatorios.php', page: 'admin-relatorios' },
                { label: 'Manual do sistema', icon: 'book', href: 'manual-sistema.php', page: 'admin-manual' }
            ],
            mobile: ['admin-painel', 'admin-usuarios', 'admin-modulos', 'admin-configuracoes']
        }
    };

    const escapeHTML = value => String(value || '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
    const flatItems = items => items.flatMap(item => item.items || [item]);
    const withPrefix = (url, prefix = '') => `${prefix}${url}`;
    const itemMarkup = (item, page, urlPrefix) => `<a class="module-nav-link${item.page === page ? ' active' : ''}" href="${escapeHTML(withPrefix(item.href, urlPrefix))}"${item.page === page ? ' aria-current="page"' : ''}><i class="bi bi-${escapeHTML(item.icon)}"></i><span>${escapeHTML(item.label)}</span></a>`;
    const menuMarkup = (menu, page, urlPrefix) => menu.items.map(item => item.group
        ? `<section class="module-nav-group"><h2>${escapeHTML(item.group)}</h2>${item.items.map(child => itemMarkup(child, page, urlPrefix)).join('')}</section>`
        : itemMarkup(item, page, urlPrefix)).join('');

    function moduleKey(value) {
        return ({ 'assistencia-social': 'assistenciaSocial', 'comida-mesa': 'comidaMesa', 'primeiro-emprego': 'primeiroEmprego' })[value] || value;
    }

    function render(target, options = {}) {
        const context = window.SIGAS_CONTEXT || {};
        const resolvedModule = moduleKey(options.module);
        const baseMenu = moduleMenus[resolvedModule];
        const override = options.menu || context.moduleMenu;
        const menu = Array.isArray(override) && baseMenu ? { ...baseMenu, home: override[0]?.href || baseMenu.home, items: override } : baseMenu;
        if (!menu || !target) return;
        const page = options.page || '';
        // Use '../' for pages within primeiro-emprego/. No URL here grants access.
        const urlPrefix = options.urlPrefix || '';
        const resolvedPortalUrl = options.portalUrl || (resolvedModule === 'primeiroEmprego' && Array.isArray(override) ? '../portal.php' : withPrefix(portalUrl, urlPrefix));
        const links = flatItems(menu.items);
        const mobile = (menu.mobile || []).map(key => links.find(item => item.page === key)).filter(Boolean);
        target.classList.add('module-shell', `module-shell--${menu.tone}`);
        target.innerHTML = `<aside class="module-sidebar" id="moduleSidebar" aria-label="Menu do módulo ${escapeHTML(menu.label)}">
            <div class="module-sidebar-head"><a class="module-brand" href="${escapeHTML(withPrefix(menu.home, urlPrefix))}"><i class="bi bi-${escapeHTML(menu.icon)}"></i><span><small>Módulo SIGAS</small><strong>${escapeHTML(menu.label)}</strong></span></a><button class="btn btn-light btn-icon d-lg-none" type="button" data-module-menu-close aria-label="Fechar menu"><i class="bi bi-x-lg"></i></button></div>
            <nav class="module-nav" aria-label="Navegação de ${escapeHTML(menu.label)}">${menuMarkup(menu, page, urlPrefix)}</nav>
            <div class="module-sidebar-footer"><a href="${escapeHTML(resolvedPortalUrl)}" class="module-switch-link"><i class="bi bi-grid"></i>Trocar módulo</a></div>
        </aside>
        <div class="module-main"><header class="module-topbar"><button class="btn btn-light btn-icon d-lg-none" type="button" data-module-menu-toggle aria-label="Abrir menu do módulo" aria-expanded="false" aria-controls="moduleSidebar"><i class="bi bi-list"></i></button><div><span class="module-topbar-kicker">SIGAS / Módulo</span><strong>${escapeHTML(menu.label)}</strong></div><a class="btn btn-outline-secondary btn-sm module-switch-button" href="${escapeHTML(resolvedPortalUrl)}"><i class="bi bi-grid"></i><span>Trocar módulo</span></a></header><main class="module-content" data-module-content></main></div>
        ${mobile.length ? `<nav class="module-mobile-nav" aria-label="Navegação móvel de ${escapeHTML(menu.label)}">${mobile.map(item => itemMarkup(item, page, urlPrefix)).join('')}<button type="button" data-module-menu-toggle aria-label="Mais opções do módulo" aria-expanded="false" aria-controls="moduleSidebar"><i class="bi bi-three-dots"></i><span>Mais</span></button></nav>` : ''}`;
        const content = target.querySelector('[data-module-content]');
        if (options.content instanceof Node) content.appendChild(options.content);
        bind(target);
    }

    /*
     * Auto-hidratação opt-in: defina data-module e data-page no body.
     * Páginas em subpasta podem definir data-module-url-prefix="../" e
     * data-portal-url="../portal.php". Isso não substitui autorização no PHP.
     */
    function hydrateDocument() {
        const body = document.body;
        const module = moduleKey(body?.dataset.module || '');
        if (!module || !moduleMenus[module]) return;
        const target = document.querySelector('[data-module-root]') || document.querySelector('#moduleRoot');
        if (!target) {
            hydrateExistingShell(module, body);
            return;
        }
        if (target.dataset.moduleHydrated === 'true') return;
        const content = document.createDocumentFragment();
        while (target.firstChild) content.appendChild(target.firstChild);
        target.dataset.moduleHydrated = 'true';
        render(target, {
            module,
            page: body.dataset.modulePage || body.dataset.page || window.SIGAS_CONTEXT?.page || '',
            urlPrefix: body.dataset.moduleUrlPrefix || '',
            portalUrl: body.dataset.portalUrl || '',
            content
        });
    }

    function hydrateExistingShell(module, body) {
        const sidebar = document.querySelector('#appSidebar');
        const topbar = document.querySelector('#appTopbar');
        const bottom = document.querySelector('#bottomNavigation');
        const menu = moduleMenus[module];
        if (!sidebar || !topbar || !menu || sidebar.dataset.moduleHydrated === 'true') return;
        const page = body.dataset.modulePage || body.dataset.page || window.SIGAS_CONTEXT?.page || '';
        const urlPrefix = body.dataset.moduleUrlPrefix || '';
        const portal = body.dataset.portalUrl || withPrefix(portalUrl, urlPrefix);
        const mobile = (menu.mobile || []).map(key => flatItems(menu.items).find(item => item.page === key)).filter(Boolean);
        sidebar.dataset.moduleHydrated = 'true';
        sidebar.setAttribute('aria-label', `Menu do módulo ${menu.label}`);
        sidebar.innerHTML = `<div class="module-sidebar-head"><a class="module-brand" href="${escapeHTML(withPrefix(menu.home, urlPrefix))}"><i class="bi bi-${escapeHTML(menu.icon)}"></i><span><small>Módulo SIGAS</small><strong>${escapeHTML(menu.label)}</strong></span></a><button class="btn btn-light btn-icon sidebar-close" type="button" data-sidebar-close aria-label="Fechar menu"><i class="bi bi-x-lg"></i></button></div><nav class="module-nav" aria-label="Navegação de ${escapeHTML(menu.label)}">${menuMarkup(menu, page, urlPrefix)}</nav><div class="module-sidebar-footer"><a href="${escapeHTML(portal)}" class="module-switch-link"><i class="bi bi-grid"></i>Trocar módulo</a></div>`;
        topbar.innerHTML = `<button class="btn btn-light btn-icon" type="button" data-sidebar-toggle aria-label="Abrir ou recolher menu" aria-expanded="false"><i class="bi bi-list"></i></button><div class="module-existing-title"><span class="module-topbar-kicker">SIGAS / Módulo</span><strong>${escapeHTML(menu.label)}</strong></div><a class="btn btn-outline-secondary btn-sm module-switch-button ms-auto" href="${escapeHTML(portal)}"><i class="bi bi-grid"></i><span>Trocar módulo</span></a>`;
        if (bottom) bottom.innerHTML = mobile.length ? `<nav class="module-mobile-nav module-mobile-nav--legacy" aria-label="Navegação móvel de ${escapeHTML(menu.label)}">${mobile.map(item => itemMarkup(item, page, urlPrefix)).join('')}<button type="button" data-sidebar-toggle aria-label="Mais opções do módulo" aria-expanded="false"><i class="bi bi-three-dots"></i><span>Mais</span></button></nav>` : '';
    }

    function bind(target) {
        const toggle = open => {
            target.classList.toggle('module-menu-open', open);
            target.querySelectorAll('[data-module-menu-toggle]').forEach(button => button.setAttribute('aria-expanded', String(open)));
            document.body.classList.toggle('module-menu-is-open', open);
        };
        target.addEventListener('click', event => {
            if (event.target.closest('[data-module-menu-toggle]')) toggle(true);
            if (event.target.closest('[data-module-menu-close]') || event.target === target) toggle(false);
            if (event.target.closest('.module-nav-link')) toggle(false);
        });
        window.addEventListener('popstate', () => toggle(false));
    }

    window.SIGAS_MODULE_UI = { moduleMenus, render, hydrateDocument, hydrateExistingShell };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', hydrateDocument, { once: true });
    else hydrateDocument();
}());
