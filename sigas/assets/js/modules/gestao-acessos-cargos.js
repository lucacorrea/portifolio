'use strict';

(() => {
    const root = document.querySelector('[data-governance-cargos]');
    const modalElement = document.querySelector('#governanceCargoModal');
    const form = modalElement?.querySelector('[data-governance-cargo-form]');
    const alertBox = modalElement?.querySelector('[data-governance-cargo-alert]');

    if (!root || !modalElement || !form || typeof bootstrap === 'undefined') {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
        backdrop: 'static',
        keyboard: true
    });
    const actionField = form.querySelector('[data-cargo-action]');
    const idField = form.querySelector('[data-cargo-id]');
    const nameField = form.querySelector('[data-cargo-name]');
    const descriptionField = form.querySelector('[data-cargo-description]');
    const title = modalElement.querySelector('[data-cargo-modal-title]');
    const submitLabel = modalElement.querySelector('[data-cargo-submit-label]');

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

    const resetForCreate = () => {
        form.reset();
        clearMessage();
        if (actionField) actionField.value = 'create';
        if (idField) idField.value = '';
        if (title) title.textContent = 'Novo cargo';
        if (submitLabel) submitLabel.textContent = 'Cadastrar cargo';
    };

    root.querySelectorAll('[data-cargo-new]').forEach(button => {
        button.addEventListener('click', resetForCreate);
    });

    root.querySelectorAll('[data-cargo-edit]').forEach(button => {
        button.addEventListener('click', () => {
            clearMessage();
            if (actionField) actionField.value = 'update';
            if (idField) idField.value = button.dataset.id || '';
            if (nameField) nameField.value = button.dataset.name || '';
            if (descriptionField) descriptionField.value = button.dataset.description || '';
            if (title) title.textContent = 'Editar cargo';
            if (submitLabel) submitLabel.textContent = 'Salvar alterações';
        });
    });

    const setBusy = busy => {
        form.querySelectorAll('button, input, textarea').forEach(element => {
            if (element.type === 'hidden') return;
            element.disabled = busy;
        });
        form.setAttribute('aria-busy', String(busy));
    };

    const request = async formData => {
        const response = await fetch('api/governanca-acessos/cargo-acao.php', {
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

        const csrf = form.querySelector('input[name="_csrf"]');
        if (csrf && typeof payload.csrf === 'string' && payload.csrf !== '') {
            csrf.value = payload.csrf;
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
        showMessage('Salvando cargo...', 'info');

        try {
            const payload = await request(new FormData(form));
            if (!payload) return;
            showMessage(payload.message || 'Cargo salvo com sucesso.', 'success');
            window.setTimeout(() => window.location.assign('governanca-acessos/cargos.php'), 650);
        } catch (error) {
            showMessage(error instanceof Error ? error.message : 'Não foi possível salvar o cargo.', 'danger');
            setBusy(false);
        }
    });

    root.querySelectorAll('[data-cargo-toggle]').forEach(button => {
        button.addEventListener('click', async () => {
            const action = button.dataset.action || '';
            const name = button.dataset.name || 'este cargo';
            const prompt = action === 'deactivate'
                ? `Inativar ${name}? Ele deixará de aparecer no cadastro de novos usuários.`
                : `Ativar ${name} para novas atribuições?`;

            if (!window.confirm(prompt)) {
                return;
            }

            const csrf = form.querySelector('input[name="_csrf"]');
            const formData = new FormData();
            formData.set('_csrf', csrf?.value || '');
            formData.set('acao', action);
            formData.set('cargo_id', button.dataset.id || '');
            button.disabled = true;

            try {
                await request(formData);
                window.location.reload();
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'Não foi possível alterar o cargo.');
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
