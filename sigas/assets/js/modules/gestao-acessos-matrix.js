'use strict';

(() => {
    const root = document.querySelector('[data-governance-access-matrix]');
    if (!root) return;

    const levelSelect = root.querySelector('[data-matrix-level-select]');
    const form = root.querySelector('[data-matrix-form]');
    const alertBox = root.querySelector('[data-matrix-alert]');

    levelSelect?.addEventListener('change', () => {
        const levelId = Number.parseInt(levelSelect.value || '', 10);
        if (!Number.isInteger(levelId) || levelId <= 0) return;
        window.location.assign(`governanca-acessos/matriz-acesso.php?nivel=${encodeURIComponent(String(levelId))}`);
    });

    if (!form) return;

    const showMessage = (message, type = 'danger') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} ga-matrix-alert`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const setBusy = busy => {
        form.setAttribute('aria-busy', String(busy));
        form.querySelectorAll('button, input, textarea, select').forEach(field => {
            if (field.dataset.permanentlyDisabled === '1') return;
            field.disabled = busy;
        });
    };

    form.querySelectorAll('[disabled]').forEach(field => {
        field.dataset.permanentlyDisabled = '1';
    });

    root.querySelectorAll('[data-matrix-module]').forEach(module => {
        const view = module.querySelector('[data-matrix-view]');
        const actions = [...module.querySelectorAll('[data-matrix-action]')];

        if (!view || view.disabled) return;

        view.addEventListener('change', () => {
            if (view.checked) return;
            actions.forEach(action => {
                if (!action.disabled) action.checked = false;
            });
        });

        actions.forEach(action => {
            action.addEventListener('change', () => {
                if (action.checked) view.checked = true;
            });
        });
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.reportValidity()) return;

        const levelId = Number.parseInt(form.querySelector('input[name="nivel_id"]')?.value || '', 10);
        if (!Number.isInteger(levelId) || levelId <= 0) {
            showMessage('Selecione um nível de acesso válido.');
            return;
        }

        if (!window.confirm('Salvar esta matriz? Usuários deste nível terão as autorizações renovadas e sessões ativas poderão ser encerradas.')) {
            return;
        }

        const formData = new FormData(form);
        setBusy(true);
        showMessage('Atualizando matriz de acesso...', 'info');

        try {
            const response = await fetch('api/governanca-acessos/matriz-acao.php', {
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
                showMessage(payload.message || 'Sua sessão expirou. Entre novamente.', 'warning');
                window.setTimeout(() => window.location.assign('index.php'), 700);
                return;
            }

            if (!response.ok || payload.ok !== true) {
                showMessage(payload.message || 'Não foi possível atualizar a matriz.', 'danger');
                setBusy(false);
                return;
            }

            const affected = Number.parseInt(payload.affected_users || '0', 10) || 0;
            const revoked = Number.parseInt(payload.revoked_sessions || '0', 10) || 0;
            const detail = affected > 0
                ? ` ${affected} usuário(s) tiveram a autorização renovada; ${revoked} sessão(ões) ativa(s) foram encerrada(s).`
                : '';
            showMessage((payload.message || 'Matriz atualizada com sucesso.') + detail, 'success');

            window.setTimeout(() => {
                window.location.assign(`governanca-acessos/matriz-acesso.php?nivel=${encodeURIComponent(String(levelId))}`);
            }, 1100);
        } catch (_) {
            showMessage('Falha de comunicação com o servidor. Tente novamente.', 'danger');
            setBusy(false);
        }
    });
})();
