<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;

require_once __DIR__ . '/bootstrap.php';

$pdo = Database::connection();

$authService = new AuthService(
    new UserRepository($pdo),
    new UserSessionRepository($pdo),
    new AccessLevelRepository($pdo),
    new AuditService(
        new AuditLogRepository($pdo)
    )
);

if ($authService->currentUser() !== null) {
    header('Location: dashboard.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $identity = isset($_POST['identity']) && is_string($_POST['identity'])
        ? trim($_POST['identity'])
        : '';

    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf'])
        ? $_POST['_csrf']
        : null;

    if (!Csrf::validateAndConsume($token, 'login')) {
        Logger::security('Invalid CSRF token on login.');

        Session::flash(
            'login_error',
            'Requisição inválida. Atualize a página e tente novamente.'
        );

        Session::flash('login_identity', $identity);

        header('Location: index.php');
        exit;
    }

    try {
        $authService->attempt($identity, $password);

        header('Location: dashboard.php');
        exit;
    } catch (AuthenticationException $exception) {
        Session::flash('login_error', $exception->getMessage());
        Session::flash('login_identity', $identity);

        header('Location: index.php');
        exit;
    }
}

$loginError = Session::flash('login_error');
$loginIdentity = Session::flash('login_identity') ?? '';

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <meta
        name="description"
        content="Acesso institucional ao SIGAS Coari.">

    <meta name="theme-color" content="#14532d">

    <title>SIGAS Coari — Acesso</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <style>
        :root {
            --primary: #14532d;
            --primary-hover: #0f4425;
            --primary-soft: #e9f5ed;

            --background: #edf1ef;
            --surface: #ffffff;
            --surface-soft: #f7f9f8;

            --text: #17221c;
            --text-secondary: #66736b;
            --text-muted: #8b958f;

            --border: #e2e8e4;
            --border-strong: #cfd9d2;

            --danger: #b42318;
            --danger-soft: #fff1ef;
            --danger-border: #f2c9c5;

            --shadow: 0 20px 55px rgba(21, 45, 30, 0.10);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        body {
            min-width: 320px;
            color: var(--text);
            font-family:
                "Inter",
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            background:
                radial-gradient(
                    circle at top,
                    rgba(255, 255, 255, 0.92),
                    transparent 42%
                ),
                var(--background);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        button,
        input {
            font: inherit;
        }

        a {
            color: inherit;
        }

        :focus-visible {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.16);
        }

        .sigas-skip-link {
            position: fixed;
            top: 14px;
            left: -9999px;
            z-index: 9999;
            padding: 10px 14px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            background: var(--primary);
            border-radius: 10px;
        }

        .sigas-skip-link:focus {
            left: 14px;
        }

        .sigas-auth-page {
            width: 100%;
            height: 100dvh;
            display: grid;
            place-items: center;
            padding: 18px;
        }

        .sigas-auth-shell {
            width: min(100%, 420px);
        }

        .sigas-login-card {
            position: relative;
            overflow: hidden;
            padding: 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow);
        }

        .sigas-login-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 4px;
            background: var(--primary);
        }

        .sigas-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 11px;
            margin-bottom: 22px;
            text-decoration: none;
        }

        .sigas-brand img {
            width: 42px;
            height: 48px;
            object-fit: contain;
        }

        .sigas-brand-copy strong {
            display: block;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.03em;
        }

        .sigas-brand-copy span {
            display: block;
            margin-top: 3px;
            color: var(--text-secondary);
            font-size: 9px;
            line-height: 1.35;
        }

        .sigas-login-heading {
            margin-bottom: 22px;
            text-align: center;
        }

        .sigas-login-heading h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 780;
            line-height: 1.18;
            letter-spacing: -0.04em;
        }

        .sigas-login-heading p {
            margin: 7px 0 0;
            color: var(--text-secondary);
            font-size: 12px;
            line-height: 1.5;
        }

        .sigas-login-form {
            display: grid;
            gap: 15px;
        }

        .sigas-form-group {
            min-width: 0;
        }

        .sigas-form-group label,
        .sigas-label-row label {
            display: block;
            margin-bottom: 7px;
            color: #3f4b44;
            font-size: 11px;
            font-weight: 700;
        }

        .sigas-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 7px;
        }

        .sigas-label-row label {
            margin: 0;
        }

        .sigas-forgot-link {
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
        }

        .sigas-forgot-link:hover {
            text-decoration: underline;
        }

        .sigas-input-shell {
            position: relative;
        }

        .sigas-input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            z-index: 2;
            display: grid;
            place-items: center;
            color: var(--primary);
            font-size: 14px;
            pointer-events: none;
            transform: translateY(-50%);
        }

        .sigas-input-shell input {
            width: 100%;
            height: 46px;
            padding: 10px 44px 10px 41px;
            color: var(--text);
            font-size: 12px;
            font-weight: 500;
            background: #fff;
            border: 1px solid var(--border-strong);
            border-radius: 11px;
            outline: 0;
            transition:
                border-color 0.18s ease,
                box-shadow 0.18s ease,
                background 0.18s ease;
        }

        .sigas-input-shell input::placeholder {
            color: #9aa39d;
        }

        .sigas-input-shell input:hover {
            border-color: #bcc9c1;
        }

        .sigas-input-shell input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.10);
        }

        .sigas-input-shell input[aria-invalid="true"] {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(180, 35, 24, 0.08);
        }

        .sigas-password-toggle {
            position: absolute;
            top: 50%;
            right: 7px;
            z-index: 3;
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            padding: 0;
            color: var(--text-muted);
            background: transparent;
            border: 0;
            border-radius: 9px;
            cursor: pointer;
            transform: translateY(-50%);
            transition:
                color 0.18s ease,
                background 0.18s ease;
        }

        .sigas-password-toggle:hover,
        .sigas-password-toggle:focus-visible {
            color: var(--primary);
            background: var(--primary-soft);
        }

        .sigas-field-error,
        .sigas-caps-warning {
            display: none;
            align-items: center;
            gap: 5px;
            margin: 7px 3px 0;
            font-size: 10px;
            line-height: 1.35;
        }

        .sigas-field-error {
            color: var(--danger);
        }

        .sigas-caps-warning {
            color: #8a5b05;
        }

        .sigas-form-group.is-invalid .sigas-field-error,
        .sigas-caps-warning:not([hidden]) {
            display: flex;
        }

        .sigas-login-feedback {
            display: none;
            grid-template-columns: auto 1fr;
            align-items: flex-start;
            gap: 8px;
            margin: 0;
            padding: 10px 11px;
            color: var(--danger);
            font-size: 10px;
            line-height: 1.45;
            background: var(--danger-soft);
            border: 1px solid var(--danger-border);
            border-radius: 10px;
        }

        .sigas-login-feedback.is-error {
            display: grid;
        }

        .sigas-login-submit {
            width: 100%;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 1px;
            padding: 10px 16px;
            color: #fff;
            font-size: 12px;
            font-weight: 750;
            background: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 11px;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(20, 83, 45, 0.16);
            transition:
                background 0.18s ease,
                transform 0.18s ease,
                box-shadow 0.18s ease;
        }

        .sigas-login-submit:hover {
            background: var(--primary-hover);
            box-shadow: 0 10px 22px rgba(20, 83, 45, 0.20);
            transform: translateY(-1px);
        }

        .sigas-login-submit:active {
            transform: translateY(0);
        }

        .sigas-login-submit:disabled {
            cursor: wait;
            opacity: 0.76;
            transform: none;
        }

        .sigas-submit-icon {
            display: inline-grid;
            place-items: center;
            font-size: 15px;
        }

        .sigas-submit-loading {
            display: none;
            align-items: center;
            gap: 8px;
        }

        .sigas-login-submit.is-loading .sigas-submit-label,
        .sigas-login-submit.is-loading .sigas-submit-icon {
            display: none;
        }

        .sigas-login-submit.is-loading .sigas-submit-loading {
            display: inline-flex;
        }

        .sigas-spinner {
            width: 15px;
            height: 15px;
            border: 2px solid rgba(255, 255, 255, 0.34);
            border-top-color: #fff;
            border-radius: 50%;
            animation: sigas-spin 0.7s linear infinite;
        }

        @keyframes sigas-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .sigas-login-support {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 18px;
            color: var(--text-secondary);
            font-size: 10px;
        }

        .sigas-login-support a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .sigas-login-support a:hover {
            text-decoration: underline;
        }

        .sigas-login-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 18px;
            padding-top: 16px;
            color: var(--text-muted);
            font-size: 9px;
            line-height: 1.4;
            text-align: center;
            border-top: 1px solid var(--border);
        }

        .sigas-login-footer i {
            color: var(--primary);
        }

        .sigas-toast-container {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 9999;
            display: grid;
            gap: 10px;
        }

        .sigas-demo-toast {
            width: min(350px, calc(100vw - 32px));
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 10px;
            padding: 12px 13px;
            color: var(--text);
            font-size: 11px;
            line-height: 1.45;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 16px 42px rgba(25, 33, 29, 0.14);
        }

        .sigas-demo-toast > i {
            color: var(--primary);
        }

        .sigas-demo-toast button {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            padding: 0;
            color: var(--text-secondary);
            background: var(--surface-soft);
            border: 0;
            border-radius: 9px;
            cursor: pointer;
        }

        @media (max-width: 480px) {
            .sigas-auth-page {
                padding: 10px;
            }

            .sigas-login-card {
                padding: 23px 20px;
                border-radius: 18px;
            }

            .sigas-brand {
                margin-bottom: 18px;
            }

            .sigas-login-heading {
                margin-bottom: 18px;
            }

            .sigas-login-heading h1 {
                font-size: 22px;
            }

            .sigas-login-heading p {
                font-size: 11px;
            }
        }

        @media (max-height: 690px) {
            .sigas-auth-page {
                padding: 8px;
            }

            .sigas-login-card {
                padding: 20px 24px;
            }

            .sigas-brand {
                margin-bottom: 14px;
            }

            .sigas-brand img {
                width: 36px;
                height: 42px;
            }

            .sigas-login-heading {
                margin-bottom: 16px;
            }

            .sigas-login-heading h1 {
                font-size: 21px;
            }

            .sigas-login-form {
                gap: 12px;
            }

            .sigas-login-support {
                margin-top: 14px;
            }

            .sigas-login-footer {
                margin-top: 14px;
                padding-top: 12px;
            }
        }

        @media (max-height: 570px) {
            .sigas-brand-copy span,
            .sigas-login-heading p,
            .sigas-login-footer {
                display: none;
            }

            .sigas-login-card {
                padding: 16px 22px;
            }

            .sigas-brand {
                margin-bottom: 10px;
            }

            .sigas-login-heading {
                margin-bottom: 12px;
            }

            .sigas-login-form {
                gap: 10px;
            }

            .sigas-login-support {
                margin-top: 10px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body data-page="login">
    <a class="sigas-skip-link" href="#loginForm">
        Ir para o formulário de acesso
    </a>

    <main
        class="sigas-auth-page"
        aria-labelledby="loginTitle">

        <div class="sigas-auth-shell">
            <section
                class="sigas-login-card"
                aria-label="Acesso ao SIGAS Coari">

                <a
                    class="sigas-brand"
                    href="index.php"
                    aria-label="SIGAS Coari — Página de acesso">

                    <img
                        src="assets/img/brasao-placeholder.svg"
                        alt="Brasão institucional do SIGAS Coari">

                    <span class="sigas-brand-copy">
                        <strong>SIGAS COARI</strong>

                        <span>
                            Secretaria Municipal de Assistência Social
                        </span>
                    </span>
                </a>

                <header class="sigas-login-heading">
                    <h1 id="loginTitle">
                        Acesso ao sistema
                    </h1>

                    <p>
                        Informe seus dados para continuar.
                    </p>
                </header>

                <form
                    id="loginForm"
                    class="sigas-login-form"
                    method="post"
                    action="index.php"
                    novalidate>

                    <?= Csrf::input('login') ?>

                    <div
                        id="loginFeedback"
                        class="sigas-login-feedback<?= is_string($loginError) && $loginError !== '' ? ' is-error' : '' ?>"
                        role="alert"
                        aria-live="polite">

                        <?php if (is_string($loginError) && $loginError !== ''): ?>
                            <i
                                class="bi bi-exclamation-circle"
                                aria-hidden="true"></i>

                            <span><?= e($loginError) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="sigas-form-group">
                        <label for="loginIdentity">
                            CPF ou e-mail
                        </label>

                        <div class="sigas-input-shell">
                            <span
                                class="sigas-input-icon"
                                aria-hidden="true">

                                <i class="bi bi-person"></i>
                            </span>

                            <input
                                id="loginIdentity"
                                name="identity"
                                type="text"
                                autocomplete="username"
                                inputmode="email"
                                placeholder="Digite seu CPF ou e-mail"
                                required
                                minlength="5"
                                value="<?= e($loginIdentity) ?>"
                                aria-describedby="identityError">
                        </div>

                        <p
                            id="identityError"
                            class="sigas-field-error">

                            <i
                                class="bi bi-exclamation-circle"
                                aria-hidden="true"></i>

                            Informe um CPF ou e-mail válido.
                        </p>
                    </div>

                    <div class="sigas-form-group">
                        <div class="sigas-label-row">
                            <label for="loginPassword">
                                Senha
                            </label>

                            <a
                                href="#"
                                class="sigas-forgot-link"
                                data-demo-action="recuperação de senha">

                                Esqueci minha senha
                            </a>
                        </div>

                        <div class="sigas-input-shell">
                            <span
                                class="sigas-input-icon"
                                aria-hidden="true">

                                <i class="bi bi-lock"></i>
                            </span>

                            <input
                                id="loginPassword"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="Digite sua senha"
                                required
                                minlength="8"
                                aria-describedby="passwordError capsLockWarning">

                            <button
                                class="sigas-password-toggle"
                                id="passwordToggle"
                                type="button"
                                aria-label="Mostrar senha"
                                aria-pressed="false">

                                <i
                                    class="bi bi-eye"
                                    aria-hidden="true"></i>
                            </button>
                        </div>

                        <p
                            id="capsLockWarning"
                            class="sigas-caps-warning"
                            role="status"
                            aria-live="polite"
                            hidden>

                            <i
                                class="bi bi-exclamation-triangle"
                                aria-hidden="true"></i>

                            Caps Lock está ativado.
                        </p>

                        <p
                            id="passwordError"
                            class="sigas-field-error">

                            <i
                                class="bi bi-exclamation-circle"
                                aria-hidden="true"></i>

                            Informe uma senha com pelo menos oito caracteres.
                        </p>
                    </div>

                    <button
                        class="sigas-login-submit"
                        id="loginSubmit"
                        type="submit">

                        <span class="sigas-submit-label">
                            Entrar
                        </span>

                        <span
                            class="sigas-submit-icon"
                            aria-hidden="true">

                            <i class="bi bi-arrow-right"></i>
                        </span>

                        <span
                            class="sigas-submit-loading"
                            aria-hidden="true">

                            <span class="sigas-spinner"></span>
                            Verificando...
                        </span>
                    </button>
                </form>

                <div class="sigas-login-support">
                    <span>Problemas para acessar?</span>

                    <a
                        href="#"
                        data-demo-action="suporte técnico">

                        Falar com o suporte
                    </a>
                </div>

                <footer class="sigas-login-footer">
                    <i
                        class="bi bi-shield-check"
                        aria-hidden="true"></i>

                    <span>
                        Ambiente institucional protegido e monitorado
                    </span>
                </footer>
            </section>
        </div>
    </main>

    <div
        class="sigas-toast-container"
        id="toastContainer"
        aria-live="polite"
        aria-atomic="true">
    </div>

    <script src="assets/js/login.js?v=20260703"></script>
</body>
</html>
