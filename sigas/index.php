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
        content="Acesso institucional ao SIGAS Coari — Sistema Integrado de Gestão da Assistência Social.">

    <meta name="theme-color" content="#14532d">

    <title>SIGAS Coari — Acesso institucional</title>

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
            --sigas-primary: #14532d;
            --sigas-primary-hover: #0f4425;
            --sigas-primary-dark: #0b351c;
            --sigas-primary-light: #176b3a;
            --sigas-primary-soft: #e9f5ed;

            --sigas-warning: #d99218;

            --sigas-background: #edf1ef;
            --sigas-surface: #ffffff;
            --sigas-surface-soft: #f7f9f8;

            --sigas-text: #17221c;
            --sigas-text-secondary: #66736b;
            --sigas-text-muted: #8b958f;

            --sigas-border: #e5eae7;
            --sigas-border-strong: #d7ded9;

            --sigas-danger: #b42318;
            --sigas-danger-soft: #fff1f0;
            --sigas-danger-border: #f3c7c3;

            --sigas-shadow: 0 24px 70px rgba(22, 40, 30, 0.11);

            --sigas-radius-small: 10px;
            --sigas-radius-medium: 16px;
            --sigas-radius-large: 28px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            min-height: 100%;
            background: var(--sigas-background);
        }

        body {
            min-width: 320px;
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
            color: var(--sigas-text);
            font-family:
                "Inter",
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            background: var(--sigas-background);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        button,
        input {
            font: inherit;
        }

        button,
        input,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        a {
            color: inherit;
        }

        .sigas-skip-link {
            position: fixed;
            top: 16px;
            left: -9999px;
            z-index: 9999;
            padding: 10px 14px;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            background: var(--sigas-primary);
            border-radius: var(--sigas-radius-small);
        }

        .sigas-skip-link:focus {
            left: 16px;
        }

        :focus-visible {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.18);
        }

        .sigas-auth-page {
            position: relative;
            overflow-x: hidden;
        }

        .sigas-auth-stage {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 28px;
            background:
                radial-gradient(
                    circle at 50% 0%,
                    rgba(255, 255, 255, 0.78),
                    transparent 42%
                ),
                var(--sigas-background);
        }

        .sigas-auth-shell {
            width: min(960px, 100%);
            min-height: 550px;
            display: grid;
            grid-template-columns: minmax(310px, 38%) minmax(0, 62%);
            overflow: hidden;
            background: var(--sigas-surface);
            border: 1px solid var(--sigas-border);
            border-radius: var(--sigas-radius-large);
            box-shadow: var(--sigas-shadow);
        }

        .sigas-auth-institutional {
            position: relative;
            min-width: 0;
            display: flex;
            flex-direction: column;
            padding: 34px;
            color: #fff;
            background:
                radial-gradient(
                    circle at 110% -10%,
                    rgba(255, 255, 255, 0.12),
                    transparent 35%
                ),
                linear-gradient(
                    145deg,
                    var(--sigas-primary-dark),
                    var(--sigas-primary) 62%,
                    var(--sigas-primary-light)
                );
            isolation: isolate;
        }

        .sigas-auth-institutional::before {
            content: "";
            position: absolute;
            right: -80px;
            bottom: -100px;
            z-index: -1;
            width: 260px;
            height: 260px;
            border: 50px solid rgba(255, 255, 255, 0.035);
            border-radius: 50%;
        }

        .sigas-auth-institutional::after {
            content: "";
            position: absolute;
            top: 47%;
            left: -70px;
            z-index: -1;
            width: 160px;
            height: 160px;
            background: rgba(255, 255, 255, 0.025);
            border-radius: 50%;
        }

        .sigas-institutional-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .sigas-auth-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            color: #fff;
            text-decoration: none;
        }

        .sigas-auth-brand img {
            width: 44px;
            height: 50px;
            flex: 0 0 auto;
            object-fit: contain;
        }

        .sigas-auth-brand-copy {
            min-width: 0;
        }

        .sigas-auth-brand-copy strong {
            display: block;
            font-size: 0.94rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.025em;
        }

        .sigas-auth-brand-copy small {
            display: block;
            margin-top: 3px;
            color: rgba(255, 255, 255, 0.66);
            font-size: 0.61rem;
            line-height: 1.4;
        }

        .sigas-institutional-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex: 0 0 auto;
            padding: 7px 9px;
            color: rgba(255, 255, 255, 0.88);
            font-size: 0.54rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.13);
            border-radius: 999px;
        }

        .sigas-institutional-content {
            max-width: 310px;
            margin: auto 0;
            padding: 45px 0 34px;
        }

        .sigas-institutional-kicker {
            display: block;
            margin-bottom: 12px;
            color: #f6cf68;
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }

        .sigas-institutional-content h2 {
            margin: 0;
            font-size: clamp(1.7rem, 3vw, 2.3rem);
            font-weight: 750;
            line-height: 1.12;
            letter-spacing: -0.045em;
        }

        .sigas-institutional-content > p {
            margin: 18px 0 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.78rem;
            line-height: 1.7;
        }

        .sigas-trust-list {
            display: grid;
            gap: 14px;
            margin-top: 30px;
        }

        .sigas-trust-item {
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: center;
            gap: 11px;
        }

        .sigas-trust-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
        }

        .sigas-trust-item strong {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
        }

        .sigas-trust-item small {
            display: block;
            margin-top: 2px;
            color: rgba(255, 255, 255, 0.59);
            font-size: 0.58rem;
            line-height: 1.4;
        }

        .sigas-institutional-footer {
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding-top: 20px;
            color: rgba(255, 255, 255, 0.58);
            border-top: 1px solid rgba(255, 255, 255, 0.11);
        }

        .sigas-institutional-footer strong {
            color: rgba(255, 255, 255, 0.83);
            font-size: 0.62rem;
        }

        .sigas-institutional-footer span {
            font-size: 0.56rem;
        }

        .sigas-auth-form-panel {
            min-width: 0;
            display: grid;
            place-items: center;
            padding: 44px 56px;
            background: var(--sigas-surface);
        }

        .sigas-auth-form-wrapper {
            width: min(100%, 390px);
        }

        .sigas-auth-heading {
            margin-bottom: 28px;
        }

        .sigas-auth-kicker {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
            color: var(--sigas-primary);
            font-size: 0.64rem;
            font-weight: 800;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .sigas-auth-heading h1 {
            margin: 0;
            color: var(--sigas-text);
            font-size: clamp(1.7rem, 3vw, 2rem);
            font-weight: 760;
            line-height: 1.12;
            letter-spacing: -0.045em;
        }

        .sigas-auth-heading p {
            margin: 10px 0 0;
            color: var(--sigas-text-secondary);
            font-size: 0.77rem;
            line-height: 1.6;
        }

        .sigas-login-form {
            display: grid;
            gap: 18px;
        }

        .sigas-form-group {
            min-width: 0;
        }

        .sigas-form-group > label,
        .sigas-label-row label {
            display: block;
            margin-bottom: 7px;
            color: #3f4b44;
            font-size: 0.7rem;
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
            margin-bottom: 0;
        }

        .sigas-forgot-link {
            color: var(--sigas-primary);
            font-size: 0.65rem;
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
            color: var(--sigas-primary);
            font-size: 0.86rem;
            pointer-events: none;
            transform: translateY(-50%);
        }

        .sigas-input-shell input {
            width: 100%;
            min-height: 46px;
            padding: 10px 46px 10px 40px;
            color: var(--sigas-text);
            font-size: 0.76rem;
            font-weight: 500;
            background: #fff;
            border: 1px solid var(--sigas-border-strong);
            border-radius: 11px;
            outline: 0;
            transition:
                border-color 0.18s ease,
                box-shadow 0.18s ease,
                background 0.18s ease;
        }

        .sigas-input-shell input::placeholder {
            color: #9ba49f;
        }

        .sigas-input-shell input:hover {
            border-color: #c8d2cc;
        }

        .sigas-input-shell input:focus {
            border-color: var(--sigas-primary);
            box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.1);
        }

        .sigas-input-shell input[aria-invalid="true"] {
            border-color: var(--sigas-danger);
            box-shadow: 0 0 0 3px rgba(180, 35, 24, 0.08);
        }

        .sigas-password-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            z-index: 3;
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            padding: 0;
            color: var(--sigas-text-muted);
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
            color: var(--sigas-primary);
            background: var(--sigas-primary-soft);
        }

        .sigas-login-feedback {
            display: none;
            grid-template-columns: auto 1fr;
            align-items: flex-start;
            gap: 9px;
            margin: 0;
            padding: 11px 12px;
            font-size: 0.68rem;
            line-height: 1.45;
            border-radius: 11px;
        }

        .sigas-login-feedback:not(:empty) {
            display: grid;
        }

        .sigas-login-feedback.is-error {
            color: var(--sigas-danger);
            background: var(--sigas-danger-soft);
            border: 1px solid var(--sigas-danger-border);
        }

        .sigas-field-error,
        .sigas-caps-warning {
            display: none;
            align-items: center;
            gap: 5px;
            margin: 7px 3px 0;
            font-size: 0.62rem;
            line-height: 1.4;
        }

        .sigas-field-error {
            color: var(--sigas-danger);
        }

        .sigas-caps-warning {
            color: #8b5a00;
        }

        .sigas-form-group.is-invalid .sigas-field-error,
        .sigas-caps-warning:not([hidden]) {
            display: flex;
        }

        .sigas-login-submit {
            position: relative;
            width: 100%;
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 2px;
            padding: 10px 16px;
            color: #fff;
            font-size: 0.76rem;
            font-weight: 750;
            background: var(--sigas-primary);
            border: 1px solid var(--sigas-primary);
            border-radius: 11px;
            cursor: pointer;
            box-shadow: 0 9px 20px rgba(20, 83, 45, 0.17);
            transition:
                background 0.18s ease,
                border-color 0.18s ease,
                transform 0.18s ease,
                box-shadow 0.18s ease;
        }

        .sigas-login-submit:hover {
            background: var(--sigas-primary-hover);
            border-color: var(--sigas-primary-hover);
            box-shadow: 0 11px 24px rgba(20, 83, 45, 0.22);
            transform: translateY(-1px);
        }

        .sigas-login-submit:active {
            transform: translateY(0);
        }

        .sigas-login-submit:focus-visible {
            box-shadow:
                0 0 0 3px rgba(20, 83, 45, 0.17),
                0 9px 20px rgba(20, 83, 45, 0.17);
        }

        .sigas-login-submit:disabled {
            cursor: wait;
            opacity: 0.76;
            transform: none;
        }

        .sigas-submit-icon {
            display: inline-grid;
            place-items: center;
            font-size: 0.95rem;
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
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.35);
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
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: center;
            gap: 10px;
            margin-top: 24px;
            padding: 13px;
            background: var(--sigas-surface-soft);
            border: 1px solid var(--sigas-border);
            border-radius: 12px;
        }

        .sigas-support-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            color: var(--sigas-primary);
            background: var(--sigas-primary-soft);
            border-radius: 10px;
        }

        .sigas-login-support div {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            color: var(--sigas-text-secondary);
            font-size: 0.65rem;
            line-height: 1.45;
        }

        .sigas-login-support a {
            color: var(--sigas-primary);
            font-weight: 700;
            text-decoration: none;
        }

        .sigas-login-support a:hover {
            text-decoration: underline;
        }

        .sigas-auth-security {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 16px;
            color: var(--sigas-text-muted);
            font-size: 0.59rem;
            line-height: 1.5;
        }

        .sigas-auth-security i {
            margin-top: 1px;
            color: var(--sigas-primary);
        }

        .sigas-toast-container {
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
            color: var(--sigas-text);
            font-size: 0.7rem;
            line-height: 1.45;
            background: #fff;
            border: 1px solid var(--sigas-border);
            border-radius: 13px;
            box-shadow: 0 18px 45px rgba(25, 33, 29, 0.15);
            animation: sigas-toast-in 0.2s ease-out;
        }

        .sigas-demo-toast > i {
            color: var(--sigas-primary);
        }

        .sigas-demo-toast button {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            padding: 0;
            color: var(--sigas-text-secondary);
            background: var(--sigas-surface-soft);
            border: 0;
            border-radius: 9px;
            cursor: pointer;
        }

        @keyframes sigas-toast-in {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 900px) {
            .sigas-auth-stage {
                padding: 22px;
            }

            .sigas-auth-shell {
                grid-template-columns:
                    minmax(270px, 35%)
                    minmax(0, 65%);
            }

            .sigas-auth-institutional {
                padding: 28px;
            }

            .sigas-auth-form-panel {
                padding: 40px;
            }

            .sigas-institutional-badge {
                display: none;
            }
        }

        @media (max-width: 680px) {
            .sigas-auth-stage {
                place-items: start center;
                padding: 14px;
            }

            .sigas-auth-shell {
                width: 100%;
                min-height: auto;
                grid-template-columns: 1fr;
                border-radius: 21px;
            }

            .sigas-auth-institutional {
                min-height: auto;
                padding: 23px;
            }

            .sigas-institutional-top {
                align-items: center;
            }

            .sigas-auth-brand img {
                width: 38px;
                height: 44px;
            }

            .sigas-institutional-content {
                max-width: none;
                margin: 30px 0 0;
                padding: 0;
            }

            .sigas-institutional-content h2 {
                max-width: 390px;
                font-size: 1.55rem;
            }

            .sigas-institutional-content > p {
                margin-top: 12px;
                font-size: 0.72rem;
            }

            .sigas-trust-list,
            .sigas-institutional-footer {
                display: none;
            }

            .sigas-auth-form-panel {
                display: block;
                padding: 30px 23px 25px;
            }

            .sigas-auth-form-wrapper {
                width: 100%;
                max-width: none;
            }

            .sigas-auth-heading {
                margin-bottom: 24px;
            }

            .sigas-auth-heading h1 {
                font-size: 1.65rem;
            }
        }

        @media (max-width: 420px) {
            .sigas-auth-stage {
                padding: 0;
            }

            .sigas-auth-shell {
                min-height: 100dvh;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .sigas-auth-institutional {
                padding: 21px;
            }

            .sigas-institutional-content {
                margin-top: 25px;
            }

            .sigas-institutional-content h2 {
                font-size: 1.42rem;
            }

            .sigas-institutional-content > p {
                margin-bottom: 2px;
            }

            .sigas-auth-form-panel {
                padding: 27px 21px 24px;
            }

            .sigas-label-row {
                align-items: flex-end;
            }

            .sigas-login-support {
                margin-top: 20px;
            }

            .sigas-toast-container {
                top: 12px;
                right: 12px;
                left: 12px;
            }

            .sigas-demo-toast {
                width: 100%;
            }
        }

        @media (max-height: 650px) and (min-width: 681px) {
            .sigas-auth-stage {
                place-items: start center;
                padding-block: 20px;
            }

            .sigas-auth-shell {
                min-height: 520px;
            }

            .sigas-auth-form-panel {
                padding-block: 32px;
            }

            .sigas-institutional-content {
                padding-block: 35px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="sigas-auth-page" data-page="login">

    <a class="sigas-skip-link" href="#loginForm">
        Ir para o formulário de acesso
    </a>

    <main
        class="sigas-auth-stage"
        aria-labelledby="loginTitle">

        <section
            class="sigas-auth-shell"
            aria-label="Acesso ao SIGAS Coari">

            <aside class="sigas-auth-institutional">
                <div class="sigas-institutional-top">

                    <a
                        class="sigas-auth-brand"
                        href="index.php"
                        aria-label="SIGAS Coari — Página de acesso">

                        <img
                            src="assets/img/brasao-placeholder.svg"
                            alt="Brasão institucional do SIGAS Coari">

                        <span class="sigas-auth-brand-copy">
                            <strong>SIGAS COARI</strong>
                            <small>Gestão da Assistência Social</small>
                        </span>
                    </a>

                    <span class="sigas-institutional-badge">
                        <i
                            class="bi bi-building-lock"
                            aria-hidden="true"></i>

                        Acesso institucional
                    </span>
                </div>

                <div class="sigas-institutional-content">
                    <span class="sigas-institutional-kicker">
                        Sistema integrado
                    </span>

                    <h2>
                        Gestão social integrada, organizada e segura.
                    </h2>

                    <p>
                        Acesse os serviços, programas e atendimentos da
                        Secretaria Municipal de Assistência Social de Coari.
                    </p>

                    <div class="sigas-trust-list">
                        <div class="sigas-trust-item">
                            <span class="sigas-trust-icon">
                                <i
                                    class="bi bi-shield-check"
                                    aria-hidden="true"></i>
                            </span>

                            <div>
                                <strong>Ambiente protegido</strong>
                                <small>
                                    Controle seguro de acesso e sessões.
                                </small>
                            </div>
                        </div>

                        <div class="sigas-trust-item">
                            <span class="sigas-trust-icon">
                                <i
                                    class="bi bi-person-check"
                                    aria-hidden="true"></i>
                            </span>

                            <div>
                                <strong>Acesso autorizado</strong>
                                <small>
                                    Permissões conforme função e unidade.
                                </small>
                            </div>
                        </div>

                        <div class="sigas-trust-item">
                            <span class="sigas-trust-icon">
                                <i
                                    class="bi bi-activity"
                                    aria-hidden="true"></i>
                            </span>

                            <div>
                                <strong>Atividades monitoradas</strong>
                                <small>
                                    Operações registradas para auditoria.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="sigas-institutional-footer">
                    <strong>Prefeitura Municipal de Coari</strong>

                    <span>
                        Secretaria Municipal de Assistência Social
                    </span>
                </footer>
            </aside>

            <div class="sigas-auth-form-panel">
                <div class="sigas-auth-form-wrapper">

                    <header class="sigas-auth-heading">
                        <span class="sigas-auth-kicker">
                            <i
                                class="bi bi-lock"
                                aria-hidden="true"></i>

                            Área restrita
                        </span>

                        <h1 id="loginTitle">
                            Acesso ao sistema
                        </h1>

                        <p>
                            Informe seu CPF ou e-mail institucional e sua
                            senha para continuar.
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
                                Entrar no SIGAS
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
                                Verificando acesso...
                            </span>
                        </button>
                    </form>

                    <div class="sigas-login-support">
                        <span class="sigas-support-icon">
                            <i
                                class="bi bi-headset"
                                aria-hidden="true"></i>
                        </span>

                        <div>
                            <span>Problemas para acessar?</span>

                            <a
                                href="#"
                                data-demo-action="suporte técnico">

                                Falar com o suporte
                            </a>
                        </div>
                    </div>

                    <div class="sigas-auth-security">
                        <i
                            class="bi bi-shield-lock"
                            aria-hidden="true"></i>

                        <span>
                            Este ambiente possui controle de acesso e
                            monitoramento de atividades.
                        </span>
                    </div>
                </div>
            </div>
        </section>
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