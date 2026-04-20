<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$admin = app_require_admin();
$passkeyAvailable = app_webauthn_available();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salvar Dispositivo - ERP Elétrica</title>
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
            max-width: 640px;
            border-radius: 24px;
            overflow: hidden;
            background: #fff;
            box-shadow: var(--shadow);
        }
        .hero { background: var(--primary); padding: 30px 22px 24px; text-align:center; }
        .hero img { max-width: 280px; width:100%; height:auto; }
        .accent-line { height: 6px; background: var(--accent); }
        .body { padding: 34px 34px 28px; }
        .title { text-align:center; font-size: 1.95rem; font-weight:900; color:var(--primary-dark); margin-bottom:8px; }
        .subtitle { text-align:center; color:var(--muted); line-height:1.6; margin-bottom:26px; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; }
        .info-box { border:1px solid var(--border); background:#f8fbff; border-radius:14px; padding:14px 16px; }
        .info-box span { display:block; font-size:.8rem; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.7px; margin-bottom:4px; }
        .info-box strong { display:block; font-size:1rem; color:var(--primary-dark); }
        .status { display:none; margin-bottom:16px; padding:14px 16px; border-radius:12px; font-weight:700; line-height:1.5; }
        .status.show { display:block; }
        .status.info { background:#eef4ff; border:1px solid #d8e5ff; color:#2c4d81; }
        .status.success { background:#effaf4; border:1px solid #ccefd9; color:var(--success); }
        .status.error { background:#fff2f2; border:1px solid #ffd5d5; color:var(--danger); }
        .steps { background:#f7fbff; border:1px solid #dfe8f2; border-radius:18px; padding:18px 18px 8px; margin-bottom:22px; }
        .steps h3 { margin:0 0 12px; color:var(--primary-dark); font-size:1rem; }
        .steps ol { margin:0; padding-left:18px; color:var(--muted); line-height:1.75; }
        .actions { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .btn { height:58px; border:none; border-radius:12px; font-size:1rem; font-weight:900; text-transform:uppercase; cursor:pointer; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-primary:hover { background:#274774; }
        .btn-secondary { background:#ebf1f8; color:var(--primary-dark); }
        .btn-secondary:hover { background:#dfe8f4; }
        @media (max-width: 680px) {
            .body { padding: 24px 16px 20px; }
            .info-grid, .actions { grid-template-columns:1fr; }
            .title { font-size:1.6rem; }
        }
    </style>
</head>
<body>
<div class="page">
    <main class="card">
        <div class="hero">
            <img src="assets/img/logo-centro-eletricista.png" alt="Centro do Eletricista" onerror="this.style.display='none'">
        </div>
        <div class="accent-line"></div>
        <div class="body">
            <h1 class="title">SALVAR ESTE DISPOSITIVO</h1>
            <p class="subtitle">Depois do primeiro login válido, o admin pode cadastrar uma passkey para o próximo acesso usar a autenticação do aparelho.</p>

            <div class="info-grid">
                <div class="info-box">
                    <span>Administrador</span>
                    <strong><?= app_h((string)$admin['nome']) ?></strong>
                </div>
                <div class="info-box">
                    <span>E-mail</span>
                    <strong><?= app_h((string)$admin['email']) ?></strong>
                </div>
            </div>

            <div class="status" id="statusBox"></div>

            <div class="steps">
                <h3>O que vai acontecer</h3>
                <ol>
                    <li>O navegador vai pedir a autenticação do aparelho.</li>
                    <li>O dispositivo pode usar digital, rosto, PIN ou outro método local suportado.</li>
                    <li>Depois disso, este aparelho fica salvo para o próximo login do admin.</li>
                </ol>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-primary" id="btnSalvar" <?= $passkeyAvailable ? '' : 'disabled' ?>>Salvar com biometria</button>
                <button type="button" class="btn btn-secondary" id="btnAgoraNao">Agora não</button>
            </div>
        </div>
    </main>
</div>

<script src="assets/js/passkey.js"></script>
<script>
const btnSalvar = document.getElementById('btnSalvar');
const btnAgoraNao = document.getElementById('btnAgoraNao');
const statusBox = document.getElementById('statusBox');

function showStatus(message, type = 'info') {
    statusBox.className = 'status show ' + type;
    statusBox.textContent = message;
}

btnAgoraNao.addEventListener('click', () => {
    window.location.href = 'gerar_usuario_temporario.php';
});

btnSalvar.addEventListener('click', async () => {
    if (!window.PublicKeyCredential) {
        showStatus('Este navegador não suporta passkeys/WebAuthn.', 'error');
        return;
    }

    btnSalvar.disabled = true;
    btnAgoraNao.disabled = true;
    showStatus('Preparando o cadastro deste dispositivo...', 'info');

    try {
        const beginResponse = await fetch('api/passkey_register_begin.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const beginData = await beginResponse.json();

        if (!beginResponse.ok || !beginData.ok) {
            throw new Error(beginData.message || 'Não foi possível iniciar o cadastro do dispositivo.');
        }

        const options = PasskeyHelper.prepareOptions(beginData.options);
        const credential = await navigator.credentials.create(options);

        if (!credential) {
            throw new Error('O dispositivo não retornou uma credencial válida.');
        }

        const payload = PasskeyHelper.serializeRegistration(credential);
        const body = new FormData();
        for (const [key, value] of Object.entries(payload)) {
            if (value !== null && value !== undefined) {
                body.append(key, value);
            }
        }

        const finishResponse = await fetch('api/passkey_register_finish.php', {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const finishData = await finishResponse.json();

        if (!finishResponse.ok || !finishData.ok) {
            throw new Error(finishData.message || 'Não foi possível concluir o cadastro do dispositivo.');
        }

        localStorage.setItem('ce_admin_passkey_ready', '1');
        showStatus(finishData.message || 'Dispositivo salvo com sucesso.', 'success');
        window.location.href = finishData.redirect || 'gerar_usuario_temporario.php';
    } catch (error) {
        showStatus(error.message || 'Falha ao salvar este dispositivo.', 'error');
        btnSalvar.disabled = <?= $passkeyAvailable ? 'false' : 'true' ?>;
        btnAgoraNao.disabled = false;
    }
});
</script>
</body>
</html>
