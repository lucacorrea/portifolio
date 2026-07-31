'use strict';

(() => {
    const context = window.SIGAS_CONTEXT || {};
    const user = context.user || {};
    const setText = (selector, value) => { const element = document.querySelector(selector); if (element) element.textContent = value; };
    setText('[data-portal-initials]', user.initials || 'U');
    setText('[data-portal-name]', user.name || 'Usuário');
    setText('[data-portal-role]', user.jobTitle || 'Usuário');
    setText('[data-portal-sector]', user.sector ? `Módulos disponíveis para ${user.sector}.` : 'Os módulos disponíveis são definidos conforme o seu perfil e setor.');
    const logout = document.querySelector('[data-portal-logout]');
    if (logout) logout.value = context.csrf?.logout || '';

    // Apenas apresentação: a autorização definitiva de cada módulo deve ser validada no PHP.
    const availability = context.modules || {};
    const modules = [
        { key: 'assistenciaSocial', name: 'Assistência Social', description: 'Gestão de pessoas, famílias, solicitações, atendimentos, benefícios e unidades da rede socioassistencial.', icon: 'heart-pulse', href: 'dashboard.php', tone: 'assistance', status: 'Disponível' },
        { key: 'comidaMesa', name: 'Coari Comida na Mesa', description: 'Gestão de beneficiários, competências, entregas, documentos e acompanhamento do programa.', icon: 'basket2', href: 'modulo.php', tone: 'food', status: 'Disponível' },
        { key: 'primeiroEmprego', name: 'Primeiro Emprego', description: 'Gestão de candidatos, vagas, empresas, encaminhamentos, entrevistas e contratações.', icon: 'briefcase', href: 'primeiro-emprego/index.php', tone: 'jobs', status: 'Novo módulo' },
        { key: 'administracao', name: 'Administração', description: 'Gestão de usuários, permissões, configurações, auditoria e relatórios administrativos.', icon: 'gear', href: 'administracao.php', tone: 'admin', status: 'Disponível' }
    ];
    const root = document.querySelector('#moduleCards');
    if (!root) return;
    root.innerHTML = modules.map(module => {
        const allowed = availability[module.key]?.allowed !== false;
        const status = availability[module.key]?.maintenance ? 'Em manutenção' : allowed ? module.status : 'Acesso bloqueado';
        return `<article class="module-card ${module.tone} ${allowed ? '' : 'is-locked'}"><span class="module-card-icon"><i class="bi bi-${module.icon}"></i></span><h3>${module.name}</h3><p>${module.description}</p><div class="module-card-footer"><span class="status-badge ${allowed ? 'status-success' : 'status-neutral'}"><i class="bi bi-${allowed ? 'check-circle' : 'lock'}"></i>${status}</span>${allowed ? `<a class="btn btn-primary btn-sm" href="${module.href}">Entrar<i class="bi bi-arrow-right"></i></a>` : '<button class="btn btn-light btn-sm" type="button" disabled aria-disabled="true">Indisponível</button>'}</div></article>`;
    }).join('');
})();
