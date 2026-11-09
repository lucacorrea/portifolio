'use strict';

(() => {
    const context = window.SIGAS_CONTEXT || {};
    const user = context.user || {};
    const setText = (selector, value) => {
        const element = document.querySelector(selector);
        if (element) element.textContent = value;
    };
    const escapeHTML = value => String(value || '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

    const moduleHomes = {
        'kit-maternidade': 'kit-maternidade/index.php',
        'aluguel-social': 'aluguel-social/index.php',
        'beneficios-eventuais': 'beneficios-eventuais/index.php',
        'gestao-acessos': 'governanca-acessos/index.php',
    };

    const publicHome = (key, href) => moduleHomes[key] || href;

    setText('[data-portal-initials]', user.initials || 'U');
    setText('[data-portal-name]', user.name || 'Usuário');
    setText('[data-portal-role]', user.jobTitle || 'Usuário');
    setText('[data-portal-sector]', 'Módulos liberados conforme seu perfil, setor e permissões.');
    const logout = document.querySelector('[data-portal-logout]');
    if (logout) logout.value = context.csrf?.logout || '';

    const root = document.querySelector('#moduleCards');
    const environments = context.navigation || {};
    const availability = context.modules || {};
    if (!root) return;

    root.innerHTML = Object.entries(environments).map(([key, environment]) => {
        const allowed = availability[key]?.allowed !== false;
        const status = allowed ? 'Módulo independente' : 'Acesso indisponível';
        const home = publicHome(key, environment.home || 'portal.php');
        return `<article class="module-card ${escapeHTML(environment.theme)} ${allowed ? '' : 'is-locked'}">
            <span class="module-card-icon"><i class="bi bi-${escapeHTML(environment.icon)}"></i></span>
            <h3>${escapeHTML(environment.name)}</h3>
            <p>${escapeHTML(environment.description)}</p>
            <div class="module-card-footer"><span class="status-badge ${allowed ? 'status-success' : 'status-neutral'}"><i class="bi bi-${allowed ? 'check-circle' : 'lock'}"></i>${status}</span>${allowed ? `<a class="btn btn-primary btn-sm" href="${escapeHTML(home)}">Entrar<i class="bi bi-arrow-right"></i></a>` : '<button class="btn btn-light btn-sm" type="button" disabled>Indisponível</button>'}</div>
        </article>`;
    }).join('');
})();
