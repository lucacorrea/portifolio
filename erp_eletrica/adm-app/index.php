<?php
require_once '../config.php';
$isLoggedIn = isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] != -1 && $_SESSION['usuario_nivel'] === 'admin';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ERP Adm Mobile</title>
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<style>
    :root {
        --primary: #2f5487;
        --primary-dark: #24456f;
        --primary-soft: #dfeafb;
        --yellow: #ffcc19;
        --yellow-dark: #f0b900;
        --text: #192235;
        --muted: #7b8798;
        --border: #dfe6f1;
        --bg-a: #c5d2e1;
        --bg-b: #9fb0c4;
        --white: #ffffff;
        --danger: #dc3545;
        --success: #22a06b;
        --purple: #6366f1;
        --shadow: 0 18px 45px rgba(30, 48, 75, 0.22);
        --radius: 18px;
    }

    * {
        box-sizing: border-box;
    }

    html,
    body {
        min-height: 100%;
    }

    body {
        margin: 0;
        font-family: "Inter", Arial, Helvetica, sans-serif;
        color: var(--text);
        background:
            radial-gradient(circle at top left, rgba(255, 255, 255, 0.35), transparent 35%),
            linear-gradient(135deg, var(--bg-a), var(--bg-b));
    }

    button,
    input,
    select {
        font-family: inherit;
    }

    .page-auth {
        min-height: 100vh;
        width: 100%;
        padding: 28px 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .auth-shell {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card,
    .dashboard-card {
        width: 100%;
        max-width: 460px;
        background: var(--white);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow);
        border: 1px solid rgba(255, 255, 255, 0.7);
    }

    .login-brand,
    .dashboard-brand {
        min-height: 160px;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 28px;
    }

    .brand-logo-wrap {
        width: 245px;
        max-width: 90%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .brand-logo {
        max-width: 100%;
        max-height: 95px;
        object-fit: contain;
        display: block;
    }

    .brand-line {
        width: 100%;
        height: 6px;
        background: var(--yellow);
    }

    .login-body,
    .dashboard-body {
        padding: 36px 40px 32px;
    }

    .login-title-box {
        text-align: center;
        margin-bottom: 28px;
    }

    .login-title-box h1 {
        margin: 0 0 8px;
        font-size: 1.22rem;
        line-height: 1;
        font-weight: 900;
        color: var(--text);
        letter-spacing: 0.02em;
    }

    .login-title-box p {
        margin: 0;
        color: var(--muted);
        font-size: 0.88rem;
        font-weight: 500;
    }

    .form-group-ce {
        margin-bottom: 18px;
    }

    .form-group-ce label {
        display: block;
        margin-bottom: 9px;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #25334a;
    }

    .form-control-ce {
        width: 100%;
        min-height: 52px;
        border: 1px solid var(--border);
        border-radius: 9px;
        background: #f7f9fc;
        color: var(--text);
        outline: none;
        padding: 0 16px;
        font-size: 0.94rem;
        font-weight: 600;
        transition: 0.2s ease;
    }

    .form-control-ce:focus {
        border-color: rgba(47, 84, 135, 0.55);
        background: #eef5ff;
        box-shadow: 0 0 0 4px rgba(47, 84, 135, 0.09);
    }

    .form-control-ce::placeholder {
        color: #8b98ab;
        font-weight: 500;
    }

    select.form-control-ce {
        appearance: none;
        background-image:
            linear-gradient(45deg, transparent 50%, #65758c 50%),
            linear-gradient(135deg, #65758c 50%, transparent 50%);
        background-position:
            calc(100% - 19px) 22px,
            calc(100% - 14px) 22px;
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
        padding-right: 42px;
    }

    .password-field {
        position: relative;
        display: flex;
        align-items: center;
    }

    .password-input {
        padding-right: 58px;
    }

    .btn-password-toggle {
        position: absolute;
        top: 1px;
        right: 1px;
        width: 57px;
        height: 50px;
        border: 0;
        border-left: 1px solid var(--border);
        border-radius: 0 9px 9px 0;
        background: #f7f9fc;
        color: #63748b;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .btn-password-toggle:hover {
        color: var(--primary);
        background: #eef5ff;
    }

    .btn-access {
        width: 100%;
        min-height: 54px;
        border: 0;
        border-radius: 8px;
        background: var(--primary);
        color: var(--white);
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.88rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(47, 84, 135, 0.25);
        transition: 0.2s ease;
    }

    .btn-access:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .btn-access:active {
        transform: translateY(0);
    }

    .btn-access-purple {
        background: var(--purple);
        box-shadow: 0 8px 18px rgba(99, 102, 241, 0.25);
    }

    .btn-access-purple:hover {
        background: #5558db;
    }

    .biometric-box {
        display: none;
        margin-top: 14px;
    }

    .btn-biometric {
        width: 100%;
        min-height: 46px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #f7f9fc;
        color: var(--primary);
        font-size: 0.82rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .login-error {
        display: none;
        margin-top: 14px;
        padding: 12px;
        border-radius: 8px;
        background: rgba(220, 53, 69, 0.08);
        color: var(--danger);
        font-size: 0.82rem;
        font-weight: 700;
        text-align: center;
    }

    .admin-link {
        margin: 24px auto 28px;
        color: var(--primary);
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        font-size: 0.82rem;
        font-weight: 800;
    }

    .admin-link:hover {
        color: var(--primary-dark);
    }

    .login-footer {
        border-top: 1px solid #edf1f7;
        padding-top: 20px;
        text-align: center;
    }

    .login-footer p {
        margin: 0;
        color: #8c99aa;
        font-size: 0.66rem;
        font-weight: 800;
        letter-spacing: 0.16em;
    }

    .login-footer p+p {
        margin-top: 4px;
    }

    .dashboard-card {
        max-width: 520px;
    }

    .dashboard-brand {
        min-height: 130px;
    }

    .dashboard-body {
        padding: 28px;
    }

    .dashboard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 20px;
    }

    .dashboard-header span {
        display: block;
        margin-bottom: 6px;
        color: var(--primary);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .dashboard-header h1 {
        margin: 0;
        color: var(--text);
        font-size: 1.34rem;
        font-weight: 900;
    }

    .dashboard-header p {
        margin: 5px 0 0;
        color: var(--muted);
        font-size: 0.82rem;
        font-weight: 600;
    }

    .dashboard-avatar {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        background: var(--primary-soft);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        flex: 0 0 auto;
    }

    .quick-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .quick-card {
        border: 1px solid var(--border);
        background: #f8fafd;
        border-radius: 14px;
        padding: 14px;
    }

    .quick-card i {
        color: var(--primary);
        font-size: 1.1rem;
        margin-bottom: 8px;
    }

    .quick-card strong {
        display: block;
        color: var(--text);
        font-size: 0.86rem;
        font-weight: 900;
    }

    .quick-card span {
        display: block;
        margin-top: 2px;
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .adm-tabs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 18px;
    }

    .adm-tabs .nav-item {
        width: 100%;
    }

    .adm-tabs .nav-link {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: 11px;
        color: var(--primary);
        background: #f8fafd;
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px;
    }

    .adm-tabs .nav-link.active {
        color: var(--white);
        background: var(--primary);
        border-color: var(--primary);
    }

    .section-card {
        border: 1px solid var(--border);
        background: #ffffff;
        border-radius: 16px;
        padding: 18px;
        margin-bottom: 16px;
    }

    .section-title {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 18px;
    }

    .section-title>i {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: var(--primary-soft);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }

    .section-title h2 {
        margin: 0;
        color: var(--text);
        font-size: 1rem;
        font-weight: 900;
    }

    .section-title p {
        margin: 4px 0 0;
        color: var(--muted);
        font-size: 0.78rem;
        font-weight: 600;
    }

    .generated-area {
        display: none;
        margin-top: 18px;
        border-radius: 14px;
        border: 1px dashed rgba(47, 84, 135, 0.35);
        background: #f8fbff;
        padding: 16px;
        text-align: center;
    }

    .generated-area.show {
        display: block;
    }

    .generated-area p {
        margin: 0 0 8px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .code-display {
        color: var(--primary);
        font-size: 2.1rem;
        font-weight: 900;
        line-height: 1;
        letter-spacing: 0.12em;
        margin-bottom: 14px;
    }

    .action-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 12px;
    }

    .btn-outline-ce,
    .btn-whatsapp,
    .btn-outline-warning-ce {
        min-height: 42px;
        border-radius: 9px;
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        border: 1px solid var(--primary);
        background: transparent;
        color: var(--primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .btn-outline-ce:hover {
        background: var(--primary);
        color: var(--white);
    }

    .btn-whatsapp {
        border-color: var(--success);
        color: var(--white);
        background: var(--success);
    }

    .btn-whatsapp:hover {
        filter: brightness(0.95);
    }

    .generated-login {
        text-align: left;
    }

    .info-alert {
        border-radius: 10px;
        background: #e9f3ff;
        color: #315c91;
        padding: 11px 12px;
        font-size: 0.76rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 14px;
    }

    .info-alert.warning {
        background: #fff8da;
        color: #8a6a00;
        margin-bottom: 14px;
    }

    .login-result-row {
        border-bottom: 1px solid #edf1f7;
        padding: 10px 0;
    }

    .login-result-row span {
        display: block;
        color: var(--muted);
        font-size: 0.68rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .login-result-row strong {
        display: block;
        margin-top: 4px;
        color: var(--text);
        font-size: 0.98rem;
        font-weight: 900;
        word-break: break-word;
    }

    .biometric-section {
        background: #fffdf4;
        border-color: #f2df91;
    }

    .btn-outline-warning-ce {
        width: 100%;
        border-color: var(--yellow-dark);
        color: #7b5f00;
        background: transparent;
    }

    .btn-outline-warning-ce:hover {
        background: var(--yellow);
        color: #352800;
    }

    .logout-btn {
        width: 100%;
        min-height: 45px;
        border: 0;
        border-radius: 9px;
        background: #f1f4f8;
        color: #526176;
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        transition: 0.2s ease;
        margin: 2px 0 20px;
    }

    .logout-btn:hover {
        background: #e6ebf2;
        color: var(--primary);
    }

    .dashboard-footer {
        padding-top: 16px;
    }

    @media (max-width: 576px) {
        .page-auth {
            padding: 18px 12px;
            align-items: flex-start;
        }

        .login-card,
        .dashboard-card {
            border-radius: 16px;
        }

        .login-brand {
            min-height: 140px;
            padding: 22px;
        }

        .dashboard-brand {
            min-height: 116px;
        }

        .login-body {
            padding: 30px 24px 26px;
        }

        .dashboard-body {
            padding: 22px 18px;
        }

        .dashboard-header {
            align-items: flex-start;
        }

        .dashboard-header h1 {
            font-size: 1.12rem;
        }

        .quick-grid {
            grid-template-columns: 1fr;
        }

        .action-row {
            grid-template-columns: 1fr;
        }

        .code-display {
            font-size: 1.8rem;
        }
    }
</style>

<body>
    <div class="app-container">
        <?php if (!$isLoggedIn): ?>
            <!-- Login Screen -->
            <div id="login-screen">
                <div class="header">
                    <i class="fas fa-shield-halved fa-4x mb-3" style="color: var(--accent)"></i>
                    <h1>ADM SHIELD</h1>
                    <p>Controle de Acessos e Autorizações</p>
                </div>
                <div class="premium-card">
                    <form id="login-form">
                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-bold opacity-75">Email Administrativo</label>
                            <input type="email" id="email" class="form-control" placeholder="seu-email@adm.com" required autocomplete="username webauthn">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small text-uppercase fw-bold opacity-75">Senha Mestra</label>
                            <input type="password" id="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-premium mb-3">Entrar <i class="fas fa-chevron-right ms-2 mt-1"></i></button>

                        <div id="biometric-login" style="display: none;">
                            <button type="button" class="btn face-id-btn w-100" onclick="tryBiometricLogin()">
                                <i class="fas fa-fingerprint me-2"></i> Desbloquear com Biometria
                            </button>
                        </div>
                    </form>
                    <div id="login-error" class="text-danger small mt-2 text-center" style="display:none;"></div>
                </div>
            </div>
        <?php else: ?>
            <!-- Dashboard Screen -->
            <div id="dashboard-screen">
                <div class="header d-flex align-items-center justify-content-between text-start pb-2">
                    <div>
                        <p class="mb-0 small text-uppercase fw-bold" style="letter-spacing: 1px">Bem-vindo, Adm</p>
                        <h1 class="mt-0 fw-bold"><?= $_SESSION['usuario_nome'] ?></h1>
                    </div>
                    <i class="fas fa-user-circle fa-2x opacity-50"></i>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs d-flex" id="admTabs">
                    <li class="nav-item flex-fill">
                        <a class="nav-link active" id="tab-codes" data-bs-toggle="tab" href="#codes-section">Códigos</a>
                    </li>
                    <li class="nav-item flex-fill">
                        <a class="nav-link" id="tab-logins" data-bs-toggle="tab" href="#logins-section">Logins</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab Codes -->
                    <div class="tab-pane fade show active" id="codes-section">
                        <div class="premium-card">
                            <h5 class="fw-bold mb-4"><i class="fas fa-key me-2 text-info"></i> Gerar Autorização</h5>
                            <div class="mb-3">
                                <label class="small text-uppercase opacity-75">Tipo de Operação</label>
                                <select id="code-type" class="form-select mt-1">
                                    <option value="geral">Qualquer</option>
                                    <option value="sangria">Sangria</option>
                                    <option value="suprimento">Suprimento</option>
                                    <option value="desconto">Desconto</option>
                                    <option value="cancelamento">Cancelamento</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="small text-uppercase opacity-75">Unidade Destino</label>
                                <select id="code-filial" class="form-select mt-1">
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                            <button onclick="generateCode()" class="btn btn-premium">Gerar Código Unico</button>

                            <div id="code-result" class="generated-area" style="display: none;">
                                <p class="small text-uppercase opacity-50 mb-0">Código Gerado</p>
                                <div class="code-display" id="display-code">------</div>
                                <div class="d-flex gap-2 justify-content-center mt-2">
                                    <button class="btn btn-sm btn-outline-info flex-fill" onclick="copyToClipboard('display-code')"><i class="fas fa-copy me-1"></i> COPIAR</button>
                                    <button class="btn btn-sm btn-success flex-fill" onclick="shareToWhatsApp('code')"><i class="fab fa-whatsapp me-1"></i> ENVIAR</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Logins -->
                    <div class="tab-pane fade" id="logins-section">
                        <div class="premium-card">
                            <h5 class="fw-bold mb-4"><i class="fas fa-user-shield me-2 text-info"></i> Login Temporário</h5>
                            <p class="small text-secondary mb-4">Cria um login "Master" que expira automaticamente.</p>

                            <div class="mb-3">
                                <label class="small text-uppercase opacity-75">Tempo de Acesso</label>
                                <select id="temp-time" class="form-select mt-1">
                                    <option value="30">30 Minutos</option>
                                    <option value="60" selected>1 Hora</option>
                                    <option value="240">4 Horas</option>
                                    <option value="480">8 Horas</option>
                                    <option value="720">12 Horas</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="small text-uppercase opacity-75">Unidade (Filial)</label>
                                <select id="temp-filial" class="form-select mt-1">
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                            <button onclick="generateTempLogin()" class="btn btn-premium" style="background-color: #6366f1; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3)">Criar Acesso Especial</button>

                            <div id="temp-result" class="generated-area mt-4 text-start" style="display: none;">
                                <div class="alert alert-info py-2" style="font-size: 0.75rem">
                                    <i class="fas fa-info-circle me-1"></i> Este acesso libera tudo do sistema.
                                </div>
                                <div class="mb-2">
                                    <label class="small text-uppercase opacity-50">Usuário:</label>
                                    <div class="fw-bold" id="display-user">-</div>
                                </div>
                                <div class="mb-2">
                                    <label class="small text-uppercase opacity-50">Senha:</label>
                                    <div class="fw-bold" id="display-pass">-</div>
                                </div>
                                <div class="mb-0">
                                    <label class="small text-uppercase opacity-50">Válido até:</label>
                                    <div class="small fw-bold text-info" id="display-time">-</div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button class="btn btn-sm btn-outline-info flex-fill" onclick="copyLogin()"><i class="fas fa-copy me-1"></i> COPIAR TUDO</button>
                                    <button class="btn btn-sm btn-success flex-fill" onclick="shareToWhatsApp('login')"><i class="fab fa-whatsapp me-1"></i> ENVIAR WHATSAPP</button>
                                </div>
                            </div>
                        </div>

                        <!-- Configurações de Biometria -->
                        <div class="premium-card mt-3" style="background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255,255,255,0.05)">
                            <h6 class="fw-bold mb-3 text-warning"><i class="fas fa-fingerprint me-2"></i>Biometria / FaceID</h6>
                            <p class="extra-small text-secondary mb-3">Vincule a biometria nativa deste celular para acessos rápidos.</p>

                            <div id="biometrics-status" class="alert alert-secondary py-2 extra-small mb-3" style="font-size: 0.75rem">
                                <i class="fas fa-info-circle me-1"></i> Biometria não configurada.
                            </div>

                            <button onclick="registerBiometrics()" id="btn-register-bio" class="btn btn-sm btn-outline-warning w-100 fw-bold">
                                <i class="fas fa-plus-circle me-1"></i> CONFIGURAR NESTE CELULAR
                            </button>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button class="logout-btn" onclick="logout()">
                        <i class="fas fa-sign-out-alt me-1"></i> SAIR DA CONTA ADM
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>

</html>