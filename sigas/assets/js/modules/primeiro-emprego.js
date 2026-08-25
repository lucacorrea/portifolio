'use strict';

/* ======================================================================
 * MEU PRIMEIRO EMPREGO — COMPORTAMENTOS GLOBAIS DO MÓDULO
 * ====================================================================== */
(() => {
    const dialogSelector = 'dialog.pe-modal, dialog.pe-candidate-modal';

    const openDialog = dialog => {
        if (!(dialog instanceof HTMLElement)) return;

        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) dialog.showModal();
            return;
        }

        dialog.setAttribute('open', '');
    };

    const closeDialog = dialog => {
        if (!(dialog instanceof HTMLElement)) return;

        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
            return;
        }

        dialog.removeAttribute('open');
    };

    const cleanActionParam = param => {
        if (!param || !window.history?.replaceState) return;

        const url = new URL(window.location.href);
        url.searchParams.delete(param);
        url.hash = '';
        window.history.replaceState({}, '', url.href);
    };

    const buildActionUrl = (param, itemId) => {
        const url = new URL(window.location.href);

        ['revisar', 'visita', 'ficha'].forEach(key => {
            url.searchParams.delete(key);
        });

        url.searchParams.set(param, String(itemId));
        url.hash = '';

        return url.href;
    };

    const bindCandidateActionDialog = () => {
        const rows = Array.from(
            document.querySelectorAll('[data-pe-candidate-row]')
        );

        const dialog = document.getElementById('peCandidateDialog');

        if (!rows.length || !dialog) {
            return;
        }

        const field = selector => dialog.querySelector(selector);

        const fields = {
            name: field('[data-pe-modal-name]'),
            meta: field('[data-pe-modal-meta]'),
            cpf: field('[data-pe-modal-cpf]'),
            phone: field('[data-pe-modal-phone]'),
            birth: field('[data-pe-modal-birth]'),
            neighborhood: field('[data-pe-modal-neighborhood]'),
            sector: field('[data-pe-modal-sector]'),
            status: field('[data-pe-modal-status]'),
            review: field('[data-pe-modal-review]'),
            reviewDetails: field('[data-pe-modal-review-details]'),
            reviewBox: field('[data-pe-modal-review-box]'),
            reviewAction: field('[data-pe-modal-action-review]'),
            visitAction: field('[data-pe-modal-action-visit]'),
            profileAction: field('[data-pe-modal-action-profile]'),
        };

        const fillDialog = row => {
            const data = row.dataset;

            if (fields.name) {
                fields.name.textContent =
                    data.name || 'Candidato';
            }

            if (fields.meta) {
                fields.meta.textContent =
                    `#${data.id || '—'} · ${data.origin || 'origem não informada'}`;
            }

            if (fields.cpf) {
                fields.cpf.textContent =
                    data.cpf || '—';
            }

            if (fields.phone) {
                fields.phone.textContent =
                    data.phone || '—';
            }

            if (fields.birth) {
                fields.birth.textContent =
                    data.birth || '—';
            }

            if (fields.neighborhood) {
                fields.neighborhood.textContent =
                    data.neighborhood || '—';
            }

            if (fields.sector) {
                fields.sector.textContent =
                    data.sector || '—';
            }

            if (fields.status) {
                fields.status.textContent =
                    data.status || '—';
            }

            if (fields.review) {
                fields.review.textContent =
                    data.review || 'Sem pendência';
            }

            if (fields.reviewDetails) {
                fields.reviewDetails.textContent =
                    data.reviewDetails || 'Cadastro sem pendências';
            }

            if (fields.reviewBox) {
                fields.reviewBox.classList.remove(
                    'is-warning',
                    'is-multiple',
                    'is-critical'
                );

                if (data.duplicate === '1') {
                    fields.reviewBox.classList.add(
                        'is-critical'
                    );
                } else if (
                    (data.review || '')
                        .toLowerCase()
                        .includes('cadastro')
                ) {
                    fields.reviewBox.classList.add(
                        'is-multiple'
                    );
                } else if (
                    (data.review || '') &&
                    data.review !== 'Sem pendência'
                ) {
                    fields.reviewBox.classList.add(
                        'is-warning'
                    );
                }
            }

            if (fields.reviewAction) {
                fields.reviewAction.href =
                    buildActionUrl(
                        'revisar',
                        data.id
                    );
            }

            if (fields.visitAction) {
                fields.visitAction.href =
                    buildActionUrl(
                        'visita',
                        data.id
                    );
            }

            if (fields.profileAction) {
                fields.profileAction.href =
                    buildActionUrl(
                        'ficha',
                        data.id
                    );
            }
        };

        const showActions = row => {
            fillDialog(row);
            openDialog(dialog);
        };

        rows.forEach(row => {
            row.addEventListener(
                'click',
                event => {
                    if (
                        event.target.closest(
                            'a, button, input, select, textarea, label'
                        )
                    ) {
                        return;
                    }

                    showActions(row);
                }
            );

            row.addEventListener(
                'keydown',
                event => {
                    if (
                        event.key !== 'Enter' &&
                        event.key !== ' '
                    ) {
                        return;
                    }

                    event.preventDefault();

                    showActions(row);
                }
            );
        });
    };

    const bindGenericDialogTriggers = () => {
        document
            .querySelectorAll('[data-pe-dialog-target]')
            .forEach(trigger => {
                trigger.addEventListener(
                    'click',
                    event => {
                        const selector =
                            trigger.getAttribute(
                                'data-pe-dialog-target'
                            );

                        if (!selector) {
                            return;
                        }

                        const dialog =
                            document.querySelector(
                                selector
                            );

                        if (!dialog) {
                            return;
                        }

                        event.preventDefault();

                        openDialog(dialog);
                    }
                );
            });
    };

    const bindDialogClosing = () => {
        document
            .querySelectorAll(
                '[data-pe-dialog-close], [data-pe-modal-close]'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => {
                        const dialog =
                            button.closest('dialog');

                        closeDialog(dialog);

                        cleanActionParam(
                            button.dataset.cleanParam || ''
                        );
                    }
                );
            });

        document
            .querySelectorAll(dialogSelector)
            .forEach(dialog => {
                dialog.addEventListener(
                    'click',
                    event => {
                        if (
                            event.target !== dialog
                        ) {
                            return;
                        }

                        closeDialog(dialog);

                        const closeButton =
                            dialog.querySelector(
                                '[data-clean-param]'
                            );

                        cleanActionParam(
                            closeButton?.dataset
                                .cleanParam || ''
                        );
                    }
                );

                dialog.addEventListener(
                    'cancel',
                    () => {
                        const closeButton =
                            dialog.querySelector(
                                '[data-clean-param]'
                            );

                        cleanActionParam(
                            closeButton?.dataset
                                .cleanParam || ''
                        );
                    }
                );
            });
    };

    bindCandidateActionDialog();
    bindGenericDialogTriggers();
    bindDialogClosing();

    document
        .querySelectorAll(
            'dialog[data-pe-auto-open]'
        )
        .forEach(openDialog);
})();


