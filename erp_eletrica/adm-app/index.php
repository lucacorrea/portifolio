<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$isLoggedIn = isset($_SESSION['usuario_id'])
    && (int) $_SESSION['usuario_id'] !== -1
    && strtolower((string) ($_SESSION['usuario_nivel'] ?? '')) === 'admin';

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Administrador';

/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
| Coloque sua logo neste caminho:
| assets/img/logo-centro-eletricista.png
|--------------------------------------------------------------------------
*/
$logoPath = 'assets/img/logo-centro-eletricista.png';

/*
|--------------------------------------------------------------------------
| UNIDADES
|--------------------------------------------------------------------------
| Se existir tabela filiais, ele tenta puxar do banco.
| Se não existir, usa as unidades fixas abaixo.
|--------------------------------------------------------------------------
*/
$filiais = [
    ['id' => 'matriz', 'nome' => 'Centro do Eletricista'],
    ['id' => 'loja-01', 'nome' => 'Loja 01'],
    ['id' => 'loja-02', 'nome' => 'Loja 02'],
];

$db = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
} elseif (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
} elseif (isset($conexao) && $conexao instanceof PDO) {
    $db = $conexao;
}

if ($db instanceof PDO) {
    try {
        $stmt = $db->query("
            SELECT id, nome 
            FROM filiais 
            WHERE ativo = 1 
            ORDER BY nome ASC
        ");

        $filiaisBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($filiaisBanco)) {
            $filiais = $filiaisBanco;
        }
    } catch (Throwable $e) {
        // Se não existir tabela filiais, mantém as unidades fixas.
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Login - ERP Elétrica</title>

    <link rel="manifest" href="manifest.json">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <?php
    $cssFile = __DIR__ . '/style.css';
    $cssVersion = file_exists($cssFile) ? filemtime($cssFile) : time();
    ?>

    <link rel="stylesheet" href="/erp_eletrica/style.css?v=<?= $cssVersion ?>">

    <meta name="theme-color" content="#2f5487">
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
        radial-gradient(circle at top left, rgba(255,255,255,0.35), transparent 35%),
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

.login-footer p + p {
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

.section-title > i {
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
    <main class="page-auth">
        <section class="auth-shell">

            <?php if (!$isLoggedIn): ?>

                <div class="login-card">
                    <div class="login-brand">
                        <div class="brand-logo-wrap">
                            <img
                                src="<?= h($logoPath) ?>"
                                alt="Centro do Eletricista"
                                class="brand-logo">
                        </div>
                    </div>

                    <div class="brand-line"></div>

                    <div class="login-body">
                        <div class="login-title-box">
                            <h1>LOGIN</h1>
                            <p>Identifique-se para continuar</p>
                        </div>

                        <form id="login-form" method="post" autocomplete="off">
                            <div class="form-group-ce">
                                <label for="login-unit">Unidade de acesso</label>

                                <select
                                    id="login-unit"
                                    name="filial_id"
                                    class="form-control-ce"
                                    required>
                                    <option value="">Selecionar unidade...</option>

                                    <?php foreach ($filiais as $filial): ?>
                                        <option value="<?= h($filial['id']) ?>">
                                            <?= h($filial['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group-ce">
                                <label for="email">ID ou e-mail corporativo</label>

                                <input
                                    type="text"
                                    id="email"
                                    name="email"
                                    class="form-control-ce"
                                    placeholder="Digite seu usuário ou e-mail"
                                    required
                                    autocomplete="username">
                            </div>

                            <div class="form-group-ce">
                                <label for="password">Senha técnica</label>

                                <div class="password-field">
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control-ce password-input"
                                        placeholder="Digite sua senha"
                                        required
                                        autocomplete="current-password">

                                    <button
                                        type="button"
                                        class="btn-password-toggle"
                                        id="toggle-password"
                                        aria-label="Mostrar ou ocultar senha">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn-access">
                                <span>Confirmar acesso</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>

                            <div id="biometric-login" class="biometric-box">
                                <button
                                    type="button"
                                    class="btn-biometric"
                                    onclick="tryBiometricLogin()">
                                    <i class="fa-solid fa-fingerprint"></i>
                                    Desbloquear com biometria
                                </button>
                            </div>

                            <div id="login-error" class="login-error"></div>
                        </form>

                        <a href="admin_login.php" class="admin-link">
                            <i class="fa-solid fa-key"></i>
                            Área do Administrador
                        </a>

                        <div class="login-footer">
                            <p>Desenvolvido por L&amp;J Soluções Tecnológicas.</p>
                            <p>ERP Elétrica © <?= date('Y') ?></p>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <div class="dashboard-card">
                    <div class="dashboard-brand">
                        <div class="brand-logo-wrap">
                            <img
                                src="<?= h($logoPath) ?>"
                                alt="Centro do Eletricista"
                                class="brand-logo">
                        </div>
                    </div>

                    <div class="brand-line"></div>

                    <div class="dashboard-body">
                        <div class="dashboard-header">
                            <div>
                                <span>Painel Administrativo</span>
                                <h1><?= h($usuarioNome) ?></h1>
                                <p>Controle de acessos e autorizações</p>
                            </div>

                            <div class="dashboard-avatar">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                        </div>

                        <div class="quick-grid">
                            <article class="quick-card">
                                <i class="fa-solid fa-key"></i>
                                <strong>Códigos</strong>
                                <span>Autorizações rápidas</span>
                            </article>

                            <article class="quick-card">
                                <i class="fa-solid fa-user-clock"></i>
                                <strong>Temporário</strong>
                                <span>Acesso especial</span>
                            </article>
                        </div>

                        <ul class="nav nav-pills adm-tabs" id="admTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link active"
                                    id="tab-codes"
                                    data-bs-toggle="pill"
                                    data-bs-target="#codes-section"
                                    type="button"
                                    role="tab">
                                    <i class="fa-solid fa-key"></i>
                                    Códigos
                                </button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link"
                                    id="tab-logins"
                                    data-bs-toggle="pill"
                                    data-bs-target="#logins-section"
                                    type="button"
                                    role="tab">
                                    <i class="fa-solid fa-user-shield"></i>
                                    Logins
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content adm-tab-content">
                            <div
                                class="tab-pane fade show active"
                                id="codes-section"
                                role="tabpanel">
                                <div class="section-card">
                                    <div class="section-title">
                                        <i class="fa-solid fa-lock"></i>

                                        <div>
                                            <h2>Gerar autorização</h2>
                                            <p>Crie um código único para liberar operações.</p>
                                        </div>
                                    </div>

                                    <div class="form-group-ce">
                                        <label for="code-type">Tipo de operação</label>

                                        <select id="code-type" class="form-control-ce">
                                            <option value="geral">Qualquer operação</option>
                                            <option value="sangria">Sangria</option>
                                            <option value="suprimento">Suprimento</option>
                                            <option value="desconto">Desconto</option>
                                            <option value="cancelamento">Cancelamento</option>
                                        </select>
                                    </div>

                                    <div class="form-group-ce">
                                        <label for="code-filial">Unidade destino</label>

                                        <select id="code-filial" class="form-control-ce">
                                            <?php foreach ($filiais as $filial): ?>
                                                <option value="<?= h($filial['id']) ?>">
                                                    <?= h($filial['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button
                                        type="button"
                                        onclick="generateCode()"
                                        class="btn-access">
                                        <span>Gerar código único</span>
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>

                                    <div id="code-result" class="generated-area">
                                        <p>Código gerado</p>

                                        <div class="code-display" id="display-code">
                                            ------
                                        </div>

                                        <div class="action-row">
                                            <button
                                                type="button"
                                                class="btn-outline-ce"
                                                onclick="copyToClipboard('display-code')">
                                                <i class="fa-regular fa-copy"></i>
                                                Copiar
                                            </button>

                                            <button
                                                type="button"
                                                class="btn-whatsapp"
                                                onclick="shareToWhatsApp('code')">
                                                <i class="fa-brands fa-whatsapp"></i>
                                                Enviar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="tab-pane fade"
                                id="logins-section"
                                role="tabpanel">
                                <div class="section-card">
                                    <div class="section-title">
                                        <i class="fa-solid fa-user-clock"></i>

                                        <div>
                                            <h2>Login temporário</h2>
                                            <p>Crie um acesso master com expiração automática.</p>
                                        </div>
                                    </div>

                                    <div class="form-group-ce">
                                        <label for="temp-time">Tempo de acesso</label>

                                        <select id="temp-time" class="form-control-ce">
                                            <option value="30">30 minutos</option>
                                            <option value="60" selected>1 hora</option>
                                            <option value="240">4 horas</option>
                                            <option value="480">8 horas</option>
                                            <option value="720">12 horas</option>
                                        </select>
                                    </div>

                                    <div class="form-group-ce">
                                        <label for="temp-filial">Unidade filial</label>

                                        <select id="temp-filial" class="form-control-ce">
                                            <?php foreach ($filiais as $filial): ?>
                                                <option value="<?= h($filial['id']) ?>">
                                                    <?= h($filial['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button
                                        type="button"
                                        onclick="generateTempLogin()"
                                        class="btn-access btn-access-purple">
                                        <span>Criar acesso especial</span>
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>

                                    <div id="temp-result" class="generated-area generated-login">
                                        <div class="info-alert">
                                            <i class="fa-solid fa-circle-info"></i>
                                            Este acesso libera permissões administrativas temporárias.
                                        </div>

                                        <div class="login-result-row">
                                            <span>Usuário</span>
                                            <strong id="display-user">-</strong>
                                        </div>

                                        <div class="login-result-row">
                                            <span>Senha</span>
                                            <strong id="display-pass">-</strong>
                                        </div>

                                        <div class="login-result-row">
                                            <span>Válido até</span>
                                            <strong id="display-time">-</strong>
                                        </div>

                                        <div class="action-row">
                                            <button
                                                type="button"
                                                class="btn-outline-ce"
                                                onclick="copyLogin()">
                                                <i class="fa-regular fa-copy"></i>
                                                Copiar tudo
                                            </button>

                                            <button
                                                type="button"
                                                class="btn-whatsapp"
                                                onclick="shareToWhatsApp('login')">
                                                <i class="fa-brands fa-whatsapp"></i>
                                                WhatsApp
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="section-card biometric-section">
                                    <div class="section-title">
                                        <i class="fa-solid fa-fingerprint"></i>

                                        <div>
                                            <h2>Biometria / FaceID</h2>
                                            <p>Vincule este aparelho para acessos rápidos.</p>
                                        </div>
                                    </div>

                                    <div id="biometrics-status" class="info-alert warning">
                                        <i class="fa-solid fa-circle-info"></i>
                                        Biometria não configurada.
                                    </div>

                                    <button
                                        type="button"
                                        onclick="registerBiometrics()"
                                        id="btn-register-bio"
                                        class="btn-outline-warning-ce">
                                        <i class="fa-solid fa-plus-circle"></i>
                                        Configurar neste celular
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="logout-btn" onclick="logout()">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Sair da conta ADM
                        </button>

                        <div class="login-footer dashboard-footer">
                            <p>Desenvolvido por L&amp;J Soluções Tecnológicas.</p>
                            <p>ERP Elétrica © <?= date('Y') ?></p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </section>
    </main>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
    </script>

    <script src="script.js"></script>
</body>

</html>