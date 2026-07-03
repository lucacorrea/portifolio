'use strict';

const SIGAS = {
    page: document.body.dataset.page || 'login',
    context: window.SIGAS_CONTEXT || {},
    qs(selector, context = document) { return context.querySelector(selector); },
    qsa(selector, context = document) { return [...context.querySelectorAll(selector)]; },
    escapeHTML(value = '') {
        return String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
    },
    showToast(message, type = 'success') {
        const container = this.qs('#toastContainer');
        if (!container) return;
        const icon = type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-octagon' : 'info-circle';
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body d-flex align-items-center gap-2"><i class="bi bi-${icon}"></i><span>${this.escapeHTML(message)}</span></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div>`;
        container.appendChild(toast);
        const instance = new bootstrap.Toast(toast, { delay: 3200 });
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
        instance.show();
    },
    sidebarMarkup() {
        const dashboardUrl = this.context.urls?.dashboard || 'dashboard.php';
        const sections = [
            ['Principal', [
                [dashboardUrl, 'speedometer2', 'Visão Geral', 'dashboard']
            ]],
            ['Atendimento Social', [
                ['consulta-documento.html', 'person-bounding-box', 'Consultar CPF / Documento', 'consulta'],
                ['cadastro-anexo.html', 'file-earmark-person', 'Novo Cadastro ANEXO', 'cadastro-anexo'],
                ['pessoas.html', 'people', 'Pessoas e Prontuários', 'pessoas'],
                ['solicitacoes.html', 'inboxes', 'Solicitações', 'solicitacoes'],
                ['atendimentos.html', 'clipboard2-pulse', 'Atendimentos', 'atendimentos']
            ]],
            ['Programas e Benefícios', [
                ['beneficios.html', 'gift', 'Programas e Benefícios', 'beneficios'],
                ['modulo.php', 'basket2', 'Comida na Mesa / Entregas', 'modulo', true]
            ]],
            ['Rede Socioassistencial', [
                ['unidades.html', 'buildings', 'Unidades', 'unidades']
            ]],
            ['Gestão', [
                ['relatorios.html', 'bar-chart', 'Relatórios', 'relatorios'],
                ['integracao-semth.html', 'database-lock', 'Integração SEMTH', 'integracao'],
                ['usuarios.html', 'person-gear', 'Usuários', 'usuarios'],
                ['configuracoes.html', 'sliders', 'Configurações', 'configuracoes'],
                ['manual-sistema.html', 'book', 'Manual do Sistema', 'manual']
            ]]
        ];
        const recordPages = ['registro'];
        return `
            <button class="btn btn-light btn-icon sidebar-close" type="button" data-sidebar-close aria-label="Fechar menu"><i class="bi bi-x-lg"></i></button>
            <a class="sidebar-brand" href="${this.escapeHTML(dashboardUrl)}" aria-label="SIGAS Coari - Página inicial">
                <img src="assets/img/brasao-placeholder.svg" alt="Brasão institucional ilustrativo">
                <div><strong>SIGAS COARI</strong><small>Gestão da Assistência Social</small></div>
            </a>
            <div class="sidebar-scroll">
                ${sections.map(([title, items]) => `<section class="nav-section"><h2 class="nav-section-title">${title}</h2><nav class="sidebar-nav" aria-label="${title}">${items.map(([href, icon, label, key, featured]) => {
                    const active = this.page === key || (recordPages.includes(this.page) && key === 'pessoas');
                    return `<a href="${this.escapeHTML(href)}" class="sidebar-link ${active ? 'active' : ''}" ${active ? 'aria-current="page"' : ''} title="${label}"><i class="bi bi-${icon}"></i><span>${label}</span>${featured ? '<b class="food-marker" aria-label="Ação prioritária"></b>' : ''}</a>`;
                }).join('')}</nav></section>`).join('')}
            </div>
            <div class="sidebar-footer"><strong>SIGAS Coari v1.1</strong><br>Estrutura demonstrativa organizada</div>`;
    },
    topbarMarkup() {
        const user = this.context.user || {};
        const urls = this.context.urls || {};
        const csrf = this.context.csrf || {};
        const initials = user.initials || 'U';
        const name = user.name || 'Usuário';
        const jobTitle = user.jobTitle || 'Usuário';
        const sector = user.sector || 'Sem setor';
        const logoutUrl = urls.logout || 'sair.php';
        const logoutToken = csrf.logout || '';

        return `
            <button class="btn btn-light btn-icon" type="button" data-sidebar-toggle aria-label="Abrir ou recolher menu" aria-expanded="false"><i class="bi bi-list"></i></button>
            <div class="topbar-search">
                <i class="bi bi-search"></i>
                <input class="form-control" id="globalSearch" type="search" placeholder="Pesquisar CPF, nome, protocolo ou solicitação" aria-label="Pesquisa global">
                <span class="search-shortcut">Ctrl K</span>
            </div>
            <button class="unit-chip" type="button" data-bs-toggle="tooltip" title="Unidade de trabalho atual"><i class="bi bi-building"></i><span>${this.escapeHTML(sector)}</span></button>
            <div class="dropdown">
                <button class="btn btn-light btn-icon topbar-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificações"><i class="bi bi-bell"></i><span class="notification-dot"></span></button>
                <div class="dropdown-menu dropdown-menu-end p-2 notification-menu">
                    <div class="px-2 py-2 d-flex align-items-center justify-content-between"><strong class="small">Notificações</strong><span class="status-badge status-info">3 novas</span></div>
                    <div class="notice-item"><span class="notice-icon warning"><i class="bi bi-file-earmark-excel"></i></span><div class="item-main"><strong>74 solicitações aguardam análise</strong><span>Fila de benefícios eventuais</span></div></div>
                    <div class="notice-item"><span class="notice-icon info"><i class="bi bi-calendar-event"></i></span><div class="item-main"><strong>Reunião de equipe às 15h</strong><span>Sala de coordenação</span></div></div>
                    <div class="notice-item"><span class="notice-icon success"><i class="bi bi-basket2"></i></span><div class="item-main"><strong>Meta mensal em 94,7%</strong><span>Programa Comida na Mesa</span></div></div>
                </div>
            </div>
            <div class="dropdown">
                <button class="user-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu do usuário">
                    <span class="avatar">${this.escapeHTML(initials)}</span><span class="user-meta"><strong>${this.escapeHTML(name)}</strong><span>${this.escapeHTML(jobTitle)}</span></span><i class="bi bi-chevron-down small text-secondary"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="perfil-usuario.html"><i class="bi bi-person me-2"></i>Meu perfil</a></li>
                    <li><a class="dropdown-item" href="configuracoes.html"><i class="bi bi-sliders me-2"></i>Preferências</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="${this.escapeHTML(logoutUrl)}">
                            <input type="hidden" name="_csrf" value="${this.escapeHTML(logoutToken)}">
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sair</button>
                        </form>
                    </li>
                </ul>
            </div>`;
    },
    bottomNavMarkup() {
        const dashboardUrl = this.context.urls?.dashboard || 'dashboard.php';
        const programPages = ['modulo', 'beneficios', 'cidadania', 'crianca', 'natalidade', 'funeral', 'outros'];
        return `<nav class="bottom-navigation" aria-label="Navegação móvel">
            <a href="${this.escapeHTML(dashboardUrl)}" class="${this.page === 'dashboard' ? 'active' : ''}"><i class="bi bi-house"></i><span>Início</span></a>
            <a href="pessoas.html" class="${['consulta','pessoas','registro'].includes(this.page) ? 'active' : ''}"><i class="bi bi-people"></i><span>Pessoas</span></a>
            <a href="cadastro-anexo.html" class="new-action ${this.page === 'cadastro-anexo' ? 'active' : ''}" aria-label="Novo Cadastro ANEXO"><i class="bi bi-plus-lg"></i><span>Novo</span></a>
            <a href="beneficios.html" class="${programPages.includes(this.page) ? 'active' : ''}"><i class="bi bi-gift"></i><span>Benefícios</span></a>
            <button type="button" data-sidebar-toggle><i class="bi bi-three-dots"></i><span>Mais</span></button>
        </nav>`;
    },
    hydrateShell() {
        const sidebar = this.qs('#appSidebar');
        const topbar = this.qs('#appTopbar');
        const bottom = this.qs('#bottomNavigation');
        if (sidebar) sidebar.innerHTML = this.sidebarMarkup();
        if (topbar) topbar.innerHTML = this.topbarMarkup();
        if (bottom) bottom.innerHTML = this.bottomNavMarkup();
    },
    initBootstrap() {
        this.qsa('[data-bs-toggle="tooltip"]').forEach(element => new bootstrap.Tooltip(element));
    },
    initLogin() {
        const form = this.qs('#loginForm');
        if (!form) return;
        const password = this.qs('#loginPassword');
        const toggle = this.qs('#passwordToggle');
        const feedback = this.qs('#loginFeedback');
        toggle?.addEventListener('click', () => {
            const hidden = password.type === 'password';
            password.type = hidden ? 'text' : 'password';
            toggle.innerHTML = `<i class="bi bi-eye${hidden ? '-slash' : ''}"></i>`;
            toggle.setAttribute('aria-label', hidden ? 'Ocultar senha' : 'Mostrar senha');
        });
        form.addEventListener('submit', event => {
            const identity = this.qs('#loginIdentity');
            const validIdentity = identity.value.trim().length >= 5;
            const validPassword = password.value.length >= 8;
            identity.classList.toggle('is-invalid', !validIdentity);
            password.classList.toggle('is-invalid', !validPassword);

            if (!validIdentity || !validPassword) {
                event.preventDefault();
                feedback.className = 'login-feedback show text-danger';
                feedback.textContent = 'Preencha uma identificação válida e uma senha com pelo menos 8 caracteres.';
                return;
            }

            const button = this.qs('button[type="submit"]', form);
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Verificando';
        });
    },
    initSidebar() {
        const shell = this.qs('.app-shell');
        if (!shell) return;
        const toggle = () => {
            if (window.innerWidth <= 1100) {
                shell.classList.toggle('sidebar-open');
            } else {
                shell.classList.toggle('sidebar-collapsed');
            }
            const expanded = shell.classList.contains('sidebar-open') || !shell.classList.contains('sidebar-collapsed');
            this.qsa('[data-sidebar-toggle]').forEach(button => button.setAttribute('aria-expanded', String(expanded)));
        };
        document.addEventListener('click', event => {
            if (event.target.closest('[data-sidebar-toggle]')) toggle();
            if (event.target.closest('[data-sidebar-close]')) shell.classList.remove('sidebar-open');
            if (shell.classList.contains('sidebar-open') && event.target === shell) shell.classList.remove('sidebar-open');
        });
        window.addEventListener('resize', () => { if (window.innerWidth > 1100) shell.classList.remove('sidebar-open'); });
    },
    initGlobalSearch() {
        const input = this.qs('#globalSearch');
        if (!input) return;
        document.addEventListener('keydown', event => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                input.focus();
            }
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                const term = input.value.trim();
                if (!term) {
                    this.showToast('Digite um termo para pesquisar.', 'danger');
                    return;
                }
                window.location.href = `pessoas.html?q=${encodeURIComponent(term)}`;
            }
        });
    },
    initTabs() {
        this.qsa('[data-tab-target]').forEach(button => {
            button.addEventListener('click', () => {
                const group = button.closest('[data-tabs-group]') || document;
                this.qsa('[data-tab-target]', group).forEach(item => item.classList.remove('active'));
                button.classList.add('active');
                const target = button.dataset.tabTarget;
                this.qsa('[data-tab-panel]', group).forEach(panel => panel.hidden = panel.dataset.tabPanel !== target);
            });
        });
    },
    initFilters() {
        const form = this.qs('#filterForm');
        if (!form || form.hasAttribute('data-server-filter')) {
            return;
        }

        const advanced = this.qs('#advancedFilters');
        const table = this.qs('#beneficiaryTable');
        const loading = this.qs('#tableLoading');
        const empty = this.qs('#emptyState');
        const error = this.qs('#errorState');
        this.qsa('[data-toggle-advanced]').forEach(button => button.addEventListener('click', () => {
            advanced?.classList.toggle('show');
            const expanded = advanced?.classList.contains('show') || false;
            button.setAttribute('aria-expanded', String(expanded));
        }));
        const runFilter = () => {
            table?.classList.add('d-none');
            empty?.classList.remove('show');
            error?.classList.remove('show');
            loading?.classList.add('show');
            window.setTimeout(() => {
                loading?.classList.remove('show');
                const query = (this.qs('[name="search"]', form)?.value || '').trim().toLowerCase();
                if (query === 'erro') {
                    table?.classList.add('d-none');
                    error?.classList.add('show');
                    this.showToast('Não foi possível concluir a pesquisa demonstrativa.', 'danger');
                    return;
                }
                const rows = this.qsa('tbody tr', table);
                let visible = 0;
                rows.forEach(row => {
                    const match = !query || row.textContent.toLowerCase().includes(query);
                    row.classList.toggle('d-none', !match);
                    if (match) visible += 1;
                });
                table?.classList.remove('d-none');
                empty?.classList.toggle('show', visible === 0);
                this.showToast(visible ? `${visible} registro(s) encontrado(s).` : 'Nenhum resultado para os filtros informados.', visible ? 'success' : 'danger');
            }, 520);
        };
        form.addEventListener('submit', event => { event.preventDefault(); runFilter(); });
        this.qsa('[data-clear-filters]').forEach(button => button.addEventListener('click', () => {
            form.reset();
            this.qsa('tbody tr', table).forEach(row => row.classList.remove('d-none'));
            empty?.classList.remove('show');
            error?.classList.remove('show');
            table?.classList.remove('d-none');
            this.showToast('Filtros removidos.');
        }));
        const mobileForm = this.qs('#mobileFilterForm');
        mobileForm?.addEventListener('submit', event => {
            event.preventDefault();
            this.showToast('Filtros móveis aplicados.');
            bootstrap.Offcanvas.getOrCreateInstance(this.qs('#filterOffcanvas')).hide();
        });
    },
    initCharts() {
        if (typeof Chart === 'undefined') return;
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.color = '#7a867f';
        const attendance = this.qs('#attendanceChart');
        if (attendance) new Chart(attendance, {
            type: 'bar',
            data: { labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'], datasets: [{ label: 'Atendimentos', data: [118, 142, 127, 165, 151, 96, 74], backgroundColor: '#176b3a', borderRadius: 7, borderSkipped: false, maxBarThickness: 28 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { displayColors: false } }, scales: { x: { grid: { display: false }, border: { display: false } }, y: { beginAtZero: true, ticks: { stepSize: 50 }, grid: { color: '#eef1ef' }, border: { display: false } } } }
        });
        const status = this.qs('#statusChart');
        if (status) new Chart(status, {
            type: 'doughnut',
            data: { labels: ['Aprovadas', 'Em análise', 'Pendentes', 'Indeferidas'], datasets: [{ data: [46, 29, 17, 8], backgroundColor: ['#16834a', '#2375a7', '#d88a08', '#c93c3c'], borderWidth: 0, hoverOffset: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, padding: 15, font: { size: 10 } } } } }
        });
    },
    initGenericCharts() {
        if (typeof Chart === 'undefined') return;
        const parse = (value, fallback = []) => {
            try { return JSON.parse(value || '[]'); } catch { return fallback; }
        };
        this.qsa('canvas[data-chart]').forEach(canvas => {
            if (canvas.dataset.chartReady === 'true') return;
            const type = canvas.dataset.chart;
            const labels = parse(canvas.dataset.chartLabels);
            const values = parse(canvas.dataset.chartValues);
            const values2 = parse(canvas.dataset.chartValues2);
            const palettes = {
                primary: ['#176b3a', '#2375a7', '#d88a08', '#c93c3c', '#60746a'],
                soft: ['rgba(23,107,58,.14)', 'rgba(35,117,167,.12)']
            };
            const datasets = [{
                label: canvas.dataset.chartLabel || 'Total',
                data: values,
                backgroundColor: type === 'doughnut' ? palettes.primary : (type === 'line' ? palettes.soft[0] : '#176b3a'),
                borderColor: '#176b3a',
                borderWidth: type === 'line' ? 2 : 0,
                borderRadius: type === 'bar' ? 7 : 0,
                borderSkipped: false,
                fill: type === 'line',
                tension: .35,
                pointRadius: type === 'line' ? 3 : 0,
                maxBarThickness: 30
            }];
            if (values2.length) datasets.push({
                label: canvas.dataset.chartLabel2 || 'Comparativo',
                data: values2,
                backgroundColor: type === 'line' ? palettes.soft[1] : '#d99218',
                borderColor: '#d99218',
                borderWidth: type === 'line' ? 2 : 0,
                borderRadius: type === 'bar' ? 7 : 0,
                borderSkipped: false,
                fill: type === 'line',
                tension: .35,
                pointRadius: type === 'line' ? 3 : 0,
                maxBarThickness: 30
            });
            new Chart(canvas, {
                type,
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: type === 'doughnut' ? '68%' : undefined,
                    plugins: {
                        legend: { display: type === 'doughnut' || datasets.length > 1, position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, padding: 14, font: { size: 10 } } },
                        tooltip: { displayColors: datasets.length > 1 || type === 'doughnut' }
                    },
                    scales: type === 'doughnut' ? undefined : {
                        x: { grid: { display: false }, border: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#eef1ef' }, border: { display: false } }
                    }
                }
            });
            canvas.dataset.chartReady = 'true';
        });
    },
    initFormStepper() {
        const modal = this.qs('#editRegistrationModal');
        if (!modal) return;
        let current = 0;
        const panels = this.qsa('.form-panel', modal);
        const steps = this.qsa('.form-step', modal);
        const prev = this.qs('[data-step-prev]', modal);
        const next = this.qs('[data-step-next]', modal);
        const finish = this.qs('[data-step-finish]', modal);
        const update = () => {
            panels.forEach((panel, index) => panel.classList.toggle('active', index === current));
            steps.forEach((step, index) => {
                step.classList.toggle('active', index === current);
                step.classList.toggle('done', index < current);
                step.setAttribute('aria-current', index === current ? 'step' : 'false');
            });
            prev.disabled = current === 0;
            next.classList.toggle('d-none', current === panels.length - 1);
            finish.classList.toggle('d-none', current !== panels.length - 1);
            this.qs('#stepProgressText', modal).textContent = `Etapa ${current + 1} de ${panels.length}`;
        };
        const validatePanel = () => {
            const required = this.qsa('[required]', panels[current]);
            let valid = true;
            required.forEach(field => {
                const ok = field.value.trim() !== '';
                field.classList.toggle('is-invalid', !ok);
                valid = valid && ok;
            });
            if (!valid) this.showToast('Preencha os campos obrigatórios da etapa atual.', 'danger');
            return valid;
        };
        prev.addEventListener('click', () => { if (current > 0) { current -= 1; update(); } });
        next.addEventListener('click', () => { if (validatePanel() && current < panels.length - 1) { current += 1; update(); } });
        finish.addEventListener('click', () => {
            if (!validatePanel()) return;
            bootstrap.Modal.getOrCreateInstance(modal).hide();
            this.showToast('Cadastro concluído com sucesso.');
        });
        this.qsa('[data-save-draft]', modal).forEach(button => button.addEventListener('click', () => this.showToast('Rascunho salvo localmente para demonstração.')));
        modal.addEventListener('hidden.bs.modal', () => { current = 0; update(); });
        update();
    },
    initMembers() {
        const container = this.qs('#familyMembersEditor');
        const add = this.qs('[data-add-member]');
        if (!container || !add) return;
        const renumber = () => this.qsa('.member-editor', container).forEach((member, index) => {
            const title = this.qs('.member-number', member);
            if (title) title.textContent = `Integrante ${index + 1}`;
        });
        add.addEventListener('click', () => {
            const member = document.createElement('div');
            member.className = 'member-editor';
            member.innerHTML = `<div class="member-editor-head"><strong class="member-number">Integrante</strong><button type="button" class="btn btn-sm btn-light text-danger" data-remove-member><i class="bi bi-trash"></i>Remover</button></div><div class="form-grid"><div class="field-6"><label class="form-label">Nome</label><input class="form-control" type="text" placeholder="Nome completo"></div><div class="field-3"><label class="form-label">Parentesco</label><select class="form-select"><option>Filho(a)</option><option>Cônjuge</option><option>Pai/Mãe</option><option>Outro</option></select></div><div class="field-3"><label class="form-label">Data de nascimento</label><input class="form-control" type="date"></div><div class="field-3"><label class="form-label">CPF</label><input class="form-control" type="text" placeholder="000.000.000-00"></div><div class="field-3"><label class="form-label">NIS</label><input class="form-control" type="text"></div><div class="field-3"><label class="form-label">Renda</label><input class="form-control" type="text" placeholder="R$ 0,00"></div><div class="field-3"><label class="form-label">Ocupação</label><input class="form-control" type="text"></div></div>`;
            container.appendChild(member);
            renumber();
        });
        container.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-member]');
            if (!button) return;
            button.closest('.member-editor')?.remove();
            renumber();
            this.showToast('Integrante removido do formulário.', 'danger');
        });
    },

    initDocumentScanner() {
        const root = this.qs('[data-document-scanner]');
        if (!root || !window.SIGASIntegration) return;

        const video = this.qs('#scannerVideo', root);
        const preview = this.qs('#scannerPreview', root);
        const placeholder = this.qs('#cameraPlaceholder', root);
        const processing = this.qs('#scanProcessing', root);
        const processingTitle = this.qs('#scanProcessingTitle', root);
        const processingText = this.qs('#scanProcessingText', root);
        const progressBar = this.qs('#scanProgressBar', root);
        const openCameraButton = this.qs('#openCameraButton', root);
        const captureButton = this.qs('#captureDocumentButton', root);
        const imageInput = this.qs('#documentImageInput', root);
        const cpfForm = this.qs('#manualCpfForm', root);
        const cpfInput = this.qs('#manualCpf', root);
        const scenarioSelect = this.qs('#demoScenario', root);
        const emptyResult = this.qs('#scanResultEmpty', root);
        const resultCard = this.qs('#scanResultCard', root);
        const historyList = this.qs('#scanHistoryList', root);
        const clearHistoryButton = this.qs('#clearScanHistory', root);
        const sourceNote = this.qs('#resultSourceText', root);
        let stream = null;
        let history = [];
        let processingTimer = null;

        const statusClass = status => status === 'success' ? 'status-success' : status === 'warning' ? 'status-warning' : status === 'danger' ? 'status-danger' : status === 'info' ? 'status-info' : 'status-neutral';
        const stateIcon = status => status === 'success' ? 'check-circle-fill' : status === 'warning' ? 'exclamation-circle-fill' : status === 'danger' ? 'x-circle-fill' : status === 'info' ? 'info-circle-fill' : 'dash-circle';
        const setStep = active => {
            this.qsa('[data-scan-step]', root).forEach(step => {
                const number = Number(step.dataset.scanStep);
                step.classList.toggle('active', number === active);
                step.classList.toggle('done', number < active);
                step.setAttribute('aria-current', number === active ? 'step' : 'false');
            });
        };
        const stopCamera = () => {
            if (stream) stream.getTracks().forEach(track => track.stop());
            stream = null;
            if (video) video.srcObject = null;
            if (captureButton) captureButton.hidden = true;
            if (openCameraButton) openCameraButton.innerHTML = '<i class="bi bi-camera"></i>Abrir câmera';
        };
        const setCameraVisual = mode => {
            if (placeholder) placeholder.hidden = mode !== 'placeholder';
            if (video) video.hidden = mode !== 'video';
            if (preview) preview.hidden = mode !== 'preview';
        };
        const renderHistory = () => {
            if (!historyList) return;
            if (!history.length) {
                historyList.innerHTML = '<div class="scan-history-empty"><i class="bi bi-clock-history"></i><span>Nenhuma consulta realizada nesta sessão.</span></div>';
                return;
            }
            historyList.innerHTML = history.map(item => `<button class="scan-history-item" type="button" data-history-scenario="${item.scenario}"><span class="mini-avatar">${item.initials}</span><span><strong>${this.escapeHTML(item.name)}</strong><small>${this.escapeHTML(item.cpf)} · ${item.time}</small></span><i class="bi bi-chevron-right"></i></button>`).join('');
        };
        const verificationItems = result => {
            const sigasType = result.sigas.found ? 'success' : 'neutral';
            const semthType = result.semth.found ? 'info' : 'neutral';
            const decisionMap = {
                'open-existing': ['Duplicidade', 'Cadastro bloqueado', 'Abra o registro existente no SIGAS.', 'danger', 'intersect'],
                'create-reference': ['Tratamento', 'Vincular referência', 'Criar registro local mínimo apontando para o SEMTH.', 'warning', 'link-45deg'],
                'open-linked': ['Integração', 'Referência vinculada', 'As duas bases permanecem independentes.', 'success', 'link'],
                'review-conflict': ['Consistência', 'Revisão obrigatória', 'Há divergência que precisa de análise humana.', 'danger', 'exclamation-diamond'],
                'create-new': ['Duplicidade', 'Não identificada', 'O novo cadastro pode prosseguir no SIGAS.', 'success', 'person-plus']
            };
            return [
                ['Base SIGAS', result.sigas.found ? 'Localizada' : 'Não localizada', result.sigas.found ? `${result.sigas.id} · ${result.sigas.status}` : 'Nenhum registro local', sigasType, result.sigas.found ? 'database-check' : 'database'],
                ['Base SEMTH', result.semth.found ? 'Localizada — somente leitura' : 'Não localizada', result.semth.found ? `${result.semth.id} · ${result.semth.status}` : 'Consulta concluída sem correspondência', semthType, result.semth.found ? 'database-lock' : 'database'],
                decisionMap[result.decision]
            ];
        };
        const configurePrimaryAction = result => {
            const openLink = this.qs('#openPersonLink', root);
            if (!openLink) return;
            openLink.classList.remove('disabled');
            openLink.removeAttribute('aria-disabled');
            openLink.tabIndex = 0;
            openLink.removeAttribute('data-demo-action');
            if (['open-existing', 'open-linked'].includes(result.decision)) {
                openLink.href = 'registro.html';
                openLink.innerHTML = '<i class="bi bi-person-lines-fill"></i>Abrir cadastro SIGAS';
            } else if (result.decision === 'create-reference') {
                openLink.href = 'cadastro-anexo.html';
                openLink.innerHTML = '<i class="bi bi-link-45deg"></i>Vincular ao SIGAS';
            } else if (result.decision === 'review-conflict') {
                openLink.href = 'integracao-semth.html#divergencias';
                openLink.innerHTML = '<i class="bi bi-exclamation-diamond"></i>Revisar divergência';
            } else {
                openLink.href = 'cadastro-anexo.html';
                openLink.innerHTML = '<i class="bi bi-person-plus"></i>Iniciar cadastro';
            }
        };
        const renderResult = result => {
            const type = result.severity;
            this.qs('#resultAvatar', root).textContent = result.initials;
            this.qs('#resultOverline', root).textContent = 'Comparação entre bases concluída';
            this.qs('#resultName', root).textContent = result.name;
            this.qs('#resultDocument', root).textContent = `CPF: ${window.SIGASIntegration.maskCpf(result.cpf)}`;
            const main = this.qs('#resultMainStatus', root);
            main.className = `status-badge ${statusClass(type)}`;
            main.innerHTML = `<i class="bi bi-${stateIcon(type)}"></i>${type === 'success' ? 'Liberada' : type === 'info' ? 'Vinculada' : type === 'warning' ? 'Atenção' : 'Bloqueada'}`;
            const alert = this.qs('#resultAlert', root);
            alert.className = `result-alert ${type}`;
            this.qs('#resultAlertTitle', root).textContent = result.title;
            this.qs('#resultAlertText', root).textContent = result.message;
            this.qs('#verificationGrid', root).innerHTML = verificationItems(result).map(([label, value, detail, itemType, icon]) => `<div class="verification-item ${itemType}"><span class="verification-icon"><i class="bi bi-${icon}"></i></span><div><small>${label}</small><strong>${value}</strong><p>${detail}</p></div><i class="bi bi-${stateIcon(itemType)} verification-state"></i></div>`).join('');
            if (sourceNote) sourceNote.textContent = 'SIGAS: leitura e gravação local. SEMTH: consulta externa com credencial SELECT, sem atualização cruzada.';
            configurePrimaryAction(result);
            emptyResult.hidden = true;
            resultCard.hidden = false;
            history.unshift({ scenario: result.key, initials: result.initials, name: result.name, cpf: window.SIGASIntegration.maskCpf(result.cpf), time: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) });
            history = history.slice(0, 4);
            renderHistory();
            resultCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };
        const processDocument = (enteredCpf = '') => {
            window.clearTimeout(processingTimer);
            stopCamera();
            processing.hidden = false;
            progressBar.style.width = '12%';
            setStep(2);
            processingTitle.textContent = 'Lendo documento';
            processingText.textContent = 'Identificando nome e CPF...';
            window.setTimeout(() => { progressBar.style.width = '52%'; processingText.textContent = 'Validando os dados capturados...'; }, 360);
            window.setTimeout(() => { setStep(3); progressBar.style.width = '78%'; processingTitle.textContent = 'Comparando bases'; processingText.textContent = 'Consultando SIGAS e SEMTH em modo leitura...'; }, 760);
            processingTimer = window.setTimeout(async () => {
                const forced = scenarioSelect?.value || '';
                const result = await window.SIGASIntegration.lookupByCpf(enteredCpf, forced);
                progressBar.style.width = '100%';
                processing.hidden = true;
                renderResult(result);
                this.showToast('Comparação concluída sem alterar o SEMTH.');
            }, 1180);
        };
        const openCamera = async () => {
            setStep(1);
            if (navigator.mediaDevices?.getUserMedia && window.isSecureContext) {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
                    video.srcObject = stream;
                    await video.play();
                    setCameraVisual('video');
                    captureButton.hidden = false;
                    openCameraButton.innerHTML = '<i class="bi bi-arrow-repeat"></i>Reabrir câmera';
                    this.showToast('Câmera aberta. Enquadre o documento.');
                    return;
                } catch (error) {
                    this.showToast('A câmera integrada não pôde ser aberta. Use a captura do dispositivo.', 'danger');
                }
            }
            imageInput.click();
        };
        const resetScanner = () => {
            window.clearTimeout(processingTimer);
            stopCamera();
            setCameraVisual('placeholder');
            processing.hidden = true;
            progressBar.style.width = '0';
            emptyResult.hidden = false;
            resultCard.hidden = true;
            cpfInput.value = '';
            cpfInput.classList.remove('is-invalid');
            imageInput.value = '';
            setStep(1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        openCameraButton?.addEventListener('click', openCamera);
        captureButton?.addEventListener('click', () => {
            if (!stream || !video.videoWidth) return;
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            preview.src = canvas.toDataURL('image/jpeg', .88);
            setCameraVisual('preview');
            processDocument('');
        });
        imageInput?.addEventListener('change', () => {
            const file = imageInput.files?.[0];
            if (!file) return;
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                preview.src = reader.result;
                setCameraVisual('preview');
                processDocument('');
            });
            reader.readAsDataURL(file);
        });
        cpfInput?.addEventListener('input', () => {
            cpfInput.value = window.SIGASIntegration.formatCpf(cpfInput.value);
            cpfInput.classList.remove('is-invalid');
        });
        cpfForm?.addEventListener('submit', event => {
            event.preventDefault();
            const digits = window.SIGASIntegration.digits(cpfInput.value);
            const valid = digits.length === 11;
            cpfInput.classList.toggle('is-invalid', !valid);
            if (!valid) {
                this.showToast('Informe os 11 números do CPF.', 'danger');
                cpfInput.focus();
                return;
            }
            setCameraVisual('placeholder');
            processDocument(digits);
        });
        this.qsa('[data-scan-reset]', root).forEach(button => button.addEventListener('click', resetScanner));
        clearHistoryButton?.addEventListener('click', () => {
            history = [];
            renderHistory();
            this.showToast('Histórico desta sessão removido.');
        });
        historyList?.addEventListener('click', async event => {
            const item = event.target.closest('[data-history-scenario]');
            if (!item) return;
            if (scenarioSelect) scenarioSelect.value = item.dataset.historyScenario;
            renderResult(window.SIGASIntegration.getScenario(item.dataset.historyScenario));
        });
        window.addEventListener('pagehide', stopCamera);
        renderHistory();
        setStep(1);
    },

    initIntegrationStatus() {
        const now = new Date().toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
        this.qsa('[data-integration-time]').forEach(element => { element.textContent = now; });
        this.qsa('[data-integration-test]').forEach(button => button.addEventListener('click', () => {
            button.disabled = true;
            const original = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Testando leitura';
            window.setTimeout(() => {
                button.disabled = false;
                button.innerHTML = original;
                const status = this.qs('[data-integration-health]');
                if (status) status.innerHTML = '<i class="bi bi-check-circle-fill"></i> Conectado em modo somente leitura';
                this.showToast('Consulta SELECT validada. Nenhuma operação de escrita foi executada.');
            }, 900);
        }));
    },
    initSourceOwnership() {
        const table = this.qs('#beneficiaryTable');
        if (!table) return;
        const header = this.qs('thead tr', table);
        if (header && !this.qs('[data-source-column]', header)) {
            const th = document.createElement('th');
            th.dataset.sourceColumn = 'true';
            th.textContent = 'Origem';
            const situationHeader = [...header.children].find(cell => cell.textContent.trim() === 'Situação');
            header.insertBefore(th, situationHeader || header.lastElementChild);
        }
        const sources = [
            { type: 'local', label: 'SIGAS', detail: 'Cadastro próprio' },
            { type: 'linked', label: 'Vinculado', detail: 'SIGAS + referência SEMTH' },
            { type: 'local', label: 'SIGAS', detail: 'Cadastro próprio' },
            { type: 'legacy', label: 'SEMTH', detail: 'Somente leitura' },
            { type: 'local', label: 'SIGAS', detail: 'Cadastro próprio' },
            { type: 'linked', label: 'Vinculado', detail: 'SIGAS + referência SEMTH' }
        ];
        this.qsa('tbody tr', table).forEach((row, index) => {
            const source = sources[index % sources.length];
            row.dataset.recordSource = source.type;
            if (!this.qs('[data-source-cell]', row)) {
                const td = document.createElement('td');
                td.dataset.sourceCell = 'true';
                const badgeClass = source.type === 'legacy' ? 'source-legacy' : source.type === 'linked' ? 'source-linked' : 'source-local';
                td.innerHTML = `<span class="source-badge ${badgeClass}"><i class="bi bi-${source.type === 'legacy' ? 'database-lock' : source.type === 'linked' ? 'link-45deg' : 'database-check'}"></i>${source.label}</span><small class="source-detail">${source.detail}</small>`;
                const cells = [...row.children];
                const situationCell = cells.find(cell => /Regular|revisão|Pendente|Incompleto/i.test(cell.textContent));
                row.insertBefore(td, situationCell || row.lastElementChild);
            }
            if (source.type === 'legacy') {
                row.classList.add('legacy-readonly-row');
                const checkbox = this.qs('input[type="checkbox"]', row);
                if (checkbox) { checkbox.disabled = true; checkbox.setAttribute('aria-label', 'Registro SEMTH somente leitura'); }
                this.qsa('button', row).forEach(button => { button.disabled = true; button.title = 'O SIGAS não altera registros do SEMTH'; });
                const view = this.qs('a[href="registro.html"]', row);
                if (view) { view.href = 'integracao-semth.html#consulta'; view.title = 'Visualizar referência SEMTH'; }
            }
        });
        this.qsa('.mobile-record-card').forEach((card, index) => {
            const source = sources[index % sources.length];
            card.dataset.recordSource = source.type;
            const head = this.qs('.mobile-record-head', card);
            if (head && !this.qs('.source-badge', head)) {
                const badge = document.createElement('span');
                badge.className = `source-badge ${source.type === 'legacy' ? 'source-legacy' : source.type === 'linked' ? 'source-linked' : 'source-local'}`;
                badge.innerHTML = `<i class="bi bi-${source.type === 'legacy' ? 'database-lock' : source.type === 'linked' ? 'link-45deg' : 'database-check'}"></i>${source.label}`;
                head.appendChild(badge);
            }
            if (source.type === 'legacy') {
                card.classList.add('legacy-readonly-row');
                this.qsa('button', card).forEach(button => { button.disabled = true; });
                const view = this.qs('a[href="registro.html"]', card);
                if (view) { view.href = 'integracao-semth.html#consulta'; view.innerHTML = '<i class="bi bi-eye"></i>Consultar'; }
            }
        });
    },
    initLegacyValidation() {
        const root = this.qs('[data-identity-validation]');
        if (!root || !window.SIGASIntegration) return;
        const cpf = this.qs('#newPersonCpf', root);
        const scenario = this.qs('#newPersonScenario', root);
        const checkButton = this.qs('#checkIdentityButton', root);
        const resultBox = this.qs('#identityValidationResult', root);
        const resultTitle = this.qs('#identityValidationTitle', root);
        const resultText = this.qs('#identityValidationText', root);
        const sourceGrid = this.qs('#identitySourceGrid', root);
        const details = this.qs('#newPersonDetails', root);
        const submit = this.qs('#newPersonSubmit', root);
        let lastResult = null;

        const reset = () => {
            lastResult = null;
            resultBox.hidden = true;
            details.hidden = true;
            submit.disabled = true;
            submit.dataset.mode = '';
            submit.innerHTML = '<i class="bi bi-shield-check"></i>Validar antes de continuar';
        };
        const render = result => {
            lastResult = result;
            resultBox.hidden = false;
            resultBox.className = `identity-validation-result ${result.severity}`;
            resultTitle.textContent = result.title;
            resultText.textContent = result.message;
            sourceGrid.innerHTML = `
                <div class="identity-source-card ${result.sigas.found ? 'found' : ''}"><span><i class="bi bi-database-check"></i>Base SIGAS</span><strong>${result.sigas.found ? result.sigas.id : 'Não localizado'}</strong><small>${result.sigas.found ? result.sigas.status : 'Nenhum registro próprio'}</small></div>
                <div class="identity-source-card legacy ${result.semth.found ? 'found' : ''}"><span><i class="bi bi-database-lock"></i>Base SEMTH</span><strong>${result.semth.found ? result.semth.id : 'Não localizado'}</strong><small>${result.semth.found ? 'Consulta somente leitura' : 'Nenhuma correspondência'}</small></div>`;
            const allowed = ['create-new', 'create-reference'].includes(result.decision);
            details.hidden = !allowed;
            submit.disabled = !allowed;
            submit.dataset.mode = result.decision;
            if (result.decision === 'create-new') submit.innerHTML = '<i class="bi bi-person-plus"></i>Criar cadastro no SIGAS';
            else if (result.decision === 'create-reference') submit.innerHTML = '<i class="bi bi-link-45deg"></i>Criar referência no SIGAS';
            else if (['open-existing', 'open-linked'].includes(result.decision)) submit.innerHTML = '<i class="bi bi-person-lines-fill"></i>Abrir cadastro existente';
            else submit.innerHTML = '<i class="bi bi-exclamation-diamond"></i>Revisar divergência';
        };
        cpf?.addEventListener('input', () => {
            cpf.value = window.SIGASIntegration.formatCpf(cpf.value);
            cpf.classList.remove('is-invalid');
            reset();
        });
        checkButton?.addEventListener('click', async () => {
            const digits = window.SIGASIntegration.digits(cpf.value);
            if (digits.length !== 11) {
                cpf.classList.add('is-invalid');
                this.showToast('Informe um CPF com 11 números.', 'danger');
                return;
            }
            cpf.classList.remove('is-invalid');
            checkButton.disabled = true;
            checkButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Comparando';
            const result = await window.SIGASIntegration.lookupByCpf(digits, scenario?.value || '');
            checkButton.disabled = false;
            checkButton.innerHTML = '<i class="bi bi-search"></i>Comparar bases';
            render(result);
        });
        submit?.addEventListener('click', () => {
            if (!lastResult) return;
            if (submit.dataset.mode === 'create-reference') {
                this.showToast('Referência local criada. O cadastro SEMTH permaneceu inalterado.');
            } else {
                this.showToast('Cadastro demonstrativo criado somente na base SIGAS.');
            }
            const modal = root.closest('.modal');
            if (modal) bootstrap.Modal.getOrCreateInstance(modal).hide();
        });
        root.closest('.modal')?.addEventListener('hidden.bs.modal', () => {
            root.querySelector('form')?.reset();
            reset();
        });
        reset();
    },
    initRecordOwnership() {
        const ownership = this.qs('[data-record-ownership]');
        if (!ownership) return;
        const legacyButtons = this.qsa('[data-legacy-readonly]');
        legacyButtons.forEach(button => button.addEventListener('click', event => {
            event.preventDefault();
            this.showToast('Dados provenientes do SEMTH são somente leitura no SIGAS.', 'danger');
        }));
    },

    initIntegrationExplorer() {
        const root = this.qs('[data-integration-explorer]');
        if (!root || !window.SIGASIntegration) return;
        const form = this.qs('#integrationLookupForm', root);
        const cpf = this.qs('#integrationLookupCpf', root);
        const scenario = this.qs('#integrationLookupScenario', root);
        const empty = this.qs('#integrationQueryEmpty', root);
        const resultBox = this.qs('#integrationQueryResult', root);
        const sourceGrid = this.qs('#integrationQuerySources', root);
        const status = this.qs('#integrationQueryStatus', root);
        const alert = this.qs('#integrationQueryAlert', root);
        const stateIcon = type => type === 'success' ? 'check-circle-fill' : type === 'warning' ? 'exclamation-circle-fill' : type === 'danger' ? 'x-circle-fill' : 'info-circle-fill';
        const statusClass = type => type === 'success' ? 'status-success' : type === 'warning' ? 'status-warning' : type === 'danger' ? 'status-danger' : 'status-info';

        cpf?.addEventListener('input', () => {
            cpf.value = window.SIGASIntegration.formatCpf(cpf.value);
            cpf.classList.remove('is-invalid');
        });
        form?.addEventListener('submit', async event => {
            event.preventDefault();
            const digits = window.SIGASIntegration.digits(cpf.value);
            if (digits.length !== 11) {
                cpf.classList.add('is-invalid');
                this.showToast('Informe um CPF com 11 números.', 'danger');
                return;
            }
            const button = this.qs('button[type="submit"]', form);
            button.disabled = true;
            const original = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Consultando';
            const data = await window.SIGASIntegration.lookupByCpf(digits, scenario?.value || '');
            button.disabled = false;
            button.innerHTML = original;
            this.qs('#integrationQueryAvatar', root).textContent = data.initials;
            this.qs('#integrationQueryName', root).textContent = data.name;
            this.qs('#integrationQueryCpf', root).textContent = `CPF: ${window.SIGASIntegration.maskCpf(data.cpf)}`;
            status.className = `status-badge ${statusClass(data.severity)}`;
            status.innerHTML = `<i class="bi bi-${stateIcon(data.severity)}"></i>${data.severity === 'success' ? 'Liberada' : data.severity === 'info' ? 'Vinculada' : data.severity === 'warning' ? 'Atenção' : 'Bloqueada'}`;
            sourceGrid.innerHTML = `
                <div class="identity-source-card ${data.sigas.found ? 'found' : ''}"><span><i class="bi bi-database-check"></i>Base SIGAS</span><strong>${data.sigas.found ? data.sigas.id : 'Não localizado'}</strong><small>${data.sigas.found ? data.sigas.status : 'Nenhum registro local'}</small></div>
                <div class="identity-source-card legacy ${data.semth.found ? 'found' : ''}"><span><i class="bi bi-database-lock"></i>Base SEMTH</span><strong>${data.semth.found ? data.semth.id : 'Não localizado'}</strong><small>${data.semth.found ? 'Consulta somente leitura' : 'Nenhuma correspondência'}</small></div>`;
            alert.className = `result-alert ${data.severity} mt-3`;
            this.qs('#integrationQueryTitle', root).textContent = data.title;
            this.qs('#integrationQueryMessage', root).textContent = data.message;
            empty.hidden = true;
            resultBox.hidden = false;
            this.showToast('Comparação realizada sem escrita na base SEMTH.');
        });
    },
    initActions() {
        document.addEventListener('click', event => {
            const pageLink = event.target.closest('.page-link[href="#"]');
            if (pageLink) event.preventDefault();
            const settingsSave = event.target.closest('[data-settings-save]');
            if (settingsSave) this.showToast('Configurações salvas em modo demonstrativo.');
            const demo = event.target.closest('[data-demo-action]');
            if (demo) this.showToast(`Ação demonstrativa: ${demo.dataset.demoAction}.`);
            const newAction = event.target.closest('[data-new-action]');
            if (newAction) {
                if (this.page === 'consulta') {
                    this.qs('[data-scan-reset]')?.click();
                    return;
                }
                window.location.href = 'cadastro-anexo.html';
            }
            const upload = event.target.closest('.document-upload');
            if (upload) {
                upload.classList.add('border-success');
                const status = this.qs('span', upload);
                if (status) status.textContent = 'Arquivo demonstrativo selecionado';
                this.showToast('Documento anexado em modo demonstrativo.');
            }
        });
        this.qsa('[data-confirm-action]').forEach(button => button.addEventListener('click', () => {
            const modal = this.qs('#confirmationModal');
            if (!modal) return;
            this.qs('#confirmationText', modal).textContent = button.dataset.confirmAction;
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }));
        this.qs('#confirmActionButton')?.addEventListener('click', () => {
            const modal = this.qs('#confirmationModal');
            bootstrap.Modal.getOrCreateInstance(modal).hide();
            this.showToast('Ação confirmada com sucesso.');
        });
        this.qsa('[data-submit-demo]').forEach(button => button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            const fields = this.qsa('[required]', modal);
            let valid = true;
            fields.forEach(field => { const ok = field.value.trim() !== ''; field.classList.toggle('is-invalid', !ok); valid = valid && ok; });
            if (!valid) { this.showToast('Verifique os campos obrigatórios.', 'danger'); return; }
            bootstrap.Modal.getOrCreateInstance(modal).hide();
            this.showToast(button.dataset.submitDemo || 'Registro salvo com sucesso.');
        }));
    },
    init() {
        this.hydrateShell();
        this.initBootstrap();
        this.initLogin();
        this.initSidebar();
        this.initGlobalSearch();
        this.initTabs();
        this.initFilters();
        this.initCharts();
        this.initGenericCharts();
        this.initFormStepper();
        this.initMembers();
        this.initDocumentScanner();
        this.initIntegrationStatus();
        this.initSourceOwnership();
        this.initLegacyValidation();
        this.initRecordOwnership();
        this.initIntegrationExplorer();
        this.initActions();
        if (window.location.hash === '#editar') {
            const modal = this.qs('#editRegistrationModal');
            if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
        }
        if (window.location.hash === '#novo' && this.page !== 'cadastro-anexo') {
            window.location.replace('cadastro-anexo.html');
        }
    }
};

window.SIGAS = SIGAS;
document.addEventListener('DOMContentLoaded', () => SIGAS.init());
