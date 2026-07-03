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
            --primary-dark: #0b351c;
            --primary-soft: #e7f3eb;

            --accent: #d99b27;
            --accent-soft: #fff0c9;

            --background: #e9eeeb;
            --canvas: #f8f7f2;
            --surface: #ffffff;
            --surface-soft: #f6f8f7;

            --text: #17221c;
            --text-secondary: #67736c;
            --text-muted: #929b95;

            --border: #e3e8e5;
            --border-strong: #d3dcd6;

            --danger: #b42318;
            --danger-soft: #fff1ef;
            --danger-border: #f2c9c5;

            --shadow:
                0 28px 70px rgba(23, 42, 31, 0.12);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            min-height: 100%;
            background: var(--background);
        }

        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            font-family:
                "Inter",
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            background: var(--background);
            -webkit-font-smoothing: antialiased;
        }

        button,
        input {
            font: inherit;
        }

        a {
            color: inherit;
        }

        .skip-link {
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

        .skip-link:focus {
            left: 14px;
        }

        :focus-visible {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.16);
        }

        .login-page {
            min-height: 100vh;
            padding: 28px;
        }

        .login-canvas {
            position: relative;
            min-height: calc(100vh - 56px);
            overflow: hidden;
            background:
                radial-gradient(
                    circle at 50% 38%,
                    rgba(255, 255, 255, 0.96),
                    rgba(248, 247, 242, 0.8) 48%,
                    rgba(244, 243, 236, 0.96)
                );
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 28px;
            box-shadow: 0 12px 42px rgba(26, 43, 33, 0.08);
        }

        .login-header {
            position: relative;
            z-index: 10;
            min-height: 88px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 44px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--text);
            text-decoration: none;
        }

        .brand img {
            width: 40px;
            height: 46px;
            object-fit: contain;
        }

        .brand-copy strong {
            display: block;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .brand-copy span {
            display: block;
            margin-top: 3px;
            color: var(--text-secondary);
            font-size: 10px;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 13px;
            color: var(--primary);
            font-size: 10px;
            font-weight: 750;
            background: var(--primary-soft);
            border: 1px solid #d2e5d8;
            border-radius: 999px;
        }

        .login-stage {
            position: relative;
            z-index: 5;
            min-height: calc(100vh - 200px);
            display: grid;
            place-items: center;
            padding: 28px 24px 54px;
        }

        .login-card {
            position: relative;
            z-index: 10;
            width: min(410px, 100%);
            padding: 36px;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .login-heading {
            margin-bottom: 27px;
            text-align: center;
        }

        .login-symbol {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            margin: 0 auto 15px;
            color: var(--primary);
            font-size: 20px;
            background: var(--primary-soft);
            border: 1px solid #d1e6d8;
            border-radius: 14px;
        }

        .login-heading h1 {
            margin: 0;
            font-size: 25px;
            font-weight: 780;
            line-height: 1.15;
            letter-spacing: -0.045em;
        }

        .login-heading span {
            display: block;
            margin-top: 7px;
            color: var(--text-secondary);
            font-size: 12px;
        }

        .login-form {
            display: grid;
            gap: 17px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group label,
        .label-row label {
            display: block;
            margin-bottom: 7px;
            color: #435048;
            font-size: 11px;
            font-weight: 700;
        }

        .label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 7px;
        }

        .label-row label {
            margin: 0;
        }

        .forgot-link {
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .input-shell {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            z-index: 2;
            color: var(--primary);
            font-size: 14px;
            pointer-events: none;
            transform: translateY(-50%);
        }

        .input-shell input {
            width: 100%;
            min-height: 46px;
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
                box-shadow 0.18s ease;
        }

        .input-shell input::placeholder {
            color: #9ca59f;
        }

        .input-shell input:hover {
            border-color: #c4d0c8;
        }

        .input-shell input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.1);
        }

        .input-shell input[aria-invalid="true"] {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(180, 35, 24, 0.08);
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 7px;
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
        }

        .password-toggle:hover,
        .password-toggle:focus-visible {
            color: var(--primary);
            background: var(--primary-soft);
        }

        .field-error,
        .caps-warning {
            display: none;
            align-items: center;
            gap: 5px;
            margin: 7px 3px 0;
            font-size: 10px;
            line-height: 1.4;
        }

        .field-error {
            color: var(--danger);
        }

        .caps-warning {
            color: #8a5b05;
        }

        .form-group.is-invalid .field-error,
        .caps-warning:not([hidden]) {
            display: flex;
        }

        .login-feedback {
            display: none;
            grid-template-columns: auto 1fr;
            align-items: flex-start;
            gap: 8px;
            margin: 0;
            padding: 11px 12px;
            color: var(--danger);
            font-size: 11px;
            line-height: 1.45;
            background: var(--danger-soft);
            border: 1px solid var(--danger-border);
            border-radius: 11px;
        }

        .login-feedback:not(:empty) {
            display: grid;
        }

        .login-submit {
            width: 100%;
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 2px;
            padding: 10px 16px;
            color: #fff;
            font-size: 12px;
            font-weight: 750;
            background: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 11px;
            cursor: pointer;
            box-shadow: 0 9px 20px rgba(20, 83, 45, 0.17);
            transition:
                background 0.18s ease,
                transform 0.18s ease,
                box-shadow 0.18s ease;
        }

        .login-submit:hover {
            background: var(--primary-hover);
            box-shadow: 0 11px 24px rgba(20, 83, 45, 0.22);
            transform: translateY(-1px);
        }

        .login-submit:disabled {
            cursor: wait;
            opacity: 0.76;
            transform: none;
        }

        .submit-loading {
            display: none;
            align-items: center;
            gap: 8px;
        }

        .login-submit.is-loading .submit-label,
        .login-submit.is-loading .submit-icon {
            display: none;
        }

        .login-submit.is-loading .submit-loading {
            display: inline-flex;
        }

        .spinner {
            width: 15px;
            height: 15px;
            border: 2px solid rgba(255, 255, 255, 0.34);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .login-support {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 22px;
            color: var(--text-secondary);
            font-size: 10px;
        }

        .login-support a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .login-support a:hover {
            text-decoration: underline;
        }

        .login-footer {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 6;
            padding: 0 30px 24px;
            color: var(--text-muted);
            font-size: 10px;
            text-align: center;
        }

        .login-footer span + span::before {
            content: "•";
            margin: 0 8px;
            color: #c0c7c2;
        }

        /*
         * Elementos visuais relacionados à assistência social.
         */

        .scene {
            position: absolute;
            z-index: 2;
            pointer-events: none;
        }

        .scene-left {
            top: 48%;
            left: 11%;
            width: 230px;
            height: 280px;
            transform: translateY(-50%);
        }

        .scene-right {
            top: 48%;
            right: 10%;
            width: 250px;
            height: 300px;
            transform: translateY(-50%);
        }

        .scene-icon {
            position: absolute;
            display: grid;
            place-items: center;
            color: var(--primary);
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(29, 48, 37, 0.08);
        }

        .scene-left .scene-icon:nth-child(1) {
            top: 14px;
            left: 70px;
            width: 72px;
            height: 72px;
            font-size: 27px;
            background: var(--primary-soft);
        }

        .scene-left .scene-icon:nth-child(2) {
            top: 128px;
            left: 6px;
            width: 64px;
            height: 64px;
            font-size: 23px;
        }

        .scene-left .scene-icon:nth-child(3) {
            right: 12px;
            bottom: 12px;
            width: 82px;
            height: 82px;
            color: #9a690d;
            font-size: 28px;
            background: var(--accent-soft);
            border-color: #f1daa6;
        }

        .scene-right .scene-icon:nth-child(1) {
            top: 10px;
            right: 22px;
            width: 66px;
            height: 66px;
            font-size: 24px;
        }

        .scene-right .scene-icon:nth-child(2) {
            top: 100px;
            left: 4px;
            width: 92px;
            height: 92px;
            color: #9a690d;
            font-size: 31px;
            background: var(--accent-soft);
            border-color: #f1daa6;
        }

        .scene-right .scene-icon:nth-child(3) {
            right: 2px;
            bottom: 12px;
            width: 84px;
            height: 84px;
            font-size: 29px;
            background: var(--primary-soft);
        }

        .scene-line {
            position: absolute;
            border: 1.5px solid rgba(20, 83, 45, 0.2);
            border-right-color: transparent;
            border-bottom-color: transparent;
            border-radius: 50%;
        }

        .scene-left .scene-line {
            right: 12px;
            top: 90px;
            width: 80px;
            height: 55px;
            transform: rotate(18deg);
        }

        .scene-right .scene-line {
            left: 82px;
            bottom: 52px;
            width: 100px;
            height: 65px;
            transform: rotate(-20deg);
        }

        .scene-dot {
            position: absolute;
            width: 7px;
            height: 7px;
            border: 1.5px solid rgba(20, 83, 45, 0.35);
            border-radius: 50%;
        }

        .scene-left .scene-dot {
            right: 15px;
            top: 38px;
        }

        .scene-right .scene-dot {
            left: 118px;
            top: 31px;
        }

        .toast-container {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 9999;
            display: grid;
            gap: 10px;
        }

        .sigas-demo-toast {
            width: min(350px, calc(100vw - 36px));
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 10px;
            padding: 13px 14px;
            color: var(--text);
            font-size: 11px;
            line-height: 1.45;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: 0 18px 45px rgba(25, 33, 29, 0.15);
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

        @media (max-width: 1100px) {
            .scene-left {
                left: 4%;
                transform: translateY(-50%) scale(0.82);
            }

            .scene-right {
                right: 3%;
                transform: translateY(-50%) scale(0.82);
            }
        }

        @media (max-width: 850px) {
            .login-page {
                padding: 16px;
            }

            .login-canvas {
                min-height: calc(100vh - 32px);
                border-radius: 22px;
            }

            .login-header {
                padding: 20px 24px;
            }

            .scene {
                opacity: 0.35;
            }

            .scene-left {
                left: -70px;
            }

            .scene-right {
                right: -80px;
            }
        }

        @media (max-width: 620px) {
            .login-page {
                padding: 0;
            }

            .login-canvas {
                min-height: 100vh;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .login-header {
                min-height: 76px;
                padding: 16px 18px;
                border-bottom: 1px solid rgba(227, 232, 229, 0.8);
            }

            .brand img {
                width: 34px;
                height: 40px;
            }

            .brand-copy strong {
                font-size: 14px;
            }

            .brand-copy span {
                max-width: 180px;
                font-size: 9px;
            }

            .header-badge {
                display: none;
            }

            .login-stage {
                min-height: calc(100vh - 135px);
                padding: 28px 16px 70px;
            }

            .login-card {
                width: 100%;
                max-width: 420px;
                padding: 28px 22px;
                border-radius: 20px;
            }

            .scene {
                display: none;
            }

            .login-footer {
                padding: 0 15px 18px;
                font-size: 9px;
            }
        }

        @media (max-width: 380px) {
            .login-card {
                padding: 25px 18px;
            }

            .login-heading h1 {
                font-size: 23px;
            }

            .login-footer span {
                display: block;
            }

            .login-footer span + span::before {
                display: none;
            }

            .login-footer span + span {
                margin-top: 3px;
            }
        }

        @media (max-height: 700px) and (min-width: 621px) {
            .login-page {
                padding: 16px;
            }

            .login-canvas {
                min-height: 670px;
            }

            .login-stage {
                min-height: 540px;
                place-items: start center;
                padding-top: 14px;
            }

            .login-card {
                padding-block: 28px;
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
    <a class="skip-link" href="#loginForm">
        Ir para o formulário de acesso
    </a>

    <div class="login-page">
        <div class="login-canvas">
            <header class="login-header">
                <a
                    class="brand"
                    href="index.php"
                    aria-label="SIGAS Coari — Página de acesso">

                    <img
                        src="assets/img/brasao-placeholder.svg"
                        alt="Brasão institucional do SIGAS Coari">

                    <span class="brand-copy">
                        <strong>SIGAS COARI</strong>
                        <span>Secretaria Municipal de Assistência Social</span>
                    </span>
                </a>

                <span class="header-badge">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    Acesso institucional
                </span>
            </header>

            <div class="scene scene-left" aria-hidden="true">
                <span class="scene-icon">
                    <i class="bi bi-people"></i>
                </span>

                <span class="scene-icon">
                    <i class="bi bi-file-earmark-check"></i>
                </span>

                <span class="scene-icon">
                    <i class="bi bi-house-heart"></i>
                </span>

                <span class="scene-line"></span>
                <span class="scene-dot"></span>
            </div>

            <div class="scene scene-right" aria-hidden="true">
                <span class="scene-icon">
                    <i class="bi bi-person-check"></i>
                </span>

                <span class="scene-icon">
                    <i class="bi bi-heart"></i>
                </span>

                <span class="scene-icon">
                    <i class="bi bi-shield-check"></i>
                </span>

                <span class="scene-line"></span>
                <span class="scene-dot"></span>
            </div>

            <main
                class="login-stage"
                aria-labelledby="loginTitle">

                <section
                    class="login-card"
                    aria-label="Acesso ao SIGAS Coari">

                    <header class="login-heading">
                        <span class="login-symbol" aria-hidden="true">
                            <i class="bi bi-person-lock"></i>
                        </span>

                        <h1 id="loginTitle">Acessar o SIGAS</h1>

                        <span>Área institucional</span>
                    </header>

                    <form
                        id="loginForm"
                        class="login-form"
                        method="post"
                        action="index.php"
                        novalidate>

                        <?= Csrf::input('login') ?>

                        <div
                            id="loginFeedback"
                            class="login-feedback<?= is_string($loginError) && $loginError !== '' ? ' is-error' : '' ?>"
                            role="alert"
                            aria-live="polite">

                            <?php if (is_string($loginError) && $loginError !== ''): ?>
                                <i
                                    class="bi bi-exclamation-circle"
                                    aria-hidden="true"></i>

                                <span><?= e($loginError) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="loginIdentity">
                                CPF ou e-mail
                            </label>

                            <div class="input-shell">
                                <span
                                    class="input-icon"
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
                                class="field-error">

                                <i
                                    class="bi bi-exclamation-circle"
                                    aria-hidden="true"></i>

                                Informe um CPF ou e-mail válido.
                            </p>
                        </div>

                        <div class="form-group">
                            <div class="label-row">
                                <label for="loginPassword">
                                    Senha
                                </label>

                                <a
                                    href="#"
                                    class="forgot-link"
                                    data-demo-action="recuperação de senha">

                                    Esqueci minha senha
                                </a>
                            </div>

                            <div class="input-shell">
                                <span
                                    class="input-icon"
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
                                    class="password-toggle"
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
                                class="caps-warning"
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
                                class="field-error">

                                <i
                                    class="bi bi-exclamation-circle"
                                    aria-hidden="true"></i>

                                Informe uma senha com pelo menos oito caracteres.
                            </p>
                        </div>

                        <button
                            class="login-submit"
                            id="loginSubmit"
                            type="submit">

                            <span class="submit-label">
                                Entrar
                            </span>

                            <span
                                class="submit-icon"
                                aria-hidden="true">

                                <i class="bi bi-arrow-right"></i>
                            </span>

                            <span
                                class="submit-loading"
                                aria-hidden="true">

                                <span class="spinner"></span>
                                Verificando...
                            </span>
                        </button>
                    </form>

                    <div class="login-support">
                        <span>Problemas para acessar?</span>

                        <a
                            href="#"
                            data-demo-action="suporte técnico">

                            Falar com o suporte
                        </a>
                    </div>
                </section>
            </main>

            <footer class="login-footer">
                <span>Prefeitura Municipal de Coari</span>
                <span>Secretaria Municipal de Assistência Social</span>
            </footer>
        </div>
    </div>

    <div
        class="toast-container"
        id="toastContainer"
        aria-live="polite"
        aria-atomic="true">
    </div>

    <script src="assets/js/login.js?v=20260703"></script>
</body>
</html>