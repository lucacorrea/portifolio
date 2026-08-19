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
