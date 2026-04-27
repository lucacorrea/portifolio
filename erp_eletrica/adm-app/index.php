<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

$isLoggedIn = isset($_SESSION['usuario_id'])
    && $_SESSION['usuario_id'] != -1
    && ($_SESSION['usuario_nivel'] ?? '') === 'admin';

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Administrador';

$cssPath = __DIR__ . '/style.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>ERP Adm Mobile</title>

    <link rel="manifest" href="manifest.json">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="style.css?v=<?= $cssVersion ?>">

    <meta name="theme-color" content="#2f5487">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>

<style>
    :root {
    --primary: #2f5487;
    --primary-dark: #24456f;
    --accent: #ffcc19;
    --accent-dark: #e7b800;
    --bg-start: #c7d3e1;
    --bg-end: #9eb0c4;
    --white: #ffffff;
    --text: #182235;
    --muted: #7d8a9f;
    --border: #dfe7f1;
    --input-bg: #f7f9fc;
    --input-focus: #edf5ff;
    --success: #25a869;
    --danger: #dc3545;
    --purple: #6366f1;
    --shadow: 0 20px 45px rgba(36, 69, 111, 0.22);
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
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.38), transparent 36%),
        linear-gradient(135deg, var(--bg-start), var(--bg-end));
}

button,
input,
select {
    font-family: inherit;
}

.app-container {
    min-height: 100vh;
    width: 100%;
    padding: 28px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#login-screen,
#dashboard-screen {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-card-layout,
.dashboard-card-layout {
    width: 100%;
    max-width: 460px;
    overflow: hidden;
    border-radius: 18px;
    background: var(--white);
    box-shadow: var(--shadow);
    border: 1px solid rgba(255, 255, 255, 0.65);
}

.dashboard-card-layout {
    max-width: 520px;
}

.header {
    background: var(--primary);
    color: var(--white);
    min-height: 155px;
    padding: 30px 34px;
    text-align: center;
}

.header .brand-icon {
    width: 82px;
    height: 82px;
    margin: 0 auto 14px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.12);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
}

.header .brand-icon i {
    font-size: 2.7rem;
}

.header h1 {
    margin: 0;
    font-size: 1.42rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    color: var(--white);
}

.header p {
    margin: 8px 0 0;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.84rem;
    font-weight: 600;
}

#dashboard-screen .header {
    min-height: 118px;
    padding: 26px 28px;
}

#dashboard-screen .header h1 {
    font-size: 1.26rem;
}

#dashboard-screen .header p {
    color: rgba(255, 255, 255, 0.82);
}

#dashboard-screen .header i {
    color: var(--accent);
}

.yellow-line {
    width: 100%;
    height: 6px;
    background: var(--accent);
}

.premium-card {
    background: var(--white);
    border: 0;
    border-radius: 0;
    padding: 34px 38px 32px;
    color: var(--text);
}

.dashboard-content {
    padding: 24px;
}

.dashboard-content .premium-card {
    padding: 22px;
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: none;
    margin-top: 16px;
}

.login-title {
    text-align: center;
    margin-bottom: 28px;
}

.login-title h2 {
    margin: 0 0 8px;
    font-size: 1.22rem;
    line-height: 1;
    font-weight: 900;
    color: var(--text);
}

.login-title span {
    color: var(--muted);
    font-size: 0.88rem;
    font-weight: 600;
}

.form-label,
.premium-card label {
    color: #26364f;
    font-size: 0.72rem !important;
    font-weight: 900 !important;
    letter-spacing: 0.04em;
    margin-bottom: 9px;
}

.form-control,
.form-select {
    min-height: 52px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background-color: var(--input-bg);
    color: var(--text);
    font-size: 0.94rem;
    font-weight: 600;
    padding: 0 16px;
    box-shadow: none !important;
    transition: 0.2s ease;
}

.form-control::placeholder {
    color: #8b98ab;
    font-weight: 500;
}

.form-control:focus,
.form-select:focus {
    border-color: rgba(47, 84, 135, 0.55);
    background-color: var(--input-focus);
    box-shadow: 0 0 0 4px rgba(47, 84, 135, 0.09) !important;
}

.form-select {
    cursor: pointer;
}

.password-wrapper {
    position: relative;
}

.password-wrapper .form-control {
    padding-right: 58px;
}

.password-toggle {
    position: absolute;
    top: 1px;
    right: 1px;
    width: 54px;
    height: 50px;
    border: 0;
    border-left: 1px solid var(--border);
    border-radius: 0 9px 9px 0;
    background: var(--input-bg);
    color: #65758c;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: 0.2s ease;
}