/* ======================================================================
 * WIZARD DE CADASTRO — preservado do módulo original
 * ====================================================================== */
(() => {
    const wizard =
        document.querySelector(
            '[data-pe-wizard]'
        );

    if (!wizard) {
        return;
    }

    const form =
        wizard.querySelector(
            '[data-pe-wizard-form]'
        );

    const panels = [
        ...wizard.querySelectorAll(
            '[data-pe-step]'
        )
    ];

    const indicators = [
        ...wizard.querySelectorAll(
            '[data-pe-step-indicator]'
        )
    ];

    const progress =
        wizard.querySelector(
            '[data-pe-progress]'
        );

    const progressBar =
        progress?.closest(
            '[role="progressbar"]'
        );

    const progressText =
        wizard.querySelector(
            '[data-pe-progress-text]'
        );

    const previous =
        wizard.querySelector(
            '[data-pe-prev]'
        );

    const next =
        wizard.querySelector(
            '[data-pe-next]'
        );

    let current = 0;

    const notify = message =>
        window.SIGAS_FRONTEND?.showToast(
            message
        );

    const escapeHTML = value =>
        String(value ?? '').replace(
            /[&<>"']/g,
            character =>
                ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                })[character]
        );

    const updateReview = () => {
        const review =
            wizard.querySelector(
                '[data-pe-review]'
            );

        if (!review || !form) {
            return;
        }

        const fields = [
            [
                'Nome',
                form.elements
                    .candidate_name
                    ?.value
            ],
            [
                'Contato',
                form.elements
                    .candidate_phone
                    ?.value
            ],
            [
                'Bairro',
                form.elements
                    .candidate_neighborhood
                    ?.value
            ],
            [
                'Escolaridade',
                form.elements
                    .candidate_education
                    ?.value
            ],
            [
                'Experiência',
                form.elements
                    .candidate_experience
                    ?.value
            ],
            [
                'Disponibilidade',
                form.elements
                    .candidate_availability
                    ?.value
            ],
        ];

        review.innerHTML =
            fields
                .map(
                    ([label, value]) =>
                        `<div>
                            <dt>${escapeHTML(label)}</dt>
                            <dd>${escapeHTML(
                                value ||
                                'Não informado'
                            )}</dd>
                        </div>`
                )
                .join('');
    };

    const render = focus => {
        panels.forEach(
            (panel, index) => {
                panel.hidden =
                    index !== current;
            }
        );

        indicators.forEach(
            (indicator, index) => {
                if (index === current) {
                    indicator.setAttribute(
                        'aria-current',
                        'step'
                    );
                } else {
                    indicator.removeAttribute(
                        'aria-current'
                    );
                }
            }
        );

        const percentage =
            (
                (current + 1) /
                panels.length
            ) * 100;

        if (progress) {
            progress.style.width =
                `${percentage}%`;
        }

        if (progressBar) {
            progressBar.setAttribute(
                'aria-valuenow',
                String(current + 1)
            );
        }

        if (progressText) {
            progressText.textContent =
                `Etapa ${current + 1} de ${panels.length}`;
        }

        if (previous) {
            previous.disabled =
                current === 0;
        }

        if (next) {
            next.innerHTML =
                current ===
                panels.length - 1
                    ? 'Confirmar visualmente<i class="bi bi-check-lg"></i>'
                    : 'Próximo<i class="bi bi-arrow-right"></i>';
        }

        if (
            current ===
            panels.length - 1
        ) {
            updateReview();
        }

        if (focus) {
            panels[current]
                ?.querySelector('h3')
                ?.focus();
        }
    };

    const currentIsValid = () => {
        const required = [
            ...panels[current]
                .querySelectorAll(
                    '[required]'
                )
        ];

        const areas = [
            ...panels[current]
                .querySelectorAll(
                    '[name="candidate_areas[]"]'
                )
        ];

        const areasValid =
            areas.length === 0 ||
            areas.some(
                field => field.checked
            );

        areas[0]?.setCustomValidity(
            areasValid
                ? ''
                : 'Selecione ao menos uma área de interesse.'
        );

        const valid =
            required.every(
                field =>
                    field.checkValidity()
            ) &&
            areasValid;

        panels[current]
            .classList.toggle(
                'was-validated',
                !valid
            );

        if (!valid) {
            [
                ...required,
                ...areas
            ]
                .find(
                    field =>
                        !field.checkValidity()
                )
                ?.reportValidity();
        }

        return valid;
    };

    previous?.addEventListener(
        'click',
        () => {
            current =
                Math.max(
                    0,
                    current - 1
                );

            render(true);
        }
    );

    next?.addEventListener(
        'click',
        () => {
            if (!currentIsValid()) {
                return;
            }

            if (
                current ===
                panels.length - 1
            ) {
                const title =
                    document.querySelector(
                        '#frontendActionTitle'
                    );

                if (title) {
                    title.textContent =
                        'Confirmar cadastro demonstrativo';
                }

                const actionDialog =
                    document.querySelector(
                        '#frontendActionModal'
                    );

                if (
                    actionDialog &&
                    typeof actionDialog
                        .showModal ===
                        'function'
                ) {
                    actionDialog.showModal();
                } else if (
                    window.bootstrap?.Modal
                ) {
                    window.bootstrap
                        .Modal
                        .getOrCreateInstance(
                            '#frontendActionModal'
                        )
                        .show();
                } else {
                    notify?.(
                        'Cadastro revisado visualmente.'
                    );
                }

                return;
            }

            current += 1;

            render(true);
        }
    );

    indicators.forEach(
        (indicator, index) =>
            indicator.addEventListener(
                'click',
                () => {
                    if (
                        index > current &&
                        !currentIsValid()
                    ) {
                        return;
                    }

                    current = index;

                    render(true);
                }
            )
    );

    wizard
        .querySelector(
            '[data-pe-save-draft]'
        )
        ?.addEventListener(
            'click',
            () => {
                notify?.(
                    'Rascunho visual preparado. Nenhum dado foi persistido.'
                );
            }
        );

    render(false);
})();
/* ======================================================================
 * LISTAGENS OPERACIONAIS — CRUD EM MODAIS
 * ====================================================================== */
