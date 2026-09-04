'use strict';

(() => {
    const form = document.querySelector('[data-governance-user-create]');
    const alertBox = form?.querySelector('[data-governance-user-create-alert]');

    if (!form) return;

    const showMessage = (message, type = 'danger') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const setBusy = busy => {
        form.querySelectorAll('button, input, select').forEach(control => {
            if (control.type === 'hidden') return;
            control.disabled = busy;
        });
        form.setAttribute('aria-busy', String(busy));
    };

    const formatCpf = value => {
        const digits = value.replace(/\D/g, '').slice(0, 11);
        return digits
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2');
    };

    const formatPhone = value => {
        const digits = value.replace(/\D/g, '').slice(0, 11);
        if (digits.length <= 10) {
            return digits
                .replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{4})(\d)/, '$1-$2');
        }
        return digits
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d)/, '$1-$2');
    };

    const cpf = form.querySelector('input[name="cpf"]');
    const phone = form.querySelector('input[name="telefone"]');
    cpf?.addEventListener('input', () => { cpf.value = formatCpf(cpf.value); });
    phone?.addEventListener('input', () => { phone.value = formatPhone(phone.value); });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (!form.reportValidity()) return;

        const password = form.querySelector('input[name="senha"]')?.value || '';
        const confirmation = form.querySelector('input[name="senha_confirmacao"]')?.value || '';
        if (password !== confirmation) {
            showMessage('A confirmação da senha não confere.', 'warning');
            return;
        }

        setBusy(true);
        showMessage('Criando usuário pendente...', 'info');

        try {
            const response = await fetch('api/governanca-acessos/usuario-criar.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form),
                credentials: 'same-origin'
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (_) {
                payload = {};
            }

            if (typeof payload.csrf === 'string' && payload.csrf !== '') {
                const token = form.querySelector('input[name="_csrf"]');
                if (token) token.value = payload.csrf;
            }

            if (response.status === 401) {
                showMessage(payload.message || 'Sua sessão expirou. Redirecionando para o login.', 'warning');
                window.setTimeout(() => window.location.assign('index.php'), 600);
                return;
            }

            if (!response.ok || payload.ok !== true) {
                showMessage(payload.message || 'Não foi possível criar o usuário.', 'danger');
                setBusy(false);
                return;
            }

            showMessage(payload.message || 'Usuário criado como pendente.', 'success');
            const userId = Number.parseInt(String(payload.user_id || ''), 10);
            window.setTimeout(() => {
                window.location.assign(Number.isInteger(userId) && userId > 0
                    ? `governanca-acessos/usuarios.php?usuario=${userId}`
                    : 'governanca-acessos/usuarios.php');
            }, 800);
        } catch (_) {
            showMessage('Falha de comunicação com o servidor. Tente novamente.', 'danger');
            setBusy(false);
        }
    });
})();
