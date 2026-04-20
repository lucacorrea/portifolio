<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// seu código abaixo

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

expire_temp_users();

if (current_user() && !empty($_SESSION['is_real_admin'])) {
    redirect('painel_admin.php');
}

$erro = flash('erro');
$ok   = flash('ok');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Administrativo</title>
    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:Arial,Helvetica,sans-serif;
            background:#dfe6ef;
            color:#16233b;
        }
        .page{
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .card{
            width:100%;
            max-width:560px;
            background:#fff;
            border-radius:22px;
            padding:34px;
            box-shadow:0 20px 50px rgba(21,35,63,.12);
        }
        h1{
            margin:0 0 8px;
            text-align:center;
            font-size:40px;
        }
        .sub{
            margin:0 0 30px;
            text-align:center;
            color:#5f6e86;
            font-size:16px;
        }
        .alert{
            padding:14px 16px;
            border-radius:12px;
            margin-bottom:18px;
            font-size:14px;
        }
        .alert.error{background:#ffe7e7;color:#a11d1d}
        .alert.ok{background:#e8fff0;color:#136c36}
        label{
            display:block;
            margin-bottom:8px;
            font-size:12px;
            font-weight:700;
            letter-spacing:.12em;
            color:#657795;
            text-transform:uppercase;
        }
        .field{
            margin-bottom:18px;
        }
        input, select{
            width:100%;
            height:56px;
            border:1px solid #d9e0ea;
            border-radius:14px;
            padding:0 16px;
            font-size:18px;
            color:#16233b;
            outline:none;
            background:#f9fbff;
        }
        .row{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px;
        }
        .check{
            display:flex;
            align-items:center;
            gap:10px;
            font-size:14px;
            color:#4d5f7d;
            margin:6px 0 18px;
        }
        .check input{
            width:18px;
            height:18px;
        }
        .btn{
            width:100%;
            height:58px;
            border:none;
            border-radius:14px;
            cursor:pointer;
            font-size:19px;
            font-weight:700;
            transition:.2s ease;
        }
        .btn:hover{transform:translateY(-1px)}
        .btn.primary{
            background:#2f558d;
            color:#fff;
            margin-bottom:12px;
        }
        .btn.secondary{
            background:#eef3fb;
            color:#2f558d;
            border:1px solid #ccd8ea;
        }
        .foot{
            text-align:center;
            margin-top:18px;
            color:#61728d;
            font-size:14px;
        }
        .muted{
            font-size:13px;
            color:#7d8ca3;
            margin-top:10px;
            line-height:1.5;
        }
        @media (max-width:640px){
            .card{padding:24px}
            h1{font-size:30px}
            .row{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Acesso Admin</h1>
        <p class="sub">Primeiro acesso com e-mail e senha. Depois, com a biometria salva no aparelho.</p>

        <?php if ($erro): ?>
            <div class="alert error"><?= e($erro) ?></div>
        <?php endif; ?>

        <?php if ($ok): ?>
            <div class="alert ok"><?= e($ok) ?></div>
        <?php endif; ?>

        <form method="post" action="processa_login_admin.php" id="formLogin">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="field">
                <label for="email">E-mail do administrador</label>
                <input type="email" name="email" id="email" placeholder="admin@empresa.com" required>
            </div>

            <div class="field">
                <label for="senha">Senha</label>
                <input type="password" name="senha" id="senha" placeholder="Digite sua senha" required>
            </div>

            <label class="check">
                <input type="checkbox" name="salvar_biometria" value="1" id="salvarBiometria">
                <span>Salvar neste aparelho e ativar biometria após o login</span>
            </label>

            <button type="submit" class="btn primary">Entrar com credenciais</button>
            <button type="button" class="btn secondary" id="btnPasskey">Entrar com biometria</button>

            <p class="muted">
                A biometria só aparece depois que o admin fizer o primeiro login normal e ativar a passkey neste aparelho.
            </p>
        </form>

        <div class="foot">Somente admin ou master reais entram nesta área de geração.</div>
    </div>
</div>

<script>
function base64ToArrayBuffer(base64) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (const b of bytes) {
        binary += String.fromCharCode(b);
    }
    return btoa(binary);
}

function prepareGetOptions(publicKey) {
    publicKey.challenge = base64ToArrayBuffer(publicKey.challenge);

    if (Array.isArray(publicKey.allowCredentials)) {
        publicKey.allowCredentials = publicKey.allowCredentials.map(item => ({
            ...item,
            id: base64ToArrayBuffer(item.id)
        }));
    }

    return publicKey;
}

async function loginWithPasskey() {
    if (!window.isSecureContext || !window.PublicKeyCredential) {
        alert('Biometria indisponível neste navegador/aparelho.');
        return;
    }

    const emailInput = document.getElementById('email');
    const remembered = localStorage.getItem('adminPasskeyEmail') || '';
    const email = (emailInput.value || remembered).trim();

    if (!email) {
        alert('Informe o e-mail do admin que cadastrou a biometria neste aparelho.');
        emailInput.focus();
        return;
    }

    try {
        const startResp = await fetch('passkey_login_begin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ email })
        });

        const startData = await startResp.json();
        if (!startData.ok) {
            throw new Error(startData.message || 'Não foi possível iniciar a biometria.');
        }

        const credential = await navigator.credentials.get({
            publicKey: prepareGetOptions(startData.publicKey)
        });

        const finishResp = await fetch('passkey_login_finish.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id: credential.id,
                rawId: arrayBufferToBase64(credential.rawId),
                type: credential.type,
                clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
                authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
                signature: arrayBufferToBase64(credential.response.signature),
                userHandle: credential.response.userHandle ? arrayBufferToBase64(credential.response.userHandle) : ''
            })
        });

        const finishData = await finishResp.json();
        if (!finishData.ok) {
            throw new Error(finishData.message || 'Falha na autenticação biométrica.');
        }

        localStorage.setItem('adminPasskeyEmail', email);
        window.location.href = finishData.redirect || 'painel_admin.php';
    } catch (err) {
        alert(err.message || 'Erro ao autenticar com biometria.');
    }
}

document.getElementById('btnPasskey').addEventListener('click', loginWithPasskey);

const rememberedEmail = localStorage.getItem('adminPasskeyEmail');
if (rememberedEmail && !document.getElementById('email').value) {
    document.getElementById('email').value = rememberedEmail;
}
</script>
</body>
</html>