.password-toggle:hover {
    color: var(--primary);
    background: var(--input-focus);
}

.btn-premium {
    width: 100%;
    min-height: 54px;
    border: 0;
    border-radius: 8px;
    background: var(--primary);
    color: var(--white);
    font-size: 0.88rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 18px rgba(47, 84, 135, 0.25);
    transition: 0.2s ease;
}

.btn-premium:hover,
.btn-premium:focus {
    background: var(--primary-dark);
    color: var(--white);
    transform: translateY(-1px);
}

.btn-premium:active {
    transform: translateY(0);
}

.btn-purple {
    background: var(--purple) !important;
    box-shadow: 0 8px 18px rgba(99, 102, 241, 0.25) !important;
}

.face-id-btn {
    min-height: 46px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--input-bg);
    color: var(--primary);
    font-size: 0.82rem;
    font-weight: 800;
}

.face-id-btn:hover {
    background: var(--input-focus);
    color: var(--primary-dark);
}

#login-error {
    padding: 10px 12px;
    border-radius: 8px;
    background: rgba(220, 53, 69, 0.08);
    font-weight: 700;
}

.login-footer {
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid #edf1f7;
    text-align: center;
}

.login-footer p {
    margin: 0;
    color: #8c99aa;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.14em;
}

.login-footer p + p {
    margin-top: 4px;
}

.nav-tabs {
    border: 0;
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.nav-tabs .nav-item {
    width: 100%;
}

.nav-tabs .nav-link {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 11px;
    color: var(--primary);
    background: var(--input-bg);
    font-size: 0.82rem;
    font-weight: 900;
    text-transform: uppercase;
    padding: 12px;
    text-align: center;
}

.nav-tabs .nav-link.active {
    color: var(--white);
    background: var(--primary);
    border-color: var(--primary);
}

.tab-content {
    margin-top: 0;
}

.premium-card h5,
.premium-card h6 {
    color: var(--text);
}

.text-info {
    color: var(--primary) !important;
}

.text-secondary {
    color: var(--muted) !important;
}

.generated-area {
    margin-top: 18px;
    padding: 16px;
    border-radius: 14px;
    border: 1px dashed rgba(47, 84, 135, 0.35);
    background: #f8fbff;
    text-align: center;
}

.code-display {
    margin: 8px 0 14px;
    color: var(--primary);
    font-size: 2.1rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: 0.12em;
}

.btn-outline-info {
    border-color: var(--primary);
    color: var(--primary);
    font-weight: 800;
}

.btn-outline-info:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--white);
}

.btn-success {
    background: var(--success);
    border-color: var(--success);
    font-weight: 800;
}

.alert-info {
    background: #e9f3ff;
    border-color: #cce5ff;
    color: #315c91;
    font-weight: 700;
}

.biometric-card {
    background: #fffdf4 !important;
    border: 1px solid #f2df91 !important;
}

.alert-secondary {
    background: #fff8da;
    border-color: #f2df91;
    color: #8a6a00;
    font-weight: 700;
}

.btn-outline-warning {
    border-color: var(--accent-dark);
    color: #7b5f00;
}

.btn-outline-warning:hover {
    background: var(--accent);
    border-color: var(--accent);
    color: #352800;
}

.logout-btn {
    width: 100%;
    min-height: 45px;
    margin-top: 18px;
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
}

.logout-btn:hover {
    background: #e6ebf2;
    color: var(--primary);
}

.extra-small {
    font-size: 0.76rem;
}

@media (max-width: 576px) {
    .app-container {
        padding: 18px 12px;
        align-items: flex-start;
    }

    .login-card-layout,
    .dashboard-card-layout {
        border-radius: 16px;
    }

    .header {
        min-height: 140px;
        padding: 24px 22px;
    }

    .header .brand-icon {
        width: 72px;
        height: 72px;
        border-radius: 21px;
    }

    .header .brand-icon i {
        font-size: 2.25rem;
    }

    .premium-card {
        padding: 30px 24px 26px;
    }

    .dashboard-content {
        padding: 18px;
    }

    .dashboard-content .premium-card {
        padding: 18px;
    }

    #dashboard-screen .header {
        padding: 22px 20px;
    }

    #dashboard-screen .header h1 {
        font-size: 1.08rem;
    }

    .code-display {
        font-size: 1.75rem;
    }

    .d-flex.gap-2 {
        flex-direction: column;
    }
}
</style>

