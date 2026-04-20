<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (app_current_admin()) {
    header('Location: gerar_usuario_temporario.php');
    exit;
}

$passkeyAvailable = app_webauthn_available();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - ERP Elétrica</title>
    <style>
        :root {
            --bg: #bac6d6;
            --primary: #2f5488;
            --primary-dark: #1c3761;
            --accent: #f3c31b;
            --card: #ffffff;
            --field: #eef3fb;
            --border: #d7dfeb;
            --text: #1d355a;
            --muted: #6f7d95;
            --success: #1e8e5a;
            --danger: #c44141;
            --shadow: 0 18px 50px rgba(20, 39, 71, 0.18);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 20px;
        }

        .page {
            min-height: calc(100vh - 40px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: 610px;
            border-radius: 24px;
            background: var(--card);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .hero {
            background: var(--primary);
            padding: 28px 22px 22px;
            text-align: center;
        }

        .hero img {
            max-width: 280px;
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        .brand-fallback {
            color: #fff;
            font-weight: 800;
            font-size: 2rem;
            letter-spacing: 0.4px;
        }

        .accent-line {
            height: 6px;
            background: var(--accent);
        }

        .body {
            padding: 34px 38px 30px;
        }

        .title {
            text-align: center;
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 8px;
            color: var(--primary-dark);
        }

        .subtitle {
            text-align: center;
            color: var(--muted);
            font-size: 1.02rem;
            margin-bottom: 28px;
        }

        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-size: .92rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .field input {
            width: 100%;
            height: 58px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--field);
            padding: 0 16px;
            font-size: 1.08rem;
            outline: none;
        }

        .field input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(47, 84, 136, 0.12);
            background: #fff;
        }

        .password-wrap {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 1rem;
            cursor: pointer;
            width: 42px;
            height: 42px;
            border-radius: 10px;
        }

        .btn {
            width: 100%;
            height: 58px;
            border: 0;
            border-radius: 12px;
            font-size: 1.04rem;
            font-weight: 900;
            letter-spacing: .4px;
            cursor: pointer;
            transition: .2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            margin-top: 10px;
        }

        .btn-primary:hover { background: #274774; }

        .btn-secondary {
            margin-top: 12px;
            background: #ebf1f8;
            color: var(--primary-dark);
        }

        .btn-secondary:hover { background: #dfe8f4; }

        .btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .hint {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #dfe7f0;
            background: #f7fafd;
            color: var(--muted);
            line-height: 1.55;
            font-size: .95rem;
        }

        .status {
            display: none;
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 700;
            line-height: 1.5;
        }

        .status.show { display: block; }
        .status.info { background: #eef4ff; border: 1px solid #d8e5ff; color: #2c4d81; }
        .status.success { background: #effaf4; border: 1px solid #ccefd9; color: var(--success); }
        .status.error { background: #fff2f2; border: 1px solid #ffd5d5; color: var(--danger); }

        .footer-note {
            margin-top: 18px;
            text-align: center;
            color: var(--muted);
            font-size: .92rem;
        }

        @media (max-width: 640px) {
            body { padding: 14px; }
            .page { min-height: calc(100vh - 28px); }
            .body { padding: 26px 18px 22px; }
            .title { font-size: 1.65rem; }
        }
    </style>
</head>
<body>
<div class="page">
    <main class="card">
        <div class="hero">
            <img src="assets/img/logo-centro-eletricista.png" alt="Centro do Eletricista" onerror="this.style.display='none';document.getElementById('fallbackLogo').style.display='block';">
            <div class="brand-fallback" id="fallbackLogo" style="display:none;">CENTRO DO ELETRICISTA</div>
        </div>
        <div class="accent-line"></div>
        <div class="body">
            <h1 class="title">LOGIN ADMIN</h1>
            <p class="subtitle">Acesso restrito para administrador e master.</p>

            <div class="status" id="statusBox"></div>

            <form id="loginForm" novalidate>
                <div class="field">
                    <label for="email">E-mail corporativo</label>
                    <input type="email" id="email" name="email" autocomplete="username webauthn" placeholder="Digite o e-mail do administrador" required>
                </div>

                <div class="field">
                    <label for="senha">Senha técnica</label>
                    <div class="password-wrap">
                        <input type="password" id="senha" name="senha" autocomplete="current-password" placeholder="Digite a senha do administrador" required>
                        <button type="button" class="toggle-password" id="toggleSenha" aria-label="Mostrar senha">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="btnEntrar">Confirmar acesso →</button>
            </form>

            <button type="button" class="btn btn-secondary" id="btnBiometria" <?= $passkeyAvailable ? '' : 'disabled' ?>>Entrar com biometria 🔐</button>

            <div class="hint">
                No primeiro acesso, o administrador entra com e-mail e senha. Depois da validação, o sistema pode salvar este dispositivo com passkey para o próximo login usar a autenticação do aparelho.
                <?php if (!$passkeyAvailable): ?>
                    <br><br><strong>Passkey indisponível agora:</strong> instale a dependência com <code>composer require lbuchs/webauthn</code>.
                <?php endif; ?>
            </div>

            <div class="footer-note">Somente usuários com nível <strong>admin</strong> ou <strong>master</strong> entram nesta área.</div>
        </div>
    </main>
</div>

<script src="assets/js/passkey.js"></script>
<script>
const form = document.getElementById('loginForm');
const statusBox = document.getElementById('statusBox');
const btnEntrar = document.getElementById('btnEntrar');
const btnBiometria = document.getElementById('btnBiometria');
const inputSenha = document.getElementById('senha');
const toggleSenha = document.getElementById('toggleSenha');

function showStatus(message, type = 'info') {
    statusBox.className = 'status show ' + type;
    statusBox.textContent = message;
}

function resetStatus() {
    statusBox.className = 'status';
    statusBox.textContent = '';
}

toggleSenha.addEventListener('click', () => {
    inputSenha.type = inputSenha.type === 'password' ? 'text' : 'password';
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    resetStatus();

    const email = form.email.value.trim();
    const senha = form.senha.value;

    if (!email || !senha) {
        showStatus('Preencha e-mail e senha para continuar.', 'error');
        return;
    }

    btnEntrar.disabled = true;
    btnBiometria.disabled = true;
    showStatus('Validando credenciais...', 'info');

    try {
        const body = new FormData();
        body.append('email', email);
        body.append('senha', senha);

        const response = await fetch('api/login_password.php', {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        const data = await response.json();

        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Não foi possível validar o acesso.');
        }

        localStorage.setItem('ce_admin_email', email);
        showStatus(data.message || 'Acesso liberado com sucesso.', 'success');

        if (data.offer_passkey) {
            const saveDevice = window.confirm('Credenciais validadas. Deseja salvar este aparelho para entrar com biometria na próxima vez?');
            if (saveDevice) {
                window.location.href = 'passkey_register.php';
                return;
            }
        }

        window.location.href = data.redirect || 'gerar_usuario_temporario.php';
    } catch (error) {
        showStatus(error.message || 'Falha ao validar o login.', 'error');
        btnEntrar.disabled = false;
        btnBiometria.disabled = <?= $passkeyAvailable ? 'false' : 'true' ?>;
    }
});

btnBiometria.addEventListener('click', async () => {
    if (!window.PublicKeyCredential) {
        showStatus('Este navegador não suporta passkeys/WebAuthn.', 'error');
        return;
    }

    btnBiometria.disabled = true;
    btnEntrar.disabled = true;
    showStatus('Solicitando autenticação do aparelho...', 'info');

    try {
        const beginResponse = await fetch('api/passkey_auth_begin.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        const beginData = await beginResponse.json();

        if (!beginResponse.ok || !beginData.ok) {
            throw new Error(beginData.message || 'Não foi possível iniciar a biometria.');
        }

        const options = PasskeyHelper.prepareOptions(beginData.options);
        const credential = await navigator.credentials.get(options);

        if (!credential) {
            throw new Error('Nenhuma credencial foi retornada pelo aparelho.');
        }

        const payload = PasskeyHelper.serializeAuthentication(credential);
        const finishBody = new FormData();
        for (const [key, value] of Object.entries(payload)) {
            if (value !== null && value !== undefined) {
                finishBody.append(key, value);
            }
        }

        const finishResponse = await fetch('api/passkey_auth_finish.php', {
            method: 'POST',
            body: finishBody,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        const finishData = await finishResponse.json();

        if (!finishResponse.ok || !finishData.ok) {
            throw new Error(finishData.message || 'Biometria não validada.');
        }

        localStorage.setItem('ce_admin_passkey_ready', '1');
        showStatus(finishData.message || 'Biometria validada com sucesso.', 'success');
        window.location.href = finishData.redirect || 'gerar_usuario_temporario.php';
    } catch (error) {
        showStatus(error.message || 'Falha na autenticação biométrica.', 'error');
        btnBiometria.disabled = <?= $passkeyAvailable ? 'false' : 'true' ?>;
        btnEntrar.disabled = false;
    }
});

window.addEventListener('DOMContentLoaded', () => {
    const savedEmail = localStorage.getItem('ce_admin_email');
    if (savedEmail && !form.email.value) {
        form.email.value = savedEmail;
    }
});
</script>
</body>
</html>