(() => {
    let currentRecord = null;

    const dialogs = () => [...document.querySelectorAll('dialog.pe-modal')];
    const open = dialog => {
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
    };
    const close = dialog => {
        if (!dialog) return;
        if (typeof dialog.close === 'function' && dialog.open) dialog.close();
        else dialog.removeAttribute('open');
    };
    const closeAllExcept = target => dialogs().forEach(dialog => { if (dialog !== target && dialog.open) close(dialog); });
    const stringify = value => value === null || value === undefined || value === '' ? '—' : String(value);

    const fillScope = (scope, record) => {
        if (!scope || !record) return;
        scope.querySelectorAll('[data-pe-current-title]').forEach(el => { el.textContent = stringify(record.__title || record.nome || record.candidato || record.cargo || record.curso); });
        scope.querySelectorAll('[data-pe-current-subtitle]').forEach(el => { el.textContent = stringify(record.__subtitle || 'Registro selecionado'); });
        scope.querySelectorAll('[data-pe-text]').forEach(el => {
            const key = el.getAttribute('data-pe-text');
            el.textContent = stringify(record[key]);
        });
        scope.querySelectorAll('[data-pe-href]').forEach(el => {
            const key = el.getAttribute('data-pe-href');
            const href = record[key] || '';
            if (href) {
                el.href = href;
                el.hidden = false;
            } else {
                el.removeAttribute('href');
                el.hidden = true;
            }
        });
    };

    const fillForm = (dialog, record, mode) => {
        const form = dialog?.querySelector('[data-pe-record-form]');
        if (!form) return;
        form.reset();
        const title = dialog.querySelector('[data-pe-form-title]');
        if (title) title.textContent = mode === 'edit' ? (dialog.dataset.peEditTitle || 'Editar registro') : (dialog.dataset.peCreateTitle || 'Novo registro');
        if (mode !== 'edit' || !record) {
            form.querySelectorAll('[data-pe-field="id"]').forEach(field => { field.value = ''; });
            return;
        }
        form.querySelectorAll('[data-pe-field]').forEach(field => {
            const key = field.getAttribute('data-pe-field');
            if (!key) return;
            const value = record[key];
            if (field.type === 'checkbox') {
                field.checked = value === true || value === 1 || value === '1';
            } else if (field.type !== 'file') {
                field.value = value === null || value === undefined ? '' : String(value);
            }
        });
    };

    const showRowActions = row => {
        try { currentRecord = JSON.parse(row.getAttribute('data-pe-record') || '{}'); }
        catch (_) { currentRecord = {}; }
        const selector = row.getAttribute('data-pe-actions-target');
        const dialog = selector ? document.querySelector(selector) : null;
        if (!dialog) return;
        fillScope(dialog, currentRecord);
        closeAllExcept(dialog);
        open(dialog);
    };

    document.querySelectorAll('[data-pe-list-row]').forEach(row => {
        row.addEventListener('click', event => {
            if (event.target.closest('a,button,input,select,textarea,label')) return;
            showRowActions(row);
        });
        row.addEventListener('keydown', event => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            showRowActions(row);
        });
    });

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-pe-open]');
        if (!trigger) return;
        const selector = trigger.getAttribute('data-pe-open');
        if (!selector) return;
        const dialog = document.querySelector(selector);
        if (!dialog) return;
        event.preventDefault();
        const mode = trigger.getAttribute('data-pe-mode') || 'view';
        if (mode === 'create') currentRecord = null;
        if (mode === 'create' || mode === 'edit') fillForm(dialog, currentRecord, mode);
        if (currentRecord) {
            fillScope(dialog, currentRecord);
            dialog.querySelectorAll('[data-pe-field]').forEach(field => {
                const key = field.getAttribute('data-pe-field');
                if (!key || field.type === 'file') return;
                const value = currentRecord[key];
                if (field.type === 'checkbox') field.checked = value === true || value === 1 || value === '1';
                else field.value = value === null || value === undefined ? '' : String(value);
            });
        }
        closeAllExcept(dialog);
        open(dialog);
    });

    document.querySelectorAll('dialog.pe-modal').forEach(dialog => {
        dialog.addEventListener('click', event => {
            if (event.target === dialog) close(dialog);
        });
    });

    document.querySelectorAll('[data-pe-list-search]').forEach(input => {
        input.addEventListener('input', () => {
            const query = input.value.trim().toLocaleLowerCase('pt-BR');
            const scopeSelector = input.getAttribute('data-pe-list-scope');
            const table = scopeSelector ? document.querySelector(scopeSelector) : input.closest('.pe-list-page')?.querySelector('[data-pe-list-table]');
            if (!table) return;
            table.querySelectorAll('[data-pe-list-row]').forEach(row => {
                row.hidden = query !== '' && !row.textContent.toLocaleLowerCase('pt-BR').includes(query);
            });
        });
    });
})();

/* Exclusão de candidato a partir da modal de ações. */
(() => {
    const button = document.querySelector('[data-pe-candidate-delete]');
    const actions = document.getElementById('peCandidateDialog');
    const dialog = document.getElementById('peCandidateDeleteDialog');
    if (!button || !actions || !dialog) return;
    button.addEventListener('click', () => {
        const reviewLink = actions.querySelector('[data-pe-modal-action-review]');
        let id = '';
        try { id = new URL(reviewLink?.href || '', window.location.href).searchParams.get('revisar') || ''; } catch (_) {}
        const name = actions.querySelector('[data-pe-modal-name]')?.textContent?.trim() || 'Candidato selecionado';
        const idField = dialog.querySelector('[data-pe-candidate-delete-id]');
        const nameField = dialog.querySelector('[data-pe-candidate-delete-name]');
        if (idField) idField.value = id;
        if (nameField) nameField.textContent = name;
        if (typeof actions.close === 'function' && actions.open) actions.close();
        if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open','');
    });
})();

/* ======================================================================
 * PADRÃO OPERACIONAL — INDICADORES, FILTROS E CORES SEM FUNDO
 * ====================================================================== */