<body>
    <div class="app-container">
        <?php if (!$isLoggedIn): ?>

            <!-- Login Screen -->
            <div id="login-screen">
                <div class="login-card-layout">

                    <div class="header">
                        <div class="brand-icon">
                            <img src="../../erp_eletrica/logo_sistema_erp_eletrica.PNG" alt="">
                        </div>

                        <h1>ADM SHIELD</h1>
                        <p>Controle de Acessos e Autorizações</p>
                    </div>

                    <div class="yellow-line"></div>

                    <div class="premium-card">
                        <div class="login-title">
                            <h2>LOGIN</h2>
                            <span>Identifique-se para continuar</span>
                        </div>

                        <form id="login-form">
                            <div class="mb-3">
                                <label class="form-label small text-uppercase fw-bold opacity-75">
                                    Email Administrativo
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    class="form-control"
                                    placeholder="seu-email@adm.com"
                                    required
                                    autocomplete="username webauthn">
                            </div>

                            <div class="mb-4">
                                <label class="form-label small text-uppercase fw-bold opacity-75">
                                    Senha Mestra
                                </label>

                                <div class="password-wrapper">
                                    <input
                                        type="password"
                                        id="password"
                                        class="form-control"
                                        placeholder="••••••••"
                                        required
                                        autocomplete="current-password">

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="togglePasswordField()">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-premium mb-3">
                                Entrar
                                <i class="fas fa-chevron-right ms-2 mt-1"></i>
                            </button>

                            <div id="biometric-login" style="display: none;">
                                <button
                                    type="button"
                                    class="btn face-id-btn w-100"
                                    onclick="tryBiometricLogin()">
                                    <i class="fas fa-fingerprint me-2"></i>
                                    Desbloquear com Biometria
                                </button>
                            </div>
                        </form>

                        <div
                            id="login-error"
                            class="text-danger small mt-2 text-center"
                            style="display:none;"></div>

                        <div class="login-footer">
                            <p>Desenvolvido por L&amp;J Soluções Tecnológicas.</p>
                            <p>ERP Elétrica © <?= date('Y') ?></p>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <!-- Dashboard Screen -->
            <div id="dashboard-screen">
                <div class="dashboard-card-layout">

                    <div class="header d-flex align-items-center justify-content-between text-start pb-2">
                        <div>
                            <p class="mb-0 small text-uppercase fw-bold" style="letter-spacing: 1px">
                                Bem-vindo, Adm
                            </p>

                            <h1 class="mt-0 fw-bold">
                                <?= htmlspecialchars($usuarioNome, ENT_QUOTES, 'UTF-8') ?>
                            </h1>
                        </div>

                        <i class="fas fa-user-circle fa-2x opacity-50"></i>
                    </div>

                    <div class="yellow-line"></div>

                    <div class="dashboard-content">

                        <!-- Tabs -->
                        <ul class="nav nav-tabs d-flex" id="admTabs">
                            <li class="nav-item flex-fill">
                                <a
                                    class="nav-link active"
                                    id="tab-codes"
                                    data-bs-toggle="tab"
                                    href="#codes-section">
                                    Códigos
                                </a>
                            </li>

                            <li class="nav-item flex-fill">
                                <a
                                    class="nav-link"
                                    id="tab-logins"
                                    data-bs-toggle="tab"
                                    href="#logins-section">
                                    Logins
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">

                            <!-- Tab Codes -->
                            <div class="tab-pane fade show active" id="codes-section">
                                <div class="premium-card">
                                    <h5 class="fw-bold mb-4">
                                        <i class="fas fa-key me-2 text-info"></i>
                                        Gerar Autorização
                                    </h5>

                                    <div class="mb-3">
                                        <label class="small text-uppercase opacity-75">
                                            Tipo de Operação
                                        </label>

                                        <select id="code-type" class="form-select mt-1">
                                            <option value="geral">Qualquer</option>
                                            <option value="sangria">Sangria</option>
                                            <option value="suprimento">Suprimento</option>
                                            <option value="desconto">Desconto</option>
                                            <option value="cancelamento">Cancelamento</option>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label class="small text-uppercase opacity-75">
                                            Unidade Destino
                                        </label>

                                        <select id="code-filial" class="form-select mt-1">
                                            <!-- Populated by JS -->
                                        </select>
                                    </div>

                                    <button onclick="generateCode()" class="btn btn-premium">
                                        Gerar Código Unico
                                    </button>

                                    <div
                                        id="code-result"
                                        class="generated-area"
                                        style="display: none;">
                                        <p class="small text-uppercase opacity-50 mb-0">
                                            Código Gerado
                                        </p>

                                        <div class="code-display" id="display-code">
                                            ------
                                        </div>

                                        <div class="d-flex gap-2 justify-content-center mt-2">
                                            <button
                                                class="btn btn-sm btn-outline-info flex-fill"
                                                onclick="copyToClipboard('display-code')">
                                                <i class="fas fa-copy me-1"></i>
                                                COPIAR
                                            </button>

                                            <button
                                                class="btn btn-sm btn-success flex-fill"
                                                onclick="shareToWhatsApp('code')">
                                                <i class="fab fa-whatsapp me-1"></i>
                                                ENVIAR
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab Logins -->
                            <div class="tab-pane fade" id="logins-section">
                                <div class="premium-card">
                                    <h5 class="fw-bold mb-4">
                                        <i class="fas fa-user-shield me-2 text-info"></i>
                                        Login Temporário
                                    </h5>

                                    <p class="small text-secondary mb-4">
                                        Cria um login "Master" que expira automaticamente.
                                    </p>

                                    <div class="mb-3">
                                        <label class="small text-uppercase opacity-75">
                                            Tempo de Acesso
                                        </label>

                                        <select id="temp-time" class="form-select mt-1">
                                            <option value="30">30 Minutos</option>
                                            <option value="60" selected>1 Hora</option>
                                            <option value="240">4 Horas</option>
                                            <option value="480">8 Horas</option>
                                            <option value="720">12 Horas</option>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label class="small text-uppercase opacity-75">
                                            Unidade (Filial)
                                        </label>

                                        <select id="temp-filial" class="form-select mt-1">
                                            <!-- Populated by JS -->
                                        </select>
                                    </div>

                                    <button
                                        onclick="generateTempLogin()"
                                        class="btn btn-premium btn-purple">
                                        Criar Acesso Especial
                                    </button>

                                    <div
                                        id="temp-result"
                                        class="generated-area mt-4 text-start"
                                        style="display: none;">
                                        <div class="alert alert-info py-2" style="font-size: 0.75rem">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Este acesso libera tudo do sistema.
                                        </div>

                                        <div class="mb-2">
                                            <label class="small text-uppercase opacity-50">
                                                Usuário:
                                            </label>

                                            <div class="fw-bold" id="display-user">
                                                -
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <label class="small text-uppercase opacity-50">
                                                Senha:
                                            </label>

                                            <div class="fw-bold" id="display-pass">
                                                -
                                            </div>
                                        </div>

                                        <div class="mb-0">
                                            <label class="small text-uppercase opacity-50">
                                                Válido até:
                                            </label>

                                            <div class="small fw-bold text-info" id="display-time">
                                                -
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 mt-3">
                                            <button
                                                class="btn btn-sm btn-outline-info flex-fill"
                                                onclick="copyLogin()">
                                                <i class="fas fa-copy me-1"></i>
                                                COPIAR TUDO
                                            </button>

                                            <button
                                                class="btn btn-sm btn-success flex-fill"
                                                onclick="shareToWhatsApp('login')">
                                                <i class="fab fa-whatsapp me-1"></i>
                                                ENVIAR WHATSAPP
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Configurações de Biometria -->
                                <div class="premium-card mt-3 biometric-card">
                                    <h6 class="fw-bold mb-3 text-warning">
                                        <i class="fas fa-fingerprint me-2"></i>
                                        Biometria / FaceID
                                    </h6>

                                    <p class="extra-small text-secondary mb-3">
                                        Vincule a biometria nativa deste celular para acessos rápidos.
                                    </p>

                                    <div
                                        id="biometrics-status"
                                        class="alert alert-secondary py-2 extra-small mb-3"
                                        style="font-size: 0.75rem">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Biometria não configurada.
                                    </div>

                                    <button
                                        onclick="registerBiometrics()"
                                        id="btn-register-bio"
                                        class="btn btn-sm btn-outline-warning w-100 fw-bold">
                                        <i class="fas fa-plus-circle me-1"></i>
                                        CONFIGURAR NESTE CELULAR
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button class="logout-btn" onclick="logout()">
                                <i class="fas fa-sign-out-alt me-1"></i>
                                SAIR DA CONTA ADM
                            </button>
                        </div>

                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
    </script>

    <script src="script.js"></script>

    <script>
        function togglePasswordField() {
            const input = document.getElementById('password');
            const icon = document.querySelector('.password-toggle i');

            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';

                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                input.type = 'password';

                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }
    </script>
</body>

</html>