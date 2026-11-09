'use strict';

(() => {
    const root = document.querySelector('[data-governance-sectors]');
    const modalElement = document.querySelector('#governanceSectorModal');
    const statusModalElement = document.querySelector('#governanceSectorStatusModal');
    const form = modalElement?.querySelector('[data-governance-sector-form]');
    const statusForm = statusModalElement?.querySelector('[data-governance-sector-status-form]');

    if (!root || !modalElement || !statusModalElement || !form || !statusForm || typeof bootstrap === 'undefined') {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement, { backdrop: 'static', keyboard: true });
    const statusModal = bootstrap.Modal.getOrCreateInstance(statusModalElement, { backdrop: 'static', keyboard: true });

    const actionField = form.querySelector('[data-sector-action]');
    const idField = form.querySelector('[data-sector-id]');
    const nameField = form.querySelector('[data-sector-name]');
    const slugField = form.querySelector('[data-sector-slug]');
    const slugGroup = form.querySelector('[data-sector-slug-group]');
    const descriptionField = form.querySelector('[data-sector-description]');
    const reasonField = form.querySelector('[data-sector-reason]');
    const moduleFields = [...form.querySelectorAll('[data-sector-module]')];
    const title = modalElement.querySelector('[data-sector-modal-title]');
    const submitLabel = modalElement.querySelector('[data-sector-submit-label]');
    const alertBox = modalElement.querySelector('[data-governance-sector-alert]');

    const statusAction = statusForm.querySelector('[data-sector-status-action]');
    const statusId = statusForm.querySelector('[data-sector-status-id]');
    const statusTitle = statusModalElement.querySelector('[data-sector-status-title]');
    const statusNote = statusModalElement.querySelector('[data-sector-status-note]');
    const statusSubmit = statusModalElement.querySelector('[data-sector-status-submit]');
    const statusAlert = statusModalElement.querySelector('[data-governance-sector-status-alert]');

    const showMessage = (box, message, type = 'danger') => {
        if (!box) return;
        box.className = `alert alert-${type}`;
        box.textContent = message;
        box.classList.remove('d-none');
    };

    const clearMessage = box => {
        if (!box) return;
        box.className = 'alert d-none';
        box.textContent = '';
    };

    const syncCsrf = token => {
        if (typeof token !== 'string' || token === '') return;
        document.querySelectorAll('input[name="_csrf"]').forEach(input => {
            input.value = token;
        });
    };

    const selectedModules = raw => new Set(
        String(raw || '')
            .split(',')
            .map(value => value.trim())
            .filter(Boolean)
    );

    const resetForCreate = () => {
        form.reset();
        clearMessage(alertBox);
        if (actionField) actionField.value = 'create';
        if (idField) idField.value = '';
        if (slugField) slugField.value = '';
        slugGroup?.classList.add('d-none');
        if (title) title.textContent = 'Novo setor';
        if (submitLabel) submitLabel.textContent = 'Criar setor';
        moduleFields.forEach(field => { field.checked = false; });
    };

    root.querySelectorAll('[data-sector-new]').forEach(button => {
        button.addEventListener('click', resetForCreate);
    });

    root.querySelectorAll('[data-sector-edit]').forEach(button => {
        button.addEventListener('click', () => {
            clearMessage(alertBox);
            form.reset();
            if (actionField) actionField.value = 'update';
            if (idField) idField.value = button.dataset.id || '';
            if (nameField) nameField.value = button.dataset.name || '';
            if (slugField) slugField.value = button.dataset.slug || '';
            if (descriptionField) descriptionField.value = button.dataset.description || '';
            if (reasonField) reasonField.value = '';
            slugGroup?.classList.remove('d-none');
            if (title) title.textContent = 'Editar setor';
            if (submitLabel) submitLabel.textContent = 'Salvar alterações';

            const allowed = selectedModules(button.dataset.modules);
            moduleFields.forEach(field => {
                field.checked = allowed.has(field.value);
            });
        });
    });

    const setBusy = (targetForm, busy) => {
        targetForm.querySelectorAll('button, input, textarea, select').forEach(element => {
            if (element.type === 'hidden') return;
            element.disabled = busy;
        });
        targetForm.setAttribute('aria-busy', String(busy));
    };

    const request = async formData => {
        const response = await fetch('api/governanca-acessos/setor-acao.php', {
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

    form.addEventListener('submit', async event => {
        event.preventDefault();
        clearMessage(alertBox);

        if (!form.reportValidity()) {
            return;
        }

        setBusy(form, true);
        showMessage(alertBox, 'Salvando setor...', 'info');

        try {
            const payload = await request(new FormData(form));
            if (!payload) return;
            showMessage(alertBox, payload.message || 'Setor salvo com sucesso.', 'success');
            window.setTimeout(() => window.location.assign('governanca-acessos/setores.php'), 700);
        } catch (error) {
            showMessage(alertBox, error instanceof Error ? error.message : 'Não foi possível salvar o setor.', 'danger');
            setBusy(form, false);
        }
    });

    root.querySelectorAll('[data-sector-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            statusForm.reset();
            clearMessage(statusAlert);

            const action = button.dataset.action || '';
            const name = button.dataset.name || 'Setor';
            const users = Number.parseInt(button.dataset.users || '0', 10) || 0;

            if (statusAction) statusAction.value = action;
            if (statusId) statusId.value = button.dataset.id || '';
            if (statusTitle) statusTitle.textContent = action === 'deactivate' ? `Inativar ${name}` : `Ativar ${name}`;
            if (statusSubmit) {
                statusSubmit.textContent = action === 'deactivate' ? 'Confirmar inativação' : 'Confirmar ativação';
                statusSubmit.className = action === 'deactivate' ? 'btn btn-danger' : 'btn btn-success';
            }
            if (statusNote) {
                statusNote.innerHTML = action === 'deactivate'
                    ? `<i class="bi bi-exclamation-triangle"></i><div><strong>${users} usuário(s) vinculado(s).</strong><br>As sessões ativas serão encerradas e usuários operacionais precisarão ser transferidos para um setor ativo.</div>`
                    : '<i class="bi bi-check-circle"></i><div>O setor voltará a aceitar operação conforme seus módulos e permissões configurados.</div>';
            }
        });
    });

    statusForm.addEventListener('submit', async event => {
        event.preventDefault();
        clearMessage(statusAlert);

        if (!statusForm.reportValidity()) {
            return;
        }

        setBusy(statusForm, true);
        showMessage(statusAlert, 'Aplicando alteração...', 'info');

        try {
            const payload = await request(new FormData(statusForm));
            if (!payload) return;
            showMessage(statusAlert, payload.message || 'Situação alterada com sucesso.', 'success');
            window.setTimeout(() => window.location.assign('governanca-acessos/setores.php'), 700);
        } catch (error) {
            showMessage(statusAlert, error instanceof Error ? error.message : 'Não foi possível alterar o setor.', 'danger');
            setBusy(statusForm, false);
        }
    });

    modalElement.addEventListener('hidden.bs.modal', resetForCreate);

    if (modalElement.dataset.autoOpen === '1') {
        resetForCreate();
        modal.show();
    }
})();
