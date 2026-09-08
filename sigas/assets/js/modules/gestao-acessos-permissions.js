'use strict';

(() => {
    const root = document.querySelector('[data-governance-permissions]');
    const modalElement = document.querySelector('#governancePermissionModal');
    const form = modalElement?.querySelector('[data-governance-permission-form]');
    const alertBox = modalElement?.querySelector('[data-governance-permission-alert]');

    if (!root || !modalElement || !form || typeof bootstrap === 'undefined') {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
        backdrop: 'static',
        keyboard: true
    });

    const actionField = form.querySelector('[data-permission-action]');
    const idField = form.querySelector('[data-permission-id]');
    const nameField = form.querySelector('[data-permission-name]');
    const moduleField = form.querySelector('[data-permission-module]');
    const actionKeyField = form.querySelector('[data-permission-action-key]');
    const descriptionField = form.querySelector('[data-permission-description]');
    const reasonField = form.querySelector('[data-permission-reason]');
    const slugPreview = form.querySelector('[data-permission-slug-preview]');
    const title = modalElement.querySelector('[data-permission-modal-title]');
    const submitLabel = modalElement.querySelector('[data-permission-submit-label]');

    const csrfField = () => form.querySelector('input[name="_csrf"]');

    const showMessage = (message, type = 'danger') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const clearMessage = () => {
        if (!alertBox) return;
        alertBox.className = 'alert d-none';
        alertBox.textContent = '';
    };

    const currentCsrf = () => csrfField()?.value || '';

    const updateSlugPreview = () => {
        if (!slugPreview) return;
        const module = (moduleField?.value || '').trim();
        const action = (actionKeyField?.value || '').trim().toLowerCase();
        slugPreview.textContent = module && action ? `${module}.${action}` : 'selecione_modulo.acao';
    };

    const setImmutableFields = immutable => {
        if (moduleField) moduleField.disabled = immutable;
        if (actionKeyField) actionKeyField.disabled = immutable;
    };

    const resetForCreate = () => {
        const csrf = currentCsrf();
        form.reset();
        if (csrfField()) csrfField().value = csrf;
        clearMessage();
        setImmutableFields(false);
        if (actionField) actionField.value = 'create';
        if (idField) idField.value = '';
        if (title) title.textContent = 'Nova permissão';
        if (submitLabel) submitLabel.textContent = 'Criar permissão';
        updateSlugPreview();
    };

    root.querySelectorAll('[data-permission-new]').forEach(button => {
        button.addEventListener('click', resetForCreate);
    });

    root.querySelectorAll('[data-permission-edit]').forEach(button => {
        button.addEventListener('click', () => {
            const csrf = currentCsrf();
            form.reset();
            if (csrfField()) csrfField().value = csrf;
            clearMessage();
            if (actionField) actionField.value = 'update';
            if (idField) idField.value = button.dataset.id || '';
            if (nameField) nameField.value = button.dataset.name || '';
            if (moduleField) moduleField.value = button.dataset.module || '';
            if (descriptionField) descriptionField.value = button.dataset.description || '';
            if (reasonField) reasonField.value = '';

            const slug = button.dataset.slug || '';
            const dot = slug.indexOf('.');
            if (actionKeyField) actionKeyField.value = dot >= 0 ? slug.slice(dot + 1) : slug;
            if (slugPreview) slugPreview.textContent = slug || '—';
            setImmutableFields(true);
            if (title) title.textContent = 'Editar permissão';
            if (submitLabel) submitLabel.textContent = 'Salvar alterações';
        });
    });

    moduleField?.addEventListener('change', updateSlugPreview);
    actionKeyField?.addEventListener('input', () => {
        const normalized = actionKeyField.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
        if (normalized !== actionKeyField.value) {
            actionKeyField.value = normalized;
        }
        updateSlugPreview();
    });

    const setBusy = busy => {
        form.querySelectorAll('button, input, select, textarea').forEach(element => {
            if (element.type === 'hidden') return;
            if (element === moduleField || element === actionKeyField) {
                const editing = actionField?.value === 'update';
                element.disabled = busy || editing;
                return;
            }
            element.disabled = busy;
        });
        form.setAttribute('aria-busy', String(busy));
    };

    const request = async formData => {
        const response = await fetch('api/governanca-acessos/permissao-acao.php', {
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

        if (typeof payload.csrf === 'string' && payload.csrf !== '' && csrfField()) {
            csrfField().value = payload.csrf;
        }

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
        clearMessage();

        if (!form.reportValidity()) {
            return;
        }

        setBusy(true);
        showMessage(actionField?.value === 'create' ? 'Criando permissão...' : 'Salvando permissão...', 'info');

        try {
            const payload = await request(new FormData(form));
            if (!payload) return;
            showMessage(payload.message || 'Permissão salva com sucesso.', 'success');
            window.setTimeout(() => window.location.assign('governanca-acessos/permissoes.php'), 700);
        } catch (error) {
            showMessage(error instanceof Error ? error.message : 'Não foi possível salvar a permissão.', 'danger');
            setBusy(false);
        }
    });

    root.querySelectorAll('[data-permission-toggle]').forEach(button => {
        button.addEventListener('click', async () => {
            const action = button.dataset.action || '';
            const name = button.dataset.name || 'esta permissão';
            const slug = button.dataset.slug || '';
            const isView = button.dataset.isView === '1';

            let confirmation = action === 'deactivate'
                ? `Inativar ${name}? Os vínculos com níveis serão preservados, mas deixarão de produzir efeito.`
                : `Ativar ${name}? Os vínculos existentes voltarão a produzir efeito imediatamente.`;

            if (action === 'deactivate' && isView) {
                confirmation += '\n\nATENÇÃO: esta é uma permissão de visualização/entrada. A inativação pode retirar o acesso ao módulo de todos os níveis vinculados.';
            }

            if (!window.confirm(confirmation)) {
                return;
            }

            const reason = window.prompt(`Justificativa obrigatória para ${action === 'deactivate' ? 'inativar' : 'ativar'} ${slug}:`);
            if (reason === null) {
                return;
            }
            if (reason.trim().length < 5) {
                window.alert('Informe uma justificativa com pelo menos 5 caracteres.');
                return;
            }

            const formData = new FormData();
            formData.set('_csrf', currentCsrf());
            formData.set('acao', action);
            formData.set('permissao_id', button.dataset.id || '');
            formData.set('motivo', reason.trim());
            button.disabled = true;

            try {
                const payload = await request(formData);
                if (!payload) return;
                window.alert(payload.message || 'Situação da permissão atualizada.');
                window.location.reload();
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'Não foi possível alterar a permissão.');
                button.disabled = false;
            }
        });
    });

    modalElement.addEventListener('hidden.bs.modal', resetForCreate);

    if (modalElement.dataset.autoOpen === '1') {
        resetForCreate();
        modal.show();
    }
})();
