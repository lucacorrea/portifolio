<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_guard_real_admin.php';

$mensagem = flash('ok');
$erro     = flash('erro');

$gerado = $_SESSION['generated_temp_user'] ?? null;
unset($_SESSION['generated_temp_user']);

$autoPasskey = !empty($_SESSION['trigger_passkey_setup']);
unset($_SESSION['trigger_passkey_setup']);

$adminEmail = $_SESSION['just_logged_admin_email'] ?? $usuarioLogado['email'];
unset($_SESSION['just_logged_admin_email']);

$passkeyAtiva = (int)($usuarioLogado['passkey_enabled'] ?? 0) === 1;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin</title>
    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:Arial,Helvetica,sans-serif;
            background:#dfe6ef;
            color:#16233b;
        }
        .wrap{
            max-width:1200px;
            margin:0 auto;
            padding:30px 20px;
        }
        .top{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            margin-bottom:24px;
        }
        .title h1{
            margin:0 0 6px;
            font-size:34px;
        }
        .title p{
            margin:0;
            color:#5d6f8b;
        }
        .logout{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            height:48px;
            padding:0 18px;
            border-radius:12px;
            text-decoration:none;
            background:#fff;
            color:#2f558d;
            font-weight:700;
            border:1px solid #d4dfef;
        }
        .grid{
            display:grid;
            grid-template-columns:360px 1fr;
            gap:20px;
        }
        .card{
            background:#fff;
            border-radius:20px;
            padding:24px;
            box-shadow:0 18px 40px rgba(18,35,63,.08);
        }
        .alert{
            padding:14px 16px;
            border-radius:12px;
            margin-bottom:16px;
            font-size:14px;
        }
        .alert.ok{background:#e9fff0;color:#1d6f38}
        .alert.error{background:#ffe9e9;color:#a11d1d}
        .info-item{
            margin-bottom:14px;
        }
        .info-item small{
            display:block;
            text-transform:uppercase;
            letter-spacing:.12em;
            color:#7a8aa1;
            font-size:11px;
            font-weight:700;
            margin-bottom:5px;
        }
        .info-item strong{
            font-size:18px;
        }
        .status{
            display:inline-block;
            padding:8px 12px;
            border-radius:999px;
            font-size:13px;
            font-weight:700;
            background:#eef4ff;
            color:#2f558d;
            margin-top:10px;
        }
        .field{
            margin-bottom:18px;
        }
        label{
            display:block;
            margin-bottom:8px;
            font-size:12px;
            font-weight:700;
            letter-spacing:.12em;
            color:#657795;
            text-transform:uppercase;
        }
        input, select{
            width:100%;
            height:56px;
            border:1px solid #d9e0ea;
            border-radius:14px;
            padding:0 16px;
            font-size:16px;
            background:#f9fbff;
            color:#16233b;
            outline:none;
        }
        .row{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px;
        }
        .btn{
            width:100%;
            height:56px;
            border:none;
            border-radius:14px;
            cursor:pointer;
            font-size:18px;
            font-weight:700;
            transition:.2s ease;
        }
        .btn:hover{transform:translateY(-1px)}
        .btn.primary{background:#2f558d;color:#fff}
        .btn.soft{
            background:#eef3fb;
            color:#2f558d;
            border:1px solid #cfd9ea;
        }
        .result{
            margin-top:18px;
            padding:18px;
            border-radius:16px;
            background:#f5f9ff;
            border:1px dashed #9fb5d7;
        }
        .result code{
            display:block;
            font-size:28px;
            font-weight:800;
            color:#1f4275;
            margin:8px 0 10px;
        }
        .note{
            color:#6c7b90;
            font-size:14px;
            line-height:1.55;
        }
        @media (max-width:900px){
            .grid{grid-template-columns:1fr}
        }
        @media (max-width:640px){
            .row{grid-template-columns:1fr}
            .top{flex-direction:column;align-items:flex-start}
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="title">
            <h1>Painel do Administrador</h1>
            <p>Geração de usuário temporário com expiração automática em 30 minutos.</p>
        </div>
        <a class="logout" href="logout.php">Sair</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert ok"><?= e($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert error"><?= e($erro) ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <div class="info-item">
                <small>Administrador logado</small>
                <strong><?= e($usuarioLogado['nome']) ?></strong>
            </div>

            <div class="info-item">
                <small>E-mail</small>
                <strong><?= e($usuarioLogado['email']) ?></strong>
            </div>

            <div class="info-item">
                <small>Nível</small>
                <strong><?= e($usuarioLogado['nivel']) ?></strong>
            </div>

            <div class="status">
                <?= $passkeyAtiva ? 'Biometria ativa neste usuário' : 'Biometria ainda não cadastrada' ?>
            </div>

            <div style="margin-top:18px;">
                <button type="button" class="btn soft" id="btnRegistrarPasskey">
                    <?= $passkeyAtiva ? 'Cadastrar outro aparelho biométrico' : 'Ativar biometria neste aparelho' ?>
                </button>
            </div>

            <p class="note" style="margin-top:16px;">
                Usuário temporário entra com e-mail + código temporário. Ele pode acessar a área admin se o nível dele for admin,
                mas não consegue abrir esta tela de geração.
            </p>
        </div>

        <div class="card">
            <form method="post" action="gerar_usuario_temporario.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="field">
                    <label for="nome">Nome do usuário temporário</label>
                    <input type="text" name="nome" id="nome" required placeholder="Ex.: Suporte Temporário">
                </div>

                <div class="row">
                    <div class="field">
                        <label for="email">E-mail do usuário temporário</label>
                        <input type="email" name="email" id="email" required placeholder="temp@empresa.com">
                    </div>

                    <div class="field">
                        <label for="filial_id">Filial</label>
                        <input type="number" name="filial_id" id="filial_id" min="1" placeholder="Opcional">
                    </div>
                </div>

                <div class="row">
                    <div class="field">
                        <label for="nivel">Tipo de usuário</label>
                        <select name="nivel" id="nivel" required>
                            <option value="vendedor">Vendedor</option>
                            <option value="tecnico">Técnico</option>
                            <option value="gerente">Gerente</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="desconto_maximo">Desconto máximo (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="desconto_maximo" id="desconto_maximo" value="0.00">
                    </div>
                </div>

                <button type="submit" class="btn primary">Gerar usuário temporário</button>
            </form>

            <?php if ($gerado): ?>
                <div class="result">
                    <div><strong>Usuário temporário criado com sucesso</strong></div>
                    <div style="margin-top:10px;">Código de acesso:</div>
                    <code><?= e($gerado['codigo']) ?></code>
                    <div>E-mail: <strong><?= e($gerado['email']) ?></strong></div>
                    <div>Nível: <strong><?= e($gerado['nivel']) ?></strong></div>
                    <div>Expira em: <strong><?= e($gerado['expira_em']) ?></strong></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const ADMIN_EMAIL = <?= json_encode((string)$adminEmail) ?>;
const AUTO_PASSKEY = <?= $autoPasskey ? 'true' : 'false' ?>;

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

function prepareCreateOptions(publicKey) {
    publicKey.challenge = base64ToArrayBuffer(publicKey.challenge);
    publicKey.user.id = base64ToArrayBuffer(publicKey.user.id);

    if (Array.isArray(publicKey.excludeCredentials)) {
        publicKey.excludeCredentials = publicKey.excludeCredentials.map(item => ({
            ...item,
            id: base64ToArrayBuffer(item.id)
        }));
    }

    return publicKey;
}

async function registerPasskey() {
    if (!window.isSecureContext || !window.PublicKeyCredential) {
        alert('Biometria/passkey exige HTTPS e navegador compatível.');
        return;
    }

    try {
        if (PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
            const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            if (!available) {
                throw new Error('Este aparelho não possui autenticador biométrico/plataforma disponível.');
            }
        }

        const startResp = await fetch('passkey_register_begin.php', {
            method: 'POST'
        });

        const startData = await startResp.json();
        if (!startData.ok) {
            throw new Error(startData.message || 'Falha ao iniciar cadastro da biometria.');
        }

        const credential = await navigator.credentials.create({
            publicKey: prepareCreateOptions(startData.publicKey)
        });

        const finishResp = await fetch('passkey_register_finish.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id: credential.id,
                rawId: arrayBufferToBase64(credential.rawId),
                type: credential.type,
                clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
                attestationObject: arrayBufferToBase64(credential.response.attestationObject)
            })
        });

        const finishData = await finishResp.json();
        if (!finishData.ok) {
            throw new Error(finishData.message || 'Falha ao salvar biometria.');
        }

        localStorage.setItem('adminPasskeyEmail', ADMIN_EMAIL);
        alert('Biometria ativada com sucesso.');
        window.location.reload();
    } catch (err) {
        alert(err.message || 'Erro ao cadastrar biometria.');
    }
}

document.getElementById('btnRegistrarPasskey').addEventListener('click', registerPasskey);

if (AUTO_PASSKEY) {
    setTimeout(registerPasskey, 600);
}
</script>
</body>
</html>