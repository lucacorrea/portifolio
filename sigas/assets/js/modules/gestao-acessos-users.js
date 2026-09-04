'use strict';

(() => {
    const modalElement = document.querySelector('#governanceUserAdminModal');
    const form = modalElement?.querySelector('[data-governance-user-form]');
    const alertBox = modalElement?.querySelector('[data-governance-user-alert]');

    if (!modalElement || !form || typeof bootstrap === 'undefined') {
        return;
    }

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

    const setBusy = busy => {
        form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.disabled = busy || button.dataset.permanentlyDisabled === '1';
        });
        form.setAttribute('aria-busy', String(busy));
    };

    form.querySelectorAll('button[type="submit"][disabled]').forEach(button => {
        button.dataset.permanentlyDisabled = '1';
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submitter = event.submitter;
        const action = submitter?.value || '';

        if (!action) return;

        if (!form.reportValidity()) {
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

            showMessage(payload.message || 'Ação concluída com sucesso.', 'success');
            window.setTimeout(() => window.location.assign('governanca-acessos/usuarios.php'), 900);
        } catch (_) {
            showMessage('Falha de comunicação com o servidor. Tente novamente.', 'danger');
            setBusy(false);
        }
    });
})();