(() => {
    const normalize = value => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLocaleLowerCase('pt-BR');

    const toneForStatus = value => {
        const status = normalize(value);
        if (!status) return 'muted';

        const danger = [
            'indeferido', 'indeferida', 'suspensa', 'suspenso', 'vencido', 'vencida',
            'cancelada', 'cancelado', 'nao selecionado', 'irregular', 'revisar lotacao',
            'cpf duplicado', 'bloqueado', 'bloqueada'
        ];
        const warning = [
            'pendente', 'atencao', 'em analise', 'programada', 'em selecao',
            'entrevista marcada', 'revisar', 'nao lotado', 'previsto', 'prevista',
            'revisar cadastro', 'revisar cpf', 'revisar telefone', 'revisar nascimento', 'com pendencia', 'sem arquivo'
        ];
        const success = [
            'ativa', 'ativo', 'aberta', 'aberto', 'regular', 'deferido', 'deferida',
            'aprovado', 'aprovada', 'paga', 'pago', 'concluida', 'concluido',
            'disponivel', 'contemplado', 'contemplada', 'lotado', 'lotada',
            'preenchida', 'presente', 'sem pendencia'
        ];
        const info = [
            'em andamento', 'em processamento', 'encaminhado', 'encaminhada',
            'inscricoes abertas', 'planejada', 'planejado', 'pronto para importar',
            'inscrito', 'inscrita'
        ];
        const muted = ['encerrada', 'encerrado', 'nao se aplica', 'arquivado', 'arquivada'];

        if (danger.includes(status) || status.includes('revisar lotacao') || status.includes('vencid')) return 'danger';
        if (warning.includes(status) || status.includes('pendente') || status.includes('atencao')) return 'warning';
        if (success.includes(status) || status.includes('conclu') || status.includes('aprovad') || status.includes('regular')) return 'success';
        if (info.includes(status)) return 'info';
        if (muted.includes(status)) return 'muted';
        return 'neutral';
    };

    const colorStatuses = () => {
        document.querySelectorAll('.pe-status-text').forEach(element => {
            if ([...element.classList].some(name => name.startsWith('is-'))) return;
            element.classList.add(`is-${toneForStatus(element.textContent)}`);
        });
    };

    const bindPanel = panel => {
        const selector = panel.getAttribute('data-pe-filter-scope');
        const scope = selector ? document.querySelector(selector) : null;
        if (!scope) return;

        const rows = () => [...scope.querySelectorAll('[data-pe-list-row]')];
        const search = panel.querySelector('[data-pe-filter-search]');
        const selects = [...panel.querySelectorAll('[data-pe-filter-key]')];
        const clear = panel.querySelector('[data-pe-filter-clear]');
        const count = panel.querySelector('[data-pe-filter-count]');
        const empty = scope.parentElement?.querySelector('[data-pe-filter-empty]');

        const apply = () => {
            const query = normalize(search?.value || '');
            let visible = 0;

            rows().forEach(row => {
                let matches = query === '' || normalize(row.textContent).includes(query);

                if (matches) {
                    selects.forEach(select => {
                        if (!matches || !select.value) return;
                        const key = select.getAttribute('data-pe-filter-key');
                        const attributeKey = String(key || '').replaceAll('_', '-');
                        const rowValue = normalize(row.getAttribute(`data-pe-filter-${attributeKey}`) || '');
                        const filterValue = normalize(select.value);
                        if (rowValue !== filterValue) matches = false;
                    });
                }

                row.hidden = !matches;
                if (matches) visible += 1;
            });

            if (count) count.textContent = String(visible);
            if (empty) empty.hidden = visible !== 0;
        };

        search?.addEventListener('input', apply);
        selects.forEach(select => select.addEventListener('change', apply));
        clear?.addEventListener('click', () => {
            if (search) search.value = '';
            selects.forEach(select => { select.value = ''; });
            apply();
            search?.focus();
        });

        panel._peApplyFilters = apply;
        apply();
    };

    document.querySelectorAll('[data-pe-filter-scope]').forEach(bindPanel);

    document.querySelectorAll('[data-pe-metric-filter-key]').forEach(metric => {
        metric.addEventListener('click', () => {
            const grid = metric.closest('[data-pe-metric-panel]');
            const panelSelector = grid?.getAttribute('data-pe-metric-panel');
            const panel = panelSelector ? document.querySelector(panelSelector) : null;
            if (!panel) return;

            const key = metric.getAttribute('data-pe-metric-filter-key');
            const value = metric.getAttribute('data-pe-metric-filter-value');
            const select = panel.querySelector(`[data-pe-filter-key="${CSS.escape(key || '')}"]`);
            if (!select) return;

            select.value = value || '';
            if (typeof panel._peApplyFilters === 'function') panel._peApplyFilters();
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    colorStatuses();
})();

/* =====================================================================
 * CENTRAL DE IMPORTAÇÕES — alternância segura de abas (2026-08-21)
 * Fica no JS oficial do módulo, que é carregado em todas as páginas.
 * ===================================================================== */
(() => {
    'use strict';

    const allowedModes = ['spreadsheet', 'waitlist', 'payment-pdf'];
    const hashToMode = {
        '#lista-espera': 'waitlist',
        '#pdf-pagamentos': 'payment-pdf',
    };
    const modeToHash = {
        spreadsheet: '',
        waitlist: '#lista-espera',
        'payment-pdf': '#pdf-pagamentos',
    };

    const normalizeMode = mode => (
        allowedModes.includes(String(mode || ''))
            ? String(mode)
            : 'spreadsheet'
    );

    const modeFromHash = () => hashToMode[window.location.hash] || null;

    const updateHash = mode => {
        if (!window.history?.replaceState) return;

        const url = new URL(window.location.href);
        url.hash = modeToHash[mode] || '';
        window.history.replaceState({}, '', url.href);
    };

    const bindHub = hub => {
        if (!(hub instanceof HTMLElement) || hub.dataset.peImportBound === '1') {
            return null;
        }

        const buttons = Array.from(hub.querySelectorAll('[data-pe-import-mode]'));
        const panels = Array.from(hub.querySelectorAll('[data-pe-import-panel]'));

        if (!buttons.length || !panels.length) {
            return null;
        }

        hub.dataset.peImportBound = '1';

        let currentMode = 'spreadsheet';

        const setMode = (requestedMode, options = {}) => {
            const mode = normalizeMode(requestedMode);
            const {
                updateUrl = true,
                focusTab = false,
            } = options;

            currentMode = mode;
            hub.dataset.peActiveImportMode = mode;

            buttons.forEach(button => {
                const isActive = button.dataset.peImportMode === mode;

                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                button.setAttribute('tabindex', isActive ? '0' : '-1');

                if (isActive && focusTab) {
                    button.focus({ preventScroll: true });
                }
            });

            panels.forEach(panel => {
                const isActive = panel.dataset.peImportPanel === mode;

                panel.hidden = !isActive;
                panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');

                if ('inert' in panel) {
                    panel.inert = !isActive;
                }
            });

            if (updateUrl) {
                updateHash(mode);
            }

            hub.dispatchEvent(new CustomEvent('pe:import-mode-change', {
                bubbles: true,
                detail: { mode },
            }));

            return mode;
        };

        const moveFocus = (currentButton, direction) => {
            const currentIndex = buttons.indexOf(currentButton);
            if (currentIndex < 0) return;

            const nextIndex = (
                currentIndex + direction + buttons.length
            ) % buttons.length;

            setMode(buttons[nextIndex].dataset.peImportMode, {
                updateUrl: true,
                focusTab: true,
            });
        };

        buttons.forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                setMode(button.dataset.peImportMode, {
                    updateUrl: true,
                    focusTab: false,
                });
            });

            button.addEventListener('keydown', event => {
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                    event.preventDefault();
                    moveFocus(button, 1);
                    return;
                }

                if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    moveFocus(button, -1);
                    return;
                }

                if (event.key === 'Home') {
                    event.preventDefault();
                    setMode(buttons[0].dataset.peImportMode, {
                        updateUrl: true,
                        focusTab: true,
                    });
                    return;
                }

                if (event.key === 'End') {
                    event.preventDefault();
                    setMode(buttons[buttons.length - 1].dataset.peImportMode, {
                        updateUrl: true,
                        focusTab: true,
                    });
                }
            });
        });

        const requestedMode = normalizeMode(
            modeFromHash()
            || hub.dataset.peDefaultImportMode
            || 'spreadsheet'
        );

        setMode(requestedMode, {
            updateUrl: false,
            focusTab: false,
        });

        return {
            hub,
            buttons,
            panels,
            setMode,
            getMode: () => currentMode,
        };
    };

    const hubInstances = Array.from(
        document.querySelectorAll('[data-pe-import-hub]')
    )
        .map(bindHub)
        .filter(Boolean);

    if (!hubInstances.length) return;

    const primary = hubInstances[0];

    window.PEImportHub = {
        setMode: (mode, options = {}) => primary.setMode(mode, options),
        getMode: () => primary.getMode(),
    };

    window.addEventListener('hashchange', () => {
        const hashMode = modeFromHash();
        if (!hashMode) return;

        primary.setMode(hashMode, {
            updateUrl: false,
            focusTab: false,
        });
    });
})();


