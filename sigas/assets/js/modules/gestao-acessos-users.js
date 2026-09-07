'use strict';

(() => {
    const PAGE_SIZE = 20;

    const initializePagination = () => {
        document.querySelectorAll('[data-frontend-dataset]').forEach(dataset => {
            const desktopRows = [...dataset.querySelectorAll('.frontend-desktop-table [data-filter-row]')];
            const mobileRows = [...dataset.querySelectorAll('.frontend-mobile-records [data-filter-row]')];
            const canonicalRows = desktopRows.length > 0 ? desktopRows : mobileRows;
            const pagination = dataset.querySelector('.frontend-pagination');
            const summary = pagination?.querySelector('span');
            const nav = pagination?.querySelector('nav');
            const noResults = dataset.querySelector('[data-no-results]');
            const filterForm = document.querySelector('[data-frontend-filter]');

            if (canonicalRows.length === 0 || !pagination || !summary || !nav) {
                return;
            }

            let currentPage = 1;

            const filterCriteria = () => ({
                search: (filterForm?.querySelector('[data-filter-search]')?.value || '')
                    .trim()
                    .toLocaleLowerCase('pt-BR'),
                selections: filterForm
                    ? [...filterForm.querySelectorAll('[data-filter-select]')]
                        .map(field => field.value)
                        .filter(Boolean)
                    : []
            });

            const matchingIndices = () => {
                const { search, selections } = filterCriteria();
                const matches = [];

                canonicalRows.forEach((row, index) => {
                    const content = (row.dataset.search || row.textContent || '')
                        .toLocaleLowerCase('pt-BR');
                    const matched = (!search || content.includes(search))
                        && selections.every(value => content.includes(value));

                    if (matched) {
                        matches.push(index);
                    }
                });

                return matches;
            };

            const pageButtons = totalPages => {
                if (totalPages <= 0) {
                    return '';
                }

                const pages = new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1]);
                const ordered = [...pages]
                    .filter(page => page >= 1 && page <= totalPages)
                    .sort((a, b) => a - b);
                let html = '';
                let previous = 0;

                ordered.forEach(page => {
                    if (previous > 0 && page - previous > 1) {
                        html += '<span class="px-1 align-self-center" aria-hidden="true">…</span>';
                    }

                    const active = page === currentPage;
                    html += `<button class="btn ${active ? 'btn-primary' : 'btn-light'} btn-sm" type="button" data-governance-page="${page}"${active ? ' aria-current="page"' : ''}>${page}</button>`;
                    previous = page;
                });

                return html;
            };

            const render = () => {
                const matches = matchingIndices();
                const total = matches.length;
                const totalPages = total > 0 ? Math.ceil(total / PAGE_SIZE) : 0;

                if (totalPages === 0) {
                    currentPage = 1;
                } else if (currentPage > totalPages) {
                    currentPage = totalPages;
                }

                const start = total > 0 ? (currentPage - 1) * PAGE_SIZE : 0;
                const end = total > 0 ? Math.min(start + PAGE_SIZE, total) : 0;
                const visibleIndices = new Set(matches.slice(start, end));

                desktopRows.forEach((row, index) => {
                    row.hidden = !visibleIndices.has(index);
                });
                mobileRows.forEach((row, index) => {
                    row.hidden = !visibleIndices.has(index);
                });

                if (noResults) {
                    noResults.hidden = total > 0;
                }

                if (total === 0) {
                    summary.textContent = 'Exibindo 0 de 0 registros';
                } else {
                    summary.textContent = `Exibindo ${start + 1}–${end} de ${total} registro(s) · Página ${currentPage} de ${totalPages}`;
                }

                nav.setAttribute('aria-label', 'Paginação');
                nav.innerHTML = `
                    <button class="btn btn-light btn-sm" type="button" data-governance-page-action="previous" ${currentPage <= 1 || totalPages === 0 ? 'disabled' : ''}>Anterior</button>
                    ${pageButtons(totalPages)}
                    <button class="btn btn-light btn-sm" type="button" data-governance-page-action="next" ${currentPage >= totalPages || totalPages === 0 ? 'disabled' : ''}>Próxima</button>
                `;
            };

            nav.addEventListener('click', event => {
                const button = event.target.closest('button');
                if (!button || button.disabled) {
                    return;
                }

                const directPage = Number.parseInt(button.dataset.governancePage || '', 10);
                if (Number.isInteger(directPage) && directPage > 0) {
                    currentPage = directPage;
                    render();
                    return;
                }

                if (button.dataset.governancePageAction === 'previous') {
                    currentPage = Math.max(1, currentPage - 1);
                    render();
                } else if (button.dataset.governancePageAction === 'next') {
                    currentPage += 1;
                    render();
                }
            });

            if (filterForm) {
                filterForm.addEventListener('input', () => {
                    window.setTimeout(() => {
                        currentPage = 1;
                        render();
                    }, 0);
                });
                filterForm.addEventListener('reset', () => {
                    window.setTimeout(() => {
                        currentPage = 1;
                        render();
                    }, 0);
                });
            }

            render();
        });
    };

    initializePagination();

    const modalElement = document.querySelector('#governanceUserAdminModal');
    const form = modalElement?.querySelector('[data-governance-user-form]');
    const alertBox = modalElement?.querySelector('[data-governance-user-alert]');

    if (!modalElement || !form || typeof bootstrap === 'undefined') {
        return;
    }

    const userIdField = form.querySelector('input[name="user_id"]');
    const selectedUserId = Number.parseInt(userIdField?.value || '', 10);
    const feedbackStorageKey = Number.isInteger(selectedUserId) && selectedUserId > 0
        ? `sigas-governance-feedback-${selectedUserId}`
        : 'sigas-governance-feedback';

    if (modalElement.dataset.autoOpen === '1') {
        bootstrap.Modal.getOrCreateInstance(modalElement, {
            backdrop: 'static',
            keyboard: true
        }).show();
    }

    const showMessage = (message, type = 'danger') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} ga-user-admin-alert`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const storedFeedback = window.sessionStorage.getItem(feedbackStorageKey);
    if (storedFeedback) {
        window.sessionStorage.removeItem(feedbackStorageKey);
        showMessage(storedFeedback, 'success');
    }

    const setBusy = busy => {
        form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.disabled = busy || button.dataset.permanentlyDisabled === '1';
        });
        form.setAttribute('aria-busy', String(busy));
    };

    form.querySelectorAll('button[type="submit"][disabled]').forEach(button => {
        button.dataset.permanentlyDisabled = '1';
    });

    const accessModuleBlocks = () => [...form.querySelectorAll('details.border.rounded-3.bg-white')]
        .filter(block => block.querySelector('select[name^="module_override["]'));

    const sectorIsBlocked = block => {
        const summary = block.querySelector('summary');
        return (summary?.textContent || '').includes('Setor: bloqueado');
    };

    const moduleHasEffectiveAccess = block => {
        const summary = block.querySelector('summary');
        return (summary?.textContent || '').includes('Acesso efetivo')
            && !(summary?.textContent || '').includes('Sem acesso efetivo');
    };

    const updateModuleGuard = block => {
        const moduleSelect = block.querySelector('select[name^="module_override["]');
        const content = moduleSelect?.closest('.border-top');
        if (!moduleSelect || !content) return;

        let warning = content.querySelector('[data-module-barrier-warning]');
        const baseBlocked = sectorIsBlocked(block);
        const inheritedBlock = baseBlocked && moduleSelect.value === 'inherit';

        if (!warning) {
            warning = document.createElement('div');
            warning.dataset.moduleBarrierWarning = '1';
            warning.className = 'alert alert-warning py-2 px-3 small mb-3';
            const firstRow = content.querySelector('.row');
            if (firstRow) {
                firstRow.insertAdjacentElement('afterend', warning);
            } else {
                content.prepend(warning);
            }
        }

        if (inheritedBlock) {
            warning.textContent = 'Este setor não possui acesso a este módulo. Para liberar somente este usuário, selecione “Liberar para esta pessoa” em Acesso ao módulo e salve novamente.';
            warning.classList.remove('d-none');
        } else if (baseBlocked && moduleSelect.value === 'allow') {
            warning.textContent = 'A exceção individual de módulo será aplicada ao salvar. As ações internas continuarão obedecendo ao nível ou às exceções individuais configuradas abaixo.';
            warning.classList.remove('d-none');
            warning.classList.remove('alert-warning');
            warning.classList.add('alert-info');
        } else {
            warning.classList.add('d-none');
        }

        if (inheritedBlock || !moduleHasEffectiveAccess(block)) {
            block.querySelectorAll('table tbody tr').forEach(row => {
                const resultBadge = row.querySelector('td:last-child .badge');
                if (!resultBadge) return;
                resultBadge.textContent = 'Negado';
                resultBadge.classList.remove('text-bg-success');
                resultBadge.classList.add('text-bg-secondary');
                resultBadge.title = 'O módulo não possui acesso efetivo para este usuário.';
            });
        }
    };

    accessModuleBlocks().forEach(block => {
        const moduleSelect = block.querySelector('select[name^="module_override["]');
        updateModuleGuard(block);
        moduleSelect?.addEventListener('change', () => updateModuleGuard(block));
    });

    const validateModuleBarriers = action => {
        if (action !== 'save_overrides') {
            return true;
        }

        for (const block of accessModuleBlocks()) {
            const moduleSelect = block.querySelector('select[name^="module_override["]');
            if (!moduleSelect || !sectorIsBlocked(block) || moduleSelect.value !== 'inherit') {
                continue;
            }

            const hasPositivePermissionOverride = [...block.querySelectorAll('select[name^="permission_override["]')]
                .some(select => select.value === 'allow');

            if (!hasPositivePermissionOverride) {
                continue;
            }

            const moduleName = (block.querySelector('summary .fw-semibold')?.textContent || 'Este módulo').trim();
            showMessage(`${moduleName} está bloqueado pelo setor. Para liberar uma ação interna, primeiro escolha “Liberar para esta pessoa” em Acesso ao módulo.`, 'warning');
            block.open = true;
            moduleSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => moduleSelect.focus(), 300);
            return false;
        }

        return true;
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submitter = event.submitter;
        const action = submitter?.value || '';

        if (!action) return;

        if (!form.reportValidity()) {
            return;
        }

        if (!validateModuleBarriers(action)) {
            return;
        }

        const confirmation = submitter?.dataset.confirmAction || '';
        if (confirmation && !window.confirm(confirmation)) {
            return;
        }

        const formData = new FormData(form);
        formData.set('acao', action);
        setBusy(true);
        showMessage('Processando ação administrativa...', 'info');

        try {
            const response = await fetch('api/governanca-acessos/usuario-acao.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                credentials: 'same-origin'
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (_) {
                payload = {};
            }

            if (typeof payload.csrf === 'string' && payload.csrf !== '') {
                const csrf = form.querySelector('input[name="_csrf"]');
                if (csrf) csrf.value = payload.csrf;
            }

            if (response.status === 401) {
                showMessage(payload.message || 'Sua sessão expirou. Redirecionando para o login.', 'warning');
                window.setTimeout(() => window.location.assign('index.php'), 600);
                return;
            }

            if (!response.ok || payload.ok !== true) {
                showMessage(payload.message || 'Não foi possível concluir a ação.', 'danger');
                setBusy(false);
                return;
            }

            const successMessage = payload.message || 'Alterações salvas com sucesso.';
            showMessage(successMessage, 'success');
            window.sessionStorage.setItem(feedbackStorageKey, successMessage);

            const destination = Number.isInteger(selectedUserId) && selectedUserId > 0
                ? `governanca-acessos/usuarios.php?usuario=${encodeURIComponent(String(selectedUserId))}`
                : 'governanca-acessos/usuarios.php';

            window.setTimeout(() => window.location.assign(destination), 900);
        } catch (_) {
            showMessage('Falha de comunicação com o servidor. Tente novamente.', 'danger');
            setBusy(false);
        }
    });
})();
