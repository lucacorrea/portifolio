'use strict';

(() => {
    const root = document.querySelector('[data-governance-levels]');
    const modalElement = document.querySelector('#governanceLevelModal');
    const form = modalElement?.querySelector('[data-governance-level-form]');
    const alertBox = modalElement?.querySelector('[data-governance-level-alert]');
    const statusModalElement = document.querySelector('#governanceLevelStatusModal');
    const statusForm = statusModalElement?.querySelector('[data-governance-level-status-form]');
    const statusAlert = statusModalElement?.querySelector('[data-governance-level-status-alert]');

    if (!root || !modalElement || !form || !statusModalElement || !statusForm || typeof bootstrap === 'undefined') {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement, { backdrop: 'static', keyboard: true });
    const statusModal = bootstrap.Modal.getOrCreateInstance(statusModalElement, { backdrop: 'static', keyboard: true });

    const actionField = form.querySelector('[data-level-action]');
    const idField = form.querySelector('[data-level-id]');
    const nameField = form.querySelector('[data-level-name]');
    const slugField = form.querySelector('[data-level-slug]');
    const slugGroup = form.querySelector('[data-level-slug-group]');
    const descriptionField = form.querySelector('[data-level-description]');
    const priorityField = form.querySelector('[data-level-priority]');
    const reasonField = form.querySelector('[data-level-reason]');
    const title = modalElement.querySelector('[data-level-modal-title]');
    const submitLabel = modalElement.querySelector('[data-level-submit-label]');

    const statusAction = statusForm.querySelector('[data-level-status-action]');
    const statusId = statusForm.querySelector('[data-level-status-id]');
    const statusTitle = statusModalElement.querySelector('[data-level-status-title]');
    const statusNote = statusModalElement.querySelector('[data-level-status-note]');
    const statusSubmit = statusModalElement.querySelector('[data-level-status-submit]');

    const showMessage = (target, message, type = 'danger') => {
        if (!target) return;
        target.className = `alert alert-${type}`;
        target.textContent = message;
        target.classList.remove('d-none');
    };

    const clearMessage = target => {
        if (!target) return;
        target.className = 'alert d-none';
        target.textContent = '';
    };

    const syncCsrf = token => {
        if (typeof token !== 'string' || token === '') return;
        document.querySelectorAll('input[name="_csrf"]').forEach(field => {
            if (field.closest('#governanceLevelModal') || field.closest('#governanceLevelStatusModal')) {
                field.value = token;
            }
        });
    };

    const request = async formData => {
        const response = await fetch('api/governanca-acessos/nivel-acao.php', {
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

        syncCsrf(payload.csrf);

        if (response.status === 401) {
            window.location.assign('index.php');
            return null;
        }

        if (!response.ok || payload.ok !== true) {
            throw new Error(payload.message || 'Não foi possível concluir a ação.');
        }

        return payload;
    };

    const resetCreate = () => {
        form.reset();
        clearMessage(alertBox);
        if (actionField) actionField.value = 'create';
        if (idField) idField.value = '';
        if (slugField) slugField.value = '';
        slugGroup?.classList.add('d-none');
        if (title) title.textContent = 'Novo nível';
        if (submitLabel) submitLabel.textContent = 'Criar nível';
        if (priorityField) priorityField.value = '50';
    };

    root.querySelectorAll('[data-level-new]').forEach(button => {
        button.addEventListener('click', resetCreate);
    });

    root.querySelectorAll('[data-level-edit]').forEach(button => {
        button.addEventListener('click', () => {
            form.reset();
            clearMessage(alertBox);
            if (actionField) actionField.value = 'update';
            if (idField) idField.value = button.dataset.id || '';
            if (nameField) nameField.value = button.dataset.name || '';
            if (slugField) slugField.value = button.dataset.slug || '';
            if (descriptionField) descriptionField.value = button.dataset.description || '';
            if (priorityField) priorityField.value = button.dataset.priority || '';
            if (reasonField) reasonField.value = '';
            slugGroup?.classList.remove('d-none');
            if (title) title.textContent = 'Editar nível';
            if (submitLabel) submitLabel.textContent = 'Salvar alterações';
        });
    });

    const setFormBusy = busy => {
        form.querySelectorAll('button, input, textarea').forEach(element => {
            if (element.type === 'hidden' || element.readOnly) return;
            element.disabled = busy;
        });
        form.setAttribute('aria-busy', String(busy));
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        clearMessage(alertBox);
        if (!form.reportValidity()) return;

        setFormBusy(true);
        showMessage(alertBox, 'Salvando nível de acesso...', 'info');

        try {
            const payload = await request(new FormData(form));
            if (!payload) return;
            showMessage(alertBox, payload.message || 'Nível salvo com sucesso.', 'success');
            window.setTimeout(() => window.location.assign('governanca-acessos/perfis.php'), 700);
        } catch (error) {
            showMessage(alertBox, error instanceof Error ? error.message : 'Não foi possível salvar o nível.', 'danger');
            setFormBusy(false);
        }
    });

    root.querySelectorAll('[data-level-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            statusForm.reset();
            clearMessage(statusAlert);
            const action = button.dataset.action || '';
            const name = button.dataset.name || 'Nível';
            const users = Number.parseInt(button.dataset.users || '0', 10) || 0;

            if (statusAction) statusAction.value = action;
            if (statusId) statusId.value = button.dataset.id || '';
            if (statusTitle) statusTitle.textContent = action === 'deactivate' ? `Inativar ${name}` : `Ativar ${name}`;
            if (statusSubmit) {
                statusSubmit.textContent = action === 'deactivate' ? 'Confirmar inativação' : 'Confirmar ativação';
                statusSubmit.className = `btn ${action === 'deactivate' ? 'btn-danger' : 'btn-success'}`;
            }
            if (statusNote) {
                if (action === 'deactivate') {
                    statusNote.innerHTML = `<i class="bi bi-exclamation-triangle"></i><div><strong>Impacto imediato</strong><span>${users > 0 ? `${users} usuário(s) estão vinculados a este nível. As sessões serão encerradas e eles precisarão receber outro nível para operar.` : 'O nível deixará de aparecer para novas atribuições.'}</span></div>`;
                } else {
                    statusNote.innerHTML = '<i class="bi bi-check-circle"></i><div><strong>Reativação</strong><span>O nível voltará a ficar disponível para novas atribuições. As permissões existentes serão preservadas.</span></div>';
                }
            }
        });
    });

    const setStatusBusy = busy => {
        statusForm.querySelectorAll('button, textarea').forEach(element => {
            element.disabled = busy;
        });
        statusForm.setAttribute('aria-busy', String(busy));
    };

    statusForm.addEventListener('submit', async event => {
        event.preventDefault();
        clearMessage(statusAlert);
        if (!statusForm.reportValidity()) return;

        setStatusBusy(true);
        showMessage(statusAlert, 'Aplicando alteração...', 'info');

        try {
            const payload = await request(new FormData(statusForm));
            if (!payload) return;
            const extra = Number(payload.revoked_sessions || 0) > 0
                ? ` ${payload.revoked_sessions} sessão(ões) encerrada(s).`
                : '';
            showMessage(statusAlert, (payload.message || 'Situação atualizada.') + extra, 'success');
            window.setTimeout(() => window.location.assign('governanca-acessos/perfis.php'), 850);
        } catch (error) {
            showMessage(statusAlert, error instanceof Error ? error.message : 'Não foi possível alterar a situação.', 'danger');
            setStatusBusy(false);
        }
    });

    modalElement.addEventListener('hidden.bs.modal', resetCreate);
    statusModalElement.addEventListener('hidden.bs.modal', () => {
        statusForm.reset();
        clearMessage(statusAlert);
        setStatusBusy(false);
    });

    if (modalElement.dataset.autoOpen === '1') {
        resetCreate();
        modal.show();
    }
})();