/* =====================================================================
 * CENTRAL DE IMPORTAÇÕES — PDF DE PAGAMENTOS (2026-08-21)
 * Controlador integrado ao JS oficial do módulo. Não depende de arquivo
 * complementar para que Analisar PDF / Confirmar conciliação funcionem.
 * ===================================================================== */
(() => {
    'use strict';

    const hub = document.querySelector('[data-pe-import-hub]');
    if (!hub) return;

    const form = hub.querySelector('[data-pe-payment-pdf-form]');
    if (!form || form.dataset.pePaymentBound === '1') return;
    form.dataset.pePaymentBound = '1';

    const fileInput = form.querySelector('[data-pe-payment-pdf-file]');
    const textInput = form.querySelector('[data-pe-payment-pdf-text]');
    const competenceInput = form.querySelector('[data-pe-payment-competence]');
    const analyzeButton = form.querySelector('[data-pe-payment-analyze]');
    const applyButton = form.querySelector('[data-pe-payment-apply]');
    const analyzeLabel = form.querySelector('[data-pe-payment-analyze-label]');
    const applyLabel = form.querySelector('[data-pe-payment-apply-label]');
    const liveStatus = form.querySelector('[data-pe-payment-live-status]');

    const analysisBox = hub.querySelector('[data-pe-payment-analysis]');
    const metaNode = hub.querySelector('[data-pe-payment-meta]');
    const sourceNode = hub.querySelector('[data-pe-payment-source]');
    const kpisNode = hub.querySelector('[data-pe-payment-kpis]');
    const warningNode = hub.querySelector('[data-pe-payment-warning]');
    const rowsNode = hub.querySelector('[data-pe-payment-rows]');
    const limitNote = hub.querySelector('[data-pe-payment-limit-note]');

    if (!fileInput || !textInput || !competenceInput || !analyzeButton || !applyButton || !analysisBox) {
        return;
    }

    let analyzed = false;
    let lastAnalysis = null;
    let pdfJsPromise = null;

    const escapeHtml = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const money = value => new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value || 0));

    const formatCpf = value => {
        const original = String(value || '');
        const digits = original.replace(/\D+/g, '').padStart(11, '0');
        if (digits.length !== 11) return original || '—';
        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
    };

    const showToast = (message, type = 'info') => {
        if (window.SIGASFrontend && typeof window.SIGASFrontend.toast === 'function') {
            window.SIGASFrontend.toast(message, type);
            return;
        }

        const container = document.getElementById('frontendToastContainer');
        if (!container) return;

        const node = document.createElement('div');
        const bootstrapType = type === 'danger' ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
        node.className = `alert alert-${bootstrapType} shadow-sm`;
        node.textContent = message;
        container.appendChild(node);
        window.setTimeout(() => node.remove(), 6000);
    };

    const setLiveStatus = (message, tone = 'info', icon = 'bi-info-circle') => {
        if (!liveStatus) return;
        liveStatus.classList.remove('is-loading', 'is-success', 'is-warning', 'is-danger');
        if (tone && tone !== 'info') liveStatus.classList.add(`is-${tone}`);
        liveStatus.innerHTML = `<i class="bi ${escapeHtml(icon)}"></i><span>${message}</span>`;
    };

    const updateCsrf = token => {
        if (!token) return;
        document.querySelectorAll('input[name="_csrf"]').forEach(input => {
            input.value = token;
        });
    };

    const resetAnalysis = (message = null) => {
        analyzed = false;
        lastAnalysis = null;
        applyButton.disabled = true;
        applyButton.title = 'Analise o PDF antes de confirmar';
        if (applyLabel) applyLabel.textContent = 'Sincronizar lista final';
        analysisBox.hidden = true;
        if (rowsNode) rowsNode.innerHTML = '';
        if (kpisNode) kpisNode.innerHTML = '';
        if (metaNode) metaNode.textContent = '—';
        if (sourceNode) sourceNode.textContent = '—';
        if (warningNode) warningNode.textContent = '';
        if (limitNote) limitNote.textContent = '';
        if (message) setLiveStatus(message, 'info', 'bi-info-circle');
    };

    const loadScript = url => new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[data-pe-pdfjs-src="${CSS.escape(url)}"]`);
        if (existing) {
            if (window.pdfjsLib && typeof window.pdfjsLib.getDocument === 'function') {
                resolve(url);
                return;
            }
            existing.addEventListener('load', () => resolve(url), { once: true });
            existing.addEventListener('error', () => reject(new Error(`Falha ao carregar ${url}`)), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = url;
        script.async = true;
        script.crossOrigin = 'anonymous';
        script.dataset.pePdfjsSrc = url;
        script.addEventListener('load', () => resolve(url), { once: true });
        script.addEventListener('error', () => reject(new Error(`Falha ao carregar ${url}`)), { once: true });
        document.head.appendChild(script);
    });

    const loadPdfJs = async () => {
        if (window.pdfjsLib && typeof window.pdfjsLib.getDocument === 'function') {
            return window.pdfjsLib;
        }
        if (pdfJsPromise) return pdfJsPromise;

        pdfJsPromise = (async () => {
            const sources = [
                {
                    lib: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
                    worker: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
                },
                {
                    lib: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js',
                    worker: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js',
                },
            ];

            let lastError = null;
            for (const source of sources) {
                try {
                    setLiveStatus('O servidor não possui extrator local. Carregando o leitor seguro de PDF no navegador…', 'loading', 'bi-arrow-repeat');
                    await loadScript(source.lib);
                    if (window.pdfjsLib && typeof window.pdfjsLib.getDocument === 'function') {
                        window.pdfjsLib.GlobalWorkerOptions.workerSrc = source.worker;
                        return window.pdfjsLib;
                    }
                } catch (error) {
                    lastError = error;
                }
            }

            throw lastError || new Error('Não foi possível carregar o leitor de PDF. Verifique a conexão e tente novamente.');
        })();

        try {
            return await pdfJsPromise;
        } catch (error) {
            pdfJsPromise = null;
            throw error;
        }
    };

    const pageItemsToLines = (items, viewport) => {
        const tolerance = 2.4;
        const prepared = items
            .filter(item => item
                && typeof item.str === 'string'
                && item.str.trim() !== ''
                && item.transform
                && typeof item.transform.length === 'number'
                && item.transform.length >= 6)
            .map(item => {
                const rawX = Number(item.transform[4] || 0);
                const rawY = Number(item.transform[5] || 0);
                let x = rawX;
                let y = rawY;

                // O extrato do Banco do Brasil é gravado com rotação de página.
                // Normalizamos as coordenadas para o viewport já rotacionado antes
                // de remontar as linhas; assim N IDENT., CPF, NOME, VALOR etc.
                // permanecem na mesma linha visual.
                if (viewport && typeof viewport.convertToViewportPoint === 'function') {
                    const point = viewport.convertToViewportPoint(rawX, rawY);
                    x = Number(point[0] || 0);
                    y = Number(point[1] || 0);
                }

                return {
                    text: item.str.trim(),
                    x,
                    y,
                };
            })
            .sort((a, b) => Math.abs(a.y - b.y) > tolerance ? a.y - b.y : a.x - b.x);

        const lines = [];
        prepared.forEach(item => {
            let line = lines.length ? lines[lines.length - 1] : null;
            if (!line || Math.abs(line.y - item.y) > tolerance) {
                line = { y: item.y, items: [] };
                lines.push(line);
            }
            line.items.push(item);
        });

        return lines
            .map(line => line.items
                .sort((a, b) => a.x - b.x)
                .map(item => item.text)
                .join(' ')
                .replace(/\s+/g, ' ')
                .trim())
            .filter(Boolean);
    };

    const extractPdfText = async file => {
        const pdfjsLib = await loadPdfJs();
        const data = await file.arrayBuffer();
        const loadingTask = pdfjsLib.getDocument({ data });
        const pdf = await loadingTask.promise;

        if (pdf.numPages < 1 || pdf.numPages > 300) {
            await pdf.destroy();
            throw new Error('Quantidade de páginas do PDF fora do limite permitido.');
        }

        const pages = [];
        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
            if (analyzeLabel) analyzeLabel.textContent = `Lendo página ${pageNumber}/${pdf.numPages}`;
            setLiveStatus(`Extraindo texto do PDF no navegador — página ${pageNumber} de ${pdf.numPages}.`, 'loading', 'bi-arrow-repeat');
            const page = await pdf.getPage(pageNumber);
            const content = await page.getTextContent({ disableCombineTextItems: false });
            const viewport = page.getViewport({ scale: 1 });
            pages.push(pageItemsToLines(content.items, viewport).join('\n'));
            page.cleanup();
        }
        await pdf.destroy();
        return pages.join('\n');
    };

    const parseJsonResponse = async response => {
        const raw = await response.text();
        if (!raw) {
            throw new Error('O servidor respondeu sem conteúdo.');
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            console.error('Resposta não JSON do endpoint de PDF:', raw.slice(0, 1000));
            throw new Error('O servidor retornou uma resposta inválida. Recarregue a página e tente novamente.');
        }
    };

    const requestAnalysis = async browserText => {
        textInput.value = browserText || '';
        const data = new FormData(form);
        data.set('pe_action', 'analyze_payment_pdf');

        const endpoint = `${window.location.pathname}?ajax=payment_pdf_analyze`;
        const response = await fetch(endpoint, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });

        const payload = await parseJsonResponse(response);
        updateCsrf(payload.csrf_token);

        if (!response.ok || !payload.success) {
            const error = new Error(payload.message || 'Não foi possível analisar o PDF.');
            error.status = response.status;
            error.payload = payload;
            throw error;
        }
        return payload;
    };

    const shouldUseBrowserExtractor = error => {
        const message = String(error && error.message ? error.message : '').toLowerCase();
        return message.includes('navegador não enviou o texto')
            || message.includes('não possui extrator de pdf')
            || message.includes('servidor não possui extrator')
            || message.includes('clique em “analisar pdf”')
            || message.includes('nenhuma linha de pagamento foi reconhecida')
            || message.includes('extração do pdf ficou incompleta')
            || message.includes('foram reconhecidos');
    };

    const statusMeta = status => {
        const map = {
            novo_pagamento: ['Pronto', 'success'],
            atualizar_pagamento: ['Atualizar bolsa', 'primary'],
            ja_conciliado: ['Já conciliado', 'secondary'],
            conflito_financeiro: ['Conflito financeiro', 'danger'],
            nao_localizado: ['Não localizado', 'warning'],
            cpf_ambiguo: ['CPF ambíguo', 'danger'],
            cpf_invalido: ['CPF inválido', 'danger'],
        };
        return map[status] || [status || '—', 'secondary'];
    };

    const renderAnalysis = payload => {
        const analysis = payload.analysis || {};
        const meta = analysis.meta || {};
        const summary = analysis.summary || {};
        const rows = Array.isArray(analysis.rows) ? analysis.rows : [];
        lastAnalysis = analysis;

        if (!competenceInput.value && meta.competencia) {
            competenceInput.value = meta.competencia;
        }

        if (metaNode) {
            metaNode.textContent = `Convênio ${meta.convenio_numero || '—'} · Lista ${meta.lista_numero || '—'} (${meta.lista_nome || '—'}) · ${Number(meta.total_pagamentos || 0)} pagamentos · ${money(meta.valor_total)} · Estado: ${meta.estado_lista || '—'}`;
        }
        if (sourceNode) {
            sourceNode.textContent = payload.source === 'pdftotext-servidor'
                ? 'Texto validado no servidor'
                : 'Texto extraído no navegador';
        }

        const cards = [
            ['Lista oficial', summary.total],
            ['Já existentes', summary.candidatos_existentes],
            ['Recuperar / corrigir CPF', summary.candidatos_recuperar],
            ['Criar pelo Banco', summary.candidatos_criar],
            ['Retirar da base ativa', summary.candidatos_excluir],
            ['Base ativa após sincronizar', summary.ativos_apos_sincronizacao],
            ['Pagamentos novos', summary.prontos],
            ['Conflitos financeiros', summary.conflitos_financeiros],
        ];

        if (kpisNode) {
            kpisNode.innerHTML = cards.map(([label, value]) => (
                `<div class="pe-payment-kpi"><span>${escapeHtml(label)}</span><strong>${Number(value || 0)}</strong></div>`
            )).join('');
        }

        // A lista do Banco é a fonte oficial. Duplicidades/ambiguidades que
        // existem apenas na base local são resolvidas criando um cadastro
        // canônico do Banco e arquivando os registros locais conflitantes.
        // Somente CPF inválido no próprio PDF oficial bloqueia a aplicação.
        const unresolved = Number(summary.cpf_invalidos || 0);
        const localAmbiguities = Number(summary.ambiguos || 0);
        const conflicts = Number(summary.conflitos_financeiros || 0);
        const ready = Number(summary.prontos || 0);
        const updates = Number(summary.atualizar_pagamento || 0);
        const creates = Number(summary.candidatos_criar || 0);
        const recovers = Number(summary.candidatos_recuperar || 0);
        const excludes = Number(summary.candidatos_excluir || 0);
        const canApplyByState = String(meta.estado_lista || '').trim().toUpperCase() === 'PAGA';
        const hasChanges = ready + updates + creates + recovers + excludes > 0;

        if (warningNode) {
            warningNode.className = `alert ${unresolved || conflicts ? 'alert-warning' : 'alert-success'} mt-3 mb-0`;
            warningNode.innerHTML = canApplyByState
                ? (unresolved > 0
                    ? `<strong>Sincronização bloqueada.</strong> Existem ${unresolved} CPF(s) inválido(s) no próprio arquivo oficial. Confira o PDF antes de aplicar.`
                    : `<strong>Lista final pronta.</strong> O SIGAS ficará com ${Number(summary.ativos_apos_sincronizacao || 0)} candidato(s) ativos. Serão criados ${creates}, recuperados ${recovers} e retirados da base ativa ${excludes}. ${localAmbiguities > 0 ? `${localAmbiguities} conflito(s) local(is) de CPF serão resolvidos por cadastro canônico do Banco. ` : ''}Os retirados não serão apagados: permanecem no histórico.`)
                : `<strong>Sincronização bloqueada.</strong> A lista está como “${escapeHtml(meta.estado_lista || 'sem estado')}”. Somente listas PAGA podem ser aplicadas.`;
        }

        const priorities = {
            conflito_financeiro: 1,
            cpf_ambiguo: 2,
            cpf_invalido: 3,
            nao_localizado: 4,
            atualizar_pagamento: 5,
            novo_pagamento: 6,
            ja_conciliado: 7,
        };
        const ordered = rows.slice().sort((a, b) => (priorities[a.match_status] || 99) - (priorities[b.match_status] || 99));
        const displayRows = ordered.slice(0, 220);

        if (rowsNode) {
            rowsNode.innerHTML = displayRows.map(row => {
                const status = statusMeta(row.match_status);
                let sigas = '—';
                if (row.candidate_id) {
                    sigas = `<strong>#${Number(row.candidate_id)} · ${escapeHtml(row.candidate_name)}</strong><small>${escapeHtml(row.candidate_status || '')}${row.name_divergence ? ' · Divergência de nome' : ''}</small>`;
                } else if (row.membership_action === 'criar_candidato_banco') {
                    sigas = `<strong class="text-success-emphasis">Novo cadastro oficial</strong><small>Será criado com o CPF e o nome da lista do Banco.</small>`;
                } else if (row.suggestion && row.suggestion.id) {
                    sigas = `<span class="text-warning-emphasis">Sugestão por nome: #${Number(row.suggestion.id)} · ${escapeHtml(row.suggestion.nome)}</span><small>Não será vinculado automaticamente.</small>`;
                } else if (Array.isArray(row.ambiguous_candidates) && row.ambiguous_candidates.length) {
                    sigas = row.ambiguous_candidates.slice(0, 3).map(item => `#${Number(item.id)} ${escapeHtml(item.nome)}`).join('<br>');
                }

                return `<tr class="pe-payment-row pe-payment-row--${escapeHtml(row.match_status)}">
                    <td>${escapeHtml(row.n_ident)}</td>
                    <td>${escapeHtml(formatCpf(row.cpf || row.cpf_informado))}</td>
                    <td><strong>${escapeHtml(row.nome)}</strong>${row.name_divergence ? '<small class="text-warning-emphasis">Nome difere do SIGAS</small>' : ''}</td>
                    <td>${escapeHtml(money(row.valor))}</td>
                    <td class="pe-payment-sigas-cell">${sigas}</td>
                    <td><span class="badge text-bg-${status[1]}">${escapeHtml(status[0])}</span><small>${escapeHtml(row.match_message || '')}</small></td>
                </tr>`;
            }).join('');
        }

        if (limitNote) {
            limitNote.textContent = rows.length > displayRows.length
                ? `Exibindo ${displayRows.length} de ${rows.length} linhas, priorizando conflitos e pendências.`
                : `Exibindo ${rows.length} linhas.`;
        }

        analysisBox.hidden = false;
        analyzed = canApplyByState && unresolved === 0 && hasChanges;
        applyButton.disabled = !analyzed;
        applyButton.title = analyzed
            ? 'Sincronizar a base oficial e os pagamentos'
            : (canApplyByState ? 'Não há alterações financeiras novas para aplicar' : 'Somente listas PAGA podem ser conciliadas');

        if (analyzed) {
            setLiveStatus(`Lista oficial conferida. Base final: ${Number(summary.ativos_apos_sincronizacao || 0)} candidato(s); ${creates} novo(s), ${recovers} recuperado(s) e ${excludes} retirado(s) da base ativa.`, 'success', 'bi-check-circle');
        } else if (canApplyByState) {
            setLiveStatus('PDF conferido. Não existem novos pagamentos ou bolsas a atualizar nesta competência.', 'warning', 'bi-exclamation-circle');
        } else {
            setLiveStatus(`A lista está como “${escapeHtml(meta.estado_lista || 'sem estado')}”. A confirmação permanece bloqueada.`, 'danger', 'bi-x-circle');
        }

        if (window.PEImportHub && typeof window.PEImportHub.setMode === 'function') {
            window.PEImportHub.setMode('payment-pdf');
        }
    };

    fileInput.addEventListener('change', () => {
        textInput.value = '';
        resetAnalysis('Arquivo alterado. Clique em Analisar PDF para validar o novo extrato.');
    });

    competenceInput.addEventListener('change', () => {
        if (!lastAnalysis) return;
        textInput.value = '';
        resetAnalysis('A competência foi alterada. Analise o PDF novamente antes de confirmar.');
    });

    analyzeButton.addEventListener('click', async () => {
        const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        if (!file) {
            setLiveStatus('Selecione o PDF de pagamentos antes de analisar.', 'danger', 'bi-x-circle');
            showToast('Selecione o PDF de pagamentos.', 'danger');
            fileInput.focus();
            return;
        }
        if (!/\.pdf$/i.test(file.name) || file.size <= 0 || file.size > 12 * 1024 * 1024) {
            setLiveStatus('O arquivo deve ser um PDF válido com no máximo 12 MB.', 'danger', 'bi-x-circle');
            showToast('Envie um PDF válido com no máximo 12 MB.', 'danger');
            return;
        }

        resetAnalysis();
        analyzeButton.disabled = true;
        if (analyzeLabel) analyzeLabel.textContent = 'Analisando…';
        setLiveStatus('Enviando o extrato para validação do servidor…', 'loading', 'bi-arrow-repeat');

        try {
            let payload = null;
            try {
                // Primeiro tenta a extração do servidor. Quando pdftotext está disponível,
                // isso evita qualquer dependência externa no navegador.
                payload = await requestAnalysis('');
            } catch (serverError) {
                if (!shouldUseBrowserExtractor(serverError)) throw serverError;

                const text = await extractPdfText(file);
                if (!text || text.length < 100) {
                    throw new Error('O PDF não possui texto suficiente para leitura. Use o extrato digital original, não uma digitalização sem camada de texto.');
                }
                textInput.value = text;
                setLiveStatus('Texto extraído. Revalidando os  dados com o servidor…', 'loading', 'bi-arrow-repeat');
                payload = await requestAnalysis(text);
            }

            renderAnalysis(payload);
            showToast('PDF analisado. Confira a prévia antes de confirmar.', 'success');
        } catch (error) {
            resetAnalysis();
            const message = error instanceof Error ? error.message : 'Não foi possível analisar o PDF.';
            setLiveStatus(escapeHtml(message), 'danger', 'bi-x-circle');
            showToast(message, 'danger');
            console.error('Falha na análise do PDF:', error);
        } finally {
            analyzeButton.disabled = false;
            if (analyzeLabel) analyzeLabel.textContent = 'Analisar PDF';
        }
    });

    form.addEventListener('submit', event => {
        if (!analyzed || !lastAnalysis) {
            event.preventDefault();
            setLiveStatus('Analise o PDF e aguarde a liberação da confirmação antes de continuar.', 'danger', 'bi-x-circle');
            showToast('Analise o PDF antes de confirmar a conciliação.', 'danger');
            return;
        }

        if (!competenceInput.value) {
            event.preventDefault();
            setLiveStatus('Informe a competência da bolsa antes de confirmar.', 'danger', 'bi-x-circle');
            competenceInput.focus();
            return;
        }

        const summary = lastAnalysis.summary || {};
        const confirmation = `CONFIRMAR SINCRONIZAÇÃO DA LISTA FINAL?\n\nA base ativa ficará com ${Number(summary.ativos_apos_sincronizacao || 0)} candidato(s).\n${Number(summary.candidatos_criar || 0)} cadastro(s) serão criados pelo Banco.\n${Number(summary.candidatos_recuperar || 0)} cadastro(s) terão o CPF oficial recuperado/corrigido.\n${Number(summary.candidatos_excluir || 0)} cadastro(s) que não constam no Banco sairão da base ativa, mas NÃO serão apagados do histórico.\n\nDeseja continuar?`;
        if (!window.confirm(confirmation)) {
            event.preventDefault();
            return;
        }

        applyButton.disabled = true;
        if (applyLabel) applyLabel.textContent = 'Sincronizando…';
        setLiveStatus('Sincronização oficial em andamento. Não feche esta página até a conclusão.', 'loading', 'bi-arrow-repeat');
    });
})();
