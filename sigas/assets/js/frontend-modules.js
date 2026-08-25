'use strict';

(() => {
    const shell = document.querySelector('[data-module-shell]');
    const toastContainer = document.querySelector('#frontendToastContainer');
    const body = document.body;
    const currentModule = body?.dataset.module || '';
    const currentPage = body?.dataset.page || '';
    let selectedRecord = {};
    let selectedContext = {};

    const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[character]));

    const parseJSON = (value, fallback = {}) => {
        try {
            const parsed = JSON.parse(value || '');
            return parsed ?? fallback;
        } catch (_) {
            return fallback;
        }
    };

    const humanize = key => String(key || '')
        .replace(/^_+/, '')
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, letter => letter.toLocaleUpperCase('pt-BR'));

    const visibleRecordEntries = record => Object.entries(record || {}).filter(([key, value]) => (
        key !== '_actions'
        && !key.startsWith('__')
        && typeof value !== 'object'
        && typeof value !== 'function'
    ));

    const showToast = (message, type = 'success') => {
        if (!toastContainer || typeof bootstrap === 'undefined') return;
        const toast = document.createElement('div');
        toast.className = `toast border-0 text-bg-${type}`;
        toast.setAttribute('role', 'status');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHTML(message)}</div><button class="btn-close btn-close-white me-2 m-auto" type="button" data-bs-dismiss="toast" aria-label="Fechar"></button></div>`;
        toastContainer.appendChild(toast);
        const instance = new bootstrap.Toast(toast, { delay: 3200 });
        toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });
        instance.show();
    };

    const setMenu = open => {
        if (!shell) return;
        shell.classList.toggle('module-menu-open', open);
        document.body.classList.toggle('module-menu-is-open', open);
        document.querySelectorAll('[data-module-menu-toggle]').forEach(button => button.setAttribute('aria-expanded', String(open)));
    };

    const detailAction = (label = 'Visualizar detalhes', description = 'Consultar as informações deste registro.') => ({
        kind: 'detail',
        label,
        description,
        icon: 'eye',
        variant: 'primary'
    });

    const demoAction = (label, description, icon = 'pencil-square', variant = '') => ({
        kind: 'demo', label, description, icon, variant
    });

    const pageAction = (label, description, page, icon = 'arrow-right-circle', variant = '') => ({
        kind: 'navigate', label, description, page, icon, variant
    });

    const commonReportActions = [
        detailAction('Visualizar registro', 'Consultar os dados que compõem este item do relatório.'),
        demoAction('Exportar', 'Gerar uma saída deste registro no padrão do SIGAS.', 'download'),
        demoAction('Gerar relatório', 'Preparar uma versão gerencial para conferência.', 'file-earmark-bar-graph')
    ];

    const actionProfiles = {
        'kit-maternidade': {
            '*': [
                detailAction(),
                pageAction('Abrir beneficiárias', 'Ir para a base de gestantes e localizar o acompanhamento.', 'beneficiarias', 'person-hearts'),
                pageAction('Acompanhamento e visitas', 'Consultar ou registrar a evolução do acompanhamento.', 'visitas', 'house-check'),
                pageAction('Avaliação', 'Abrir a etapa de critérios, parecer e decisão.', 'avaliacao', 'clipboard2-check')
            ],
            beneficiarias: [
                detailAction('Visualizar ficha resumida', 'Consultar os dados e a etapa atual da gestante.'),
                pageAction('Acompanhamento / visitas', 'Abrir o acompanhamento territorial e as visitas realizadas.', 'visitas', 'house-check', 'primary'),
                pageAction('Reuniões e atividades', 'Consultar frequência e participação nas ações do programa.', 'reunioes', 'people'),
                pageAction('Avaliar contemplação', 'Abrir critérios, pendências, parecer e decisão.', 'avaliacao', 'clipboard2-check'),
                pageAction('Entrega do Kit', 'Consultar reserva, entrega e comprovação do benefício.', 'entregas', 'gift'),
                pageAction('Pós-parto / encerramento', 'Registrar nascimento, entrega pendente e fechamento do fluxo.', 'pos-parto', 'heart-pulse')
            ],
            cadastro: [
                detailAction('Visualizar cadastro', 'Conferir os dados informados na entrada do programa.'),
                demoAction('Continuar triagem', 'Conferir documentos, critérios e pendências cadastrais.', 'clipboard2-pulse', 'primary'),
                pageAction('Abrir beneficiárias', 'Voltar para a base de acompanhamentos.', 'beneficiarias', 'people')
            ],
            visitas: [
                detailAction('Visualizar visita', 'Consultar data, profissional, resultado e próxima ação.'),
                demoAction('Registrar / editar visita', 'Atualizar a visita e definir a próxima ação do acompanhamento.', 'house-check', 'primary'),
                pageAction('Abrir beneficiária', 'Consultar a situação geral da gestante.', 'beneficiarias', 'person-hearts')
            ],
            reunioes: [
                detailAction('Visualizar atividade', 'Consultar reunião, presença e justificativa.'),
                demoAction('Registrar presença', 'Atualizar participação ou justificar ausência.', 'person-check', 'primary'),
                pageAction('Abrir beneficiária', 'Consultar o acompanhamento completo da gestante.', 'beneficiarias', 'person-hearts')
            ],
            avaliacao: [
                detailAction('Visualizar avaliação', 'Consultar critérios, pendências e parecer atual.'),
                demoAction('Registrar decisão', 'Salvar parecer, aptidão ou motivo de não contemplação.', 'clipboard2-check', 'primary'),
                pageAction('Abrir entrega', 'Seguir para a etapa de entrega quando houver aprovação.', 'entregas', 'gift')
            ],
            entregas: [
                detailAction('Visualizar entrega', 'Consultar lote, situação e comprovantes.'),
                demoAction('Registrar entrega', 'Confirmar recebimento do Kit e dados da entrega.', 'gift', 'primary'),
                pageAction('Abrir pós-parto', 'Seguir para nascimento e encerramento do acompanhamento.', 'pos-parto', 'heart-pulse')
            ],
            'pos-parto': [
                detailAction('Visualizar situação', 'Consultar nascimento, entrega e condição de encerramento.'),
                demoAction('Registrar nascimento', 'Atualizar a data do nascimento e a situação pós-parto.', 'heart-pulse', 'primary'),
                demoAction('Encerrar fluxo', 'Finalizar o acompanhamento com motivo e histórico preservados.', 'check2-circle'),
                demoAction('Encerrar sem entrega', 'Registrar por que o parto ocorreu sem entrega do Kit.', 'exclamation-triangle', 'danger')
            ],
            relatorios: commonReportActions
        },
        'aluguel-social': {
            '*': [
                detailAction(),
                pageAction('Abrir beneficiários', 'Consultar a situação geral da família no programa.', 'beneficiarios', 'people'),
                pageAction('Vistorias', 'Consultar a avaliação do imóvel e da situação familiar.', 'vistorias', 'house-gear'),
                pageAction('Pagamentos', 'Consultar competências e pagamentos do benefício.', 'pagamentos', 'wallet2')
            ],
            beneficiarios: [
                detailAction('Visualizar ficha resumida', 'Consultar concessão, vigência e situação do benefício.'),
                pageAction('Solicitação', 'Consultar a origem e a triagem da solicitação.', 'solicitacoes', 'inboxes'),
                pageAction('Vistoria', 'Abrir vistoria, condições do imóvel e parecer territorial.', 'vistorias', 'house-gear', 'primary'),
                pageAction('Parecer', 'Abrir análise técnica e decisão do processo.', 'pareceres', 'clipboard2-check'),
                pageAction('Pagamentos', 'Consultar as competências e o histórico financeiro.', 'pagamentos', 'wallet2'),
                pageAction('Reavaliação', 'Revisar permanência, renovação ou encerramento.', 'reavaliacoes', 'arrow-repeat')
            ],
            solicitacoes: [
                detailAction('Visualizar solicitação', 'Consultar dados, motivo e situação atual.'),
                pageAction('Enviar para vistoria', 'Abrir a etapa de vistoria e avaliação territorial.', 'vistorias', 'house-gear', 'primary'),
                demoAction('Atualizar solicitação', 'Corrigir ou complementar dados da solicitação.', 'pencil-square')
            ],
            vistorias: [
                detailAction('Visualizar vistoria', 'Consultar endereço, avaliação e registro da visita.'),
                demoAction('Registrar vistoria', 'Atualizar resultado, documentação e observações técnicas.', 'house-check', 'primary'),
                pageAction('Emitir parecer', 'Seguir para a análise técnica da concessão.', 'pareceres', 'clipboard2-check')
            ],
            pareceres: [
                detailAction('Visualizar parecer', 'Consultar fundamentação, decisão e responsável.'),
                demoAction('Registrar decisão', 'Deferir, indeferir ou devolver para pendência.', 'clipboard2-check', 'primary'),
                pageAction('Abrir concessão', 'Seguir para vigência, imóvel e dados da concessão.', 'concessoes', 'key')
            ],
            concessoes: [
                detailAction('Visualizar concessão', 'Consultar imóvel, valor, vigência e responsável.'),
                demoAction('Atualizar concessão', 'Alterar dados permitidos mantendo o histórico.', 'pencil-square', 'primary'),
                pageAction('Pagamentos', 'Consultar competências financeiras vinculadas.', 'pagamentos', 'wallet2')
            ],
            pagamentos: [
                detailAction('Visualizar pagamento', 'Consultar competência, valor e situação.'),
                demoAction('Registrar / atualizar pagamento', 'Atualizar a competência financeira deste benefício.', 'wallet2', 'primary'),
                pageAction('Reavaliar benefício', 'Abrir a revisão de permanência ou renovação.', 'reavaliacoes', 'arrow-repeat')
            ],
            reavaliacoes: [
                detailAction('Visualizar reavaliação', 'Consultar prazo, situação e histórico do acompanhamento.'),
                demoAction('Registrar reavaliação', 'Definir renovação, permanência ou encerramento.', 'arrow-repeat', 'primary'),
                demoAction('Encerrar benefício', 'Finalizar a concessão preservando o histórico.', 'x-circle', 'danger')
            ],
            relatorios: commonReportActions
        },
        'beneficios-eventuais': {
            '*': [
                detailAction(),
                pageAction('Abrir solicitações', 'Consultar a demanda de origem deste atendimento.', 'solicitacoes', 'inboxes'),
                pageAction('Análises e pareceres', 'Consultar critérios, histórico e decisão.', 'analises', 'clipboard2-check'),
                pageAction('Entregas', 'Consultar concessão e efetivação do benefício.', 'entregas', 'box-seam')
            ],
            solicitacoes: [
                detailAction('Visualizar solicitação', 'Consultar pessoa, tipo de benefício e motivo da demanda.'),
                pageAction('Enviar para triagem', 'Abrir conferência cadastral, histórico e pendências.', 'triagem', 'clipboard2-pulse', 'primary'),
                demoAction('Atualizar solicitação', 'Complementar dados sem perder o histórico original.', 'pencil-square')
            ],
            triagem: [
                detailAction('Visualizar triagem', 'Consultar cadastro, histórico de benefícios e pendências.'),
                demoAction('Concluir triagem', 'Registrar conferência e encaminhar para análise.', 'check2-circle', 'primary'),
                pageAction('Abrir análise', 'Seguir para parecer e decisão.', 'analises', 'clipboard2-check')
            ],
            analises: [
                detailAction('Visualizar análise', 'Consultar critérios, histórico e parecer.'),
                demoAction('Registrar decisão', 'Deferir, indeferir ou devolver para pendência.', 'clipboard2-check', 'primary'),
                pageAction('Abrir concessão', 'Seguir para autorização do benefício.', 'concessoes', 'check2-circle')
            ],
            concessoes: [
                detailAction('Visualizar concessão', 'Consultar tipo, quantidade, valor e autorização.'),
                demoAction('Atualizar concessão', 'Registrar autorização e condições do benefício.', 'check2-circle', 'primary'),
                pageAction('Registrar entrega', 'Seguir para efetivação e comprovante.', 'entregas', 'box-seam')
            ],
            entregas: [
                detailAction('Visualizar entrega', 'Consultar data, quantidade, responsável e comprovação.'),
                demoAction('Registrar entrega', 'Confirmar a efetivação do benefício.', 'box-seam', 'primary'),
                demoAction('Registrar ocorrência', 'Informar não entrega, recusa ou outra intercorrência.', 'exclamation-circle')
            ],
            tipos: [
                detailAction('Visualizar regra', 'Consultar tipo de benefício, critérios e configuração.'),
                demoAction('Editar tipo / regra', 'Atualizar a configuração administrativa do benefício.', 'sliders', 'primary')
            ],
            relatorios: commonReportActions
        },
        'gestao-acessos': {
            '*': [
                detailAction(),
                demoAction('Editar registro', 'Atualizar este item conforme a sua permissão administrativa.', 'pencil-square', 'primary'),
                pageAction('Auditoria', 'Consultar alterações e eventos relacionados.', 'auditoria', 'journal-text')
            ],
            usuarios: [
                detailAction('Visualizar usuário', 'Consultar setor, cargo, perfil e situação da conta.'),
                demoAction('Editar usuário', 'Atualizar cadastro, setor, cargo e perfil.', 'person-gear', 'primary'),
                pageAction('Permissões', 'Consultar e administrar as permissões disponíveis.', 'permissoes', 'key'),
                pageAction('Sessões', 'Consultar sessões e acessos recentes.', 'sessoes', 'activity'),
                demoAction('Bloquear / inativar', 'Suspender o acesso sem apagar o histórico da conta.', 'person-lock', 'danger')
            ],
            cargos: [
                detailAction('Visualizar cargo', 'Consultar função, descrição e vínculos administrativos.'),
                demoAction('Editar cargo', 'Atualizar o cadastro do cargo.', 'person-badge', 'primary'),
                pageAction('Permissões', 'Consultar permissões relacionadas aos perfis.', 'permissoes', 'key')
            ],
            perfis: [
                detailAction('Visualizar perfil', 'Consultar nível, escopo e regras gerais de acesso.'),
                demoAction('Editar perfil', 'Atualizar o perfil e suas regras.', 'person-gear', 'primary'),
                pageAction('Permissões', 'Configurar as ações permitidas para o perfil.', 'permissoes', 'key')
            ],
            permissoes: [
                detailAction('Visualizar permissão', 'Consultar módulo, chave e finalidade da permissão.'),
                demoAction('Editar permissão', 'Atualizar descrição e configuração administrativa.', 'key', 'primary'),
                pageAction('Matriz de acesso', 'Aplicar a permissão aos setores autorizados.', 'matriz-acesso', 'grid-3x3-gap')
            ],
            setores: [
                detailAction('Visualizar setor', 'Consultar unidade, escopo e situação.'),
                demoAction('Editar setor', 'Atualizar os dados administrativos do setor.', 'diagram-3', 'primary'),
                pageAction('Matriz de acesso', 'Definir quais módulos este setor pode acessar.', 'matriz-acesso', 'grid-3x3-gap')
            ],
            'matriz-acesso': [
                detailAction('Visualizar regra', 'Consultar setor, módulo e situação do acesso.'),
                demoAction('Configurar acesso', 'Autorizar ou remover o acesso do setor ao módulo.', 'shield-check', 'primary'),
                pageAction('Auditoria', 'Consultar alterações feitas na matriz.', 'auditoria', 'journal-text')
            ],
            auditoria: [
                detailAction('Visualizar evento', 'Consultar a ocorrência completa de auditoria.'),
                demoAction('Exportar ocorrência', 'Gerar registro para conferência administrativa.', 'download')
            ],
            sessoes: [
                detailAction('Visualizar sessão', 'Consultar usuário, origem, horário e situação.'),
                demoAction('Encerrar sessão', 'Invalidar a sessão selecionada mantendo o registro de auditoria.', 'box-arrow-right', 'danger')
            ]
        }
    };

    const fallbackActions = () => {
        if (currentPage === 'relatorios') return commonReportActions;
        return [
            detailAction(),
            demoAction('Editar / atualizar', 'Abrir a ação de atualização deste registro.', 'pencil-square', 'primary'),
            demoAction('Histórico / auditoria', 'Consultar a linha do tempo e alterações relacionadas.', 'clock-history')
        ];
    };

    const normalizeAction = action => ({
        kind: action?.kind || action?.action || 'demo',
        label: action?.label || 'Ação',
        description: action?.description || action?.help || 'Continuar com esta ação.',
        icon: action?.icon || 'arrow-right-circle',
        variant: action?.variant || '',
        page: action?.page || '',
        href: action?.href || ''
    });

    const actionsForContext = customActions => {
        if (Array.isArray(customActions) && customActions.length) return customActions.map(normalizeAction);
        const moduleProfile = actionProfiles[currentModule] || {};
        const profile = moduleProfile[currentPage] || moduleProfile['*'];
        return (Array.isArray(profile) && profile.length ? profile : fallbackActions()).map(normalizeAction);
    };

    const interpolateHref = (href, record) => String(href || '').replace(/\{([a-zA-Z0-9_]+)\}/g, (_, key) => (
        encodeURIComponent(String(record?.[key] ?? ''))
    ));

    const actionHref = action => {
        if (action.href) return interpolateHref(action.href, selectedRecord);
        if (action.page) {
            return `setor.php?ambiente=${encodeURIComponent(currentModule)}&pagina=${encodeURIComponent(action.page)}`;
        }
        return '';
    };

    const fillDetail = (record, title = '') => {
        const content = document.querySelector('[data-detail-content]');
        if (content) {
            content.innerHTML = visibleRecordEntries(record).map(([key, value]) => (
                `<div><dt>${escapeHTML(humanize(key))}</dt><dd>${escapeHTML(value === '' || value === null || value === undefined ? '—' : value)}</dd></div>`
            )).join('');
        }
        const detailTitle = document.querySelector('#frontendDetailTitle');
        if (detailTitle) detailTitle.textContent = title ? `Detalhes — ${title}` : 'Detalhes do registro';
    };

    const fillRowActionModal = (record, context, customActions) => {
        selectedRecord = record || {};
        selectedContext = context || {};

        const title = document.querySelector('[data-row-action-title]');
        const subtitle = document.querySelector('[data-row-action-subtitle]');
        const summary = document.querySelector('[data-row-action-summary]');
        const actionList = document.querySelector('[data-row-action-list]');

        if (title) title.textContent = context.title || 'Registro selecionado';
        if (subtitle) subtitle.textContent = context.tableTitle ? `${context.tableTitle} · escolha a próxima ação` : 'Escolha a próxima ação.';

        if (summary) {
            const entries = visibleRecordEntries(record).slice(0, 4);
            summary.innerHTML = entries.map(([key, value]) => (
                `<div><span>${escapeHTML(humanize(key))}</span><strong>${escapeHTML(value === '' || value === null || value === undefined ? '—' : value)}</strong></div>`
            )).join('');
        }

        if (actionList) {
            actionList.innerHTML = actionsForContext(customActions).map((action, index) => {
                const variantClass = action.variant ? ` frontend-row-action--${escapeHTML(action.variant)}` : '';
                return `<button class="frontend-row-action${variantClass}" type="button" data-sigas-row-action-index="${index}" data-sigas-row-action-payload="${escapeHTML(JSON.stringify(action))}">
                    <span class="frontend-row-action__icon"><i class="bi bi-${escapeHTML(action.icon)}"></i></span>
                    <span class="frontend-row-action__copy"><strong>${escapeHTML(action.label)}</strong><small>${escapeHTML(action.description)}</small></span>
                    <i class="bi bi-chevron-right frontend-row-action__chevron" aria-hidden="true"></i>
                </button>`;
            }).join('');
        }
    };

    const openRowActions = row => {
        if (!row || typeof bootstrap === 'undefined') return;
        const record = parseJSON(row.dataset.sigasRecord, {});
        const customActions = parseJSON(row.dataset.sigasRowActions, []);
        fillRowActionModal(record, {
            title: row.dataset.sigasRowTitle || 'Registro selecionado',
            tableTitle: row.dataset.sigasTableTitle || ''
        }, Array.isArray(customActions) ? customActions : []);
        bootstrap.Modal.getOrCreateInstance(document.querySelector('#frontendRowActionModal')).show();
    };

    const openAfterHidden = (fromElement, callback) => {
        if (!fromElement) {
            callback();
            return;
        }
        const instance = bootstrap.Modal.getOrCreateInstance(fromElement);
        if (!fromElement.classList.contains('show')) {
            callback();
            return;
        }
        fromElement.addEventListener('hidden.bs.modal', callback, { once: true });
        instance.hide();
    };

    const runRowAction = action => {
        const rowModal = document.querySelector('#frontendRowActionModal');
        if (!action) return;

        if (action.kind === 'navigate') {
            const href = actionHref(action);
            if (href) window.location.href = href;
            return;
        }

        if (action.kind === 'detail' || action.kind === 'view') {
            fillDetail(selectedRecord, selectedContext.title || '');
            openAfterHidden(rowModal, () => bootstrap.Modal.getOrCreateInstance(document.querySelector('#frontendDetailModal')).show());
            return;
        }

        if (action.kind === 'href' && action.href) {
            window.location.href = actionHref(action);
            return;
        }

        const actionTitle = document.querySelector('#frontendActionTitle');
        if (actionTitle) actionTitle.textContent = action.label || 'Ação do registro';
        openAfterHidden(rowModal, () => bootstrap.Modal.getOrCreateInstance(document.querySelector('#frontendActionModal')).show());
    };

    document.addEventListener('click', event => {
        if (event.target.closest('[data-module-menu-toggle]')) setMenu(true);
        if (event.target.closest('[data-module-menu-close]')) setMenu(false);
        if (shell?.classList.contains('module-menu-open') && event.target === shell) setMenu(false);

        const returnToActions = event.target.closest('[data-return-row-actions]');
        if (returnToActions) {
            event.preventDefault();
            const detailModal = document.querySelector('#frontendDetailModal');
            openAfterHidden(detailModal, () => bootstrap.Modal.getOrCreateInstance(document.querySelector('#frontendRowActionModal')).show());
            return;
        }

        const rowActionButton = event.target.closest('[data-sigas-row-action-payload]');
        if (rowActionButton) {
            event.preventDefault();
            runRowAction(parseJSON(rowActionButton.dataset.sigasRowActionPayload, {}));
            return;
        }

        const row = event.target.closest('[data-sigas-action-row]');
        if (row && !event.target.closest('a,button,input,select,textarea,label,[contenteditable="true"]')) {
            event.preventDefault();
            openRowActions(row);
            return;
        }

        const action = event.target.closest('[data-demo-action]');
        if (action) {
            const title = document.querySelector('#frontendActionTitle');
            if (title) title.textContent = action.dataset.demoAction || 'Recurso visual';
            bootstrap.Modal.getOrCreateInstance(document.querySelector('#frontendActionModal')).show();
            return;
        }

        // Compatibilidade com componentes antigos que ainda disparam o detalhe diretamente.
        const detail = event.target.closest('[data-detail-record]');
        if (detail) {
            const record = parseJSON(detail.dataset.detailRecord, {});
            fillDetail(record, detail.dataset.detailTitle || '');
            bootstrap.Modal.getOrCreateInstance(document.querySelector('#frontendDetailModal')).show();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && shell?.classList.contains('module-menu-open')) setMenu(false);

        const row = event.target.closest?.('[data-sigas-action-row]');
        if (!row || !['Enter', ' '].includes(event.key)) return;
        if (event.target.closest('a,button,input,select,textarea,label,[contenteditable="true"]')) return;
        event.preventDefault();
        openRowActions(row);
    });

    document.querySelectorAll('[data-frontend-filter]').forEach(form => {
        const apply = () => {
            const search = (form.querySelector('[data-filter-search]')?.value || '').trim().toLocaleLowerCase('pt-BR');
            const selections = [...form.querySelectorAll('[data-filter-select]')].map(field => field.value).filter(Boolean);
            let visible = 0;
            document.querySelectorAll('[data-filter-row]').forEach(row => {
                const content = row.dataset.search || row.textContent.toLocaleLowerCase('pt-BR');
                const matches = (!search || content.includes(search)) && selections.every(value => content.includes(value));
                row.hidden = !matches;
                if (matches) visible += 1;
            });
            document.querySelectorAll('[data-no-results]').forEach(state => { state.hidden = visible > 0; });
        };
        form.addEventListener('input', apply);
        form.addEventListener('reset', () => window.setTimeout(apply));
    });

    document.querySelectorAll('[data-demo-form]').forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        if (button) button.disabled = true;
        window.setTimeout(() => {
            if (button) button.disabled = false;
            const modal = form.closest('.modal');
            if (modal) bootstrap.Modal.getOrCreateInstance(modal).hide();
            showToast('Alteração visual concluída. Nenhum dado foi persistido.');
        }, 450);
    }));

    document.querySelectorAll('canvas[data-frontend-chart]').forEach(canvas => {
        if (typeof Chart === 'undefined') return;
        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const values = JSON.parse(canvas.dataset.values || '[]');
        new Chart(canvas, {
            type: canvas.dataset.frontendChart || 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Total',
                    data: values,
                    backgroundColor: 'rgba(23,107,58,.18)',
                    borderColor: '#176b3a',
                    borderWidth: 2,
                    borderRadius: 7,
                    fill: true,
                    tension: .35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: canvas.dataset.frontendChart === 'doughnut' ? undefined : { x: { grid: { display: false } }, y: { beginAtZero: true } }
            }
        });
    });

    window.SIGAS_FRONTEND = { showToast, openRowActions };
})();
