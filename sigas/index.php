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

    <meta
        name="theme-color"
        content="#14532d">

    <title>SIGAS Coari — Acesso</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

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
            --primary-soft: #e9f5ed;

            --accent: #d99b27;
            --accent-soft: #fff0c9;

            --background: #111311;
            --canvas: #f7f5ed;
            --surface: #ffffff;
            --surface-soft: #f7f9f8;

            --text: #17221c;
            --text-secondary: #66736b;
            --text-muted: #929b95;

            --border: #e4e8e5;
            --border-strong: #d4dcd7;

            --danger: #b42318;
            --danger-soft: #fff1ef;
            --danger-border: #f2c9c5;

            --shadow: 0 30px 75px rgba(20, 33, 25, 0.15);
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

        :focus-visible {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.17);
        }

        .sigas-auth-page {
            min-height: 100vh;
            padding: 42px;
        }

        .sigas-auth-canvas {
            position: relative;
            width: min(1480px, 100%);
            min-height: calc(100vh - 84px);
            margin: 0 auto;
            overflow: hidden;
            background:
                radial-gradient(circle at 50% 42%,
                    rgba(255, 255, 255, 0.98),
                    rgba(248, 247, 241, 0.94) 52%,
                    rgba(243, 241, 231, 0.98));
            border-radius: 2px;
            box-shadow: 0 18px 60px rgba(0, 0, 0, 0.28);
        }

        .sigas-auth-header {
            position: relative;
            z-index: 20;
            min-height: 86px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 22px 52px;
        }

        .sigas-auth-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--text);
            text-decoration: none;
        }

        .sigas-auth-brand img {
            width: 40px;
            height: 46px;
            flex: 0 0 auto;
            object-fit: contain;
        }

        .sigas-auth-brand-copy strong {
            display: block;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.03em;
        }

        .sigas-auth-brand-copy span {
            display: block;
            margin-top: 3px;
            color: var(--text-secondary);
            font-size: 9px;
        }

        .sigas-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sigas-header-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 13px;
            color: var(--primary);
            font-size: 10px;
            font-weight: 750;
            background: var(--primary-soft);
            border: 1px solid #d1e5d7;
            border-radius: 999px;
        }

        .sigas-auth-stage {
            position: relative;
            z-index: 5;
            min-height: calc(100vh - 230px);
            display: grid;
            place-items: center;
            padding: 26px 28px 70px;
        }

        .sigas-login-card {
            position: relative;
            z-index: 15;
            width: min(390px, 100%);
            padding: 34px;
            background: rgba(255, 255, 255, 0.985);
            border: 1px solid rgba(225, 231, 227, 0.95);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .sigas-login-heading {
            margin-bottom: 26px;
            text-align: center;
        }

        .sigas-login-symbol {
            width: 45px;
            height: 45px;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            color: var(--primary);
            font-size: 20px;
            background: var(--primary-soft);
            border: 1px solid #d1e5d7;
            border-radius: 14px;
        }

        .sigas-login-heading h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 780;
            line-height: 1.15;
            letter-spacing: -0.045em;
        }

        .sigas-login-heading span {
            display: block;
            margin-top: 7px;
            color: var(--text-secondary);
            font-size: 11px;
        }

        .sigas-login-form {
            display: grid;
            gap: 16px;
        }

        .sigas-form-group {
            min-width: 0;
        }

        .sigas-form-group label,
        .sigas-label-row label {
            display: block;
            margin-bottom: 7px;
            color: #435048;
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
                box-shadow 0.18s ease,
                background 0.18s ease;
        }

        .sigas-input-shell input::placeholder {
            color: #9ca59f;
        }

        .sigas-input-shell input:hover {
            border-color: #c4d0c8;
        }

        .sigas-input-shell input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(20, 83, 45, 0.1);
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
            line-height: 1.4;
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
            padding: 11px 12px;
            color: var(--danger);
            font-size: 11px;
            line-height: 1.45;
            background: var(--danger-soft);
            border: 1px solid var(--danger-border);
            border-radius: 11px;
        }

        .sigas-login-feedback:not(:empty) {
            display: grid;
        }

        .sigas-login-feedback.is-error {
            color: var(--danger);
        }

        .sigas-login-feedback.is-success {
            color: var(--primary);
            background: var(--primary-soft);
            border-color: #d1e5d7;
        }

        .sigas-login-submit {
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

        .sigas-login-submit:hover {
            background: var(--primary-hover);
            box-shadow: 0 11px 24px rgba(20, 83, 45, 0.22);
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
            margin-top: 21px;
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

        .sigas-auth-footer {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 10;
            padding: 0 30px 24px;
            color: var(--text-muted);
            font-size: 10px;
            text-align: center;
        }

        .sigas-auth-footer span+span::before {
            content: "•";
            margin: 0 8px;
            color: #c0c7c2;
        }

        /* =====================================================
           ILUSTRAÇÕES DE PESSOAS
           ===================================================== */

        .sigas-scene {
            position: absolute;
            z-index: 2;
            pointer-events: none;
        }

        .sigas-scene-left {
            top: 51%;
            left: 5.5%;
            width: 310px;
            max-width: 25vw;
            transform: translateY(-50%);
        }

        .sigas-scene-right {
            top: 51%;
            right: 5%;
            width: 340px;
            max-width: 27vw;
            transform: translateY(-50%);
        }

        .sigas-people-illustration {
            width: 100%;
            height: auto;
            display: block;
            overflow: visible;
        }

        .scene-ground,
        .scene-card,
        .scene-card-line,
        .scene-line,
        .scene-small-dot,
        .scene-platform,
        .scene-platform-line,
        .document-sheet,
        .document-line,
        .paper,
        .paper-line,
        .person-arm,
        .person-leg,
        .person-shoe,
        .person-pattern {
            vector-effect: non-scaling-stroke;
        }

        .scene-ground {
            stroke: rgba(20, 83, 45, 0.18);
            stroke-width: 1.4;
        }

        .scene-card {
            fill: rgba(255, 255, 255, 0.7);
            stroke: rgba(23, 34, 28, 0.28);
            stroke-width: 1.2;
        }

        .scene-card-line {
            stroke: rgba(23, 34, 28, 0.42);
            stroke-width: 1.2;
            stroke-linecap: round;
        }

        .scene-pattern {
            fill: #e6b451;
        }

        .scene-pattern-dot {
            fill: #111512;
        }

        .scene-line {
            fill: none;
            stroke: rgba(23, 34, 28, 0.4);
            stroke-width: 1.2;
            stroke-linecap: round;
        }

        .scene-small-dot {
            fill: none;
            stroke: rgba(23, 34, 28, 0.45);
            stroke-width: 1.2;
        }

        .person-skin {
            fill: #fff;
            stroke: #111512;
            stroke-width: 1.6;
        }

        .person-hair {
            fill: #101310;
        }

        .person-body-dark {
            fill: #121512;
        }

        .person-body-green {
            fill: var(--primary);
        }

        .person-pattern {
            fill: none;
            stroke: rgba(255, 255, 255, 0.25);
            stroke-width: 1.1;
            stroke-linecap: round;
        }

        .person-arm {
            fill: none;
            stroke: #111512;
            stroke-width: 9;
            stroke-linecap: round;
        }

        .person-arm-light {
            fill: none;
            stroke: #fff;
            stroke-width: 16;
            stroke-linecap: round;
        }

        .document-sheet,
        .paper {
            fill: #fff;
            stroke: rgba(17, 21, 18, 0.38);
            stroke-width: 1.2;
        }

        .document-line,
        .paper-line {
            stroke: rgba(17, 21, 18, 0.38);
            stroke-width: 1.1;
            stroke-linecap: round;
        }

        .person-leg {
            fill: none;
            stroke: #fff;
            stroke-width: 18;
            stroke-linecap: round;
        }

        .person-leg-fill {
            fill: #fff;
            stroke: #111512;
            stroke-width: 1.4;
        }

        .person-shoe {
            fill: none;
            stroke: #101310;
            stroke-width: 12;
            stroke-linecap: round;
        }

        .scene-platform {
            fill: #fff;
            stroke: rgba(17, 21, 18, 0.35);
            stroke-width: 1.2;
        }

        .scene-platform-line {
            stroke: rgba(17, 21, 18, 0.35);
            stroke-width: 1.2;
        }

        .people-left {
            opacity: 0.94;
        }

        .people-right {
            opacity: 0.97;
        }

        /* =====================================================
           TOAST
           ===================================================== */

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
            color: var(--text);
            font-size: 11px;
            line-height: 1.45;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: 0 18px 45px rgba(25, 33, 29, 0.15);
        }

        .sigas-demo-toast>i {
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

        /* =====================================================
           RESPONSIVIDADE
           ===================================================== */

        @media (max-width: 1180px) {
            .sigas-scene-left {
                left: 0;
                transform: translateY(-50%) scale(0.86);
            }

            .sigas-scene-right {
                right: 0;
                transform: translateY(-50%) scale(0.86);
            }
        }

        @media (max-width: 920px) {
            .sigas-auth-page {
                padding: 20px;
            }

            .sigas-auth-canvas {
                min-height: calc(100vh - 40px);
            }

            .sigas-auth-header {
                padding-inline: 28px;
            }

            .sigas-scene {
                opacity: 0.2;
            }

            .sigas-scene-left {
                left: -110px;
            }

            .sigas-scene-right {
                right: -120px;
            }
        }

        @media (max-width: 680px) {
            .sigas-auth-page {
                padding: 0;
            }

            .sigas-auth-canvas {
                min-height: 100vh;
                border-radius: 0;
                box-shadow: none;
            }

            .sigas-auth-header {
                min-height: 76px;
                padding: 16px 18px;
                border-bottom: 1px solid rgba(226, 232, 228, 0.82);
            }

            .sigas-auth-brand img {
                width: 34px;
                height: 40px;
            }

            .sigas-auth-brand-copy strong {
                font-size: 14px;
            }

            .sigas-auth-brand-copy span {
                max-width: 180px;
                font-size: 9px;
            }

            .sigas-header-status {
                display: none;
            }

            .sigas-auth-stage {
                min-height: calc(100vh - 130px);
                padding: 30px 16px 70px;
            }

            .sigas-login-card {
                width: 100%;
                max-width: 420px;
                padding: 29px 22px;
                border-radius: 20px;
            }

            .sigas-scene {
                display: none;
            }

            .sigas-auth-footer {
                padding: 0 15px 18px;
                font-size: 9px;
            }
        }

        @media (max-width: 390px) {
            .sigas-login-card {
                padding: 26px 18px;
            }

            .sigas-login-heading h1 {
                font-size: 22px;
            }

            .sigas-auth-footer span {
                display: block;
            }

            .sigas-auth-footer span+span::before {
                display: none;
            }

            .sigas-auth-footer span+span {
                margin-top: 3px;
            }

            .sigas-label-row {
                align-items: flex-end;
            }
        }

        @media (max-height: 710px) and (min-width: 681px) {
            .sigas-auth-page {
                padding: 18px;
            }

            .sigas-auth-canvas {
                min-height: 674px;
            }

            .sigas-auth-stage {
                min-height: 540px;
                place-items: start center;
                padding-top: 10px;
            }

            .sigas-login-card {
                padding-block: 27px;
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

<body class="sigas-auth-page" data-page="login">

    <a class="sigas-skip-link" href="#loginForm">
        Ir para o formulário de acesso
    </a>

    <div class="sigas-auth-canvas">

        <header class="sigas-auth-header">
            <a
                class="sigas-auth-brand"
                href="index.php"
                aria-label="SIGAS Coari — Página de acesso">

                <img
                    src="assets/img/brasao-placeholder.svg"
                    alt="Brasão institucional do SIGAS Coari">

                <span class="sigas-auth-brand-copy">
                    <strong>SIGAS COARI</strong>
                    <span>Secretaria Municipal de Assistência Social</span>
                </span>
            </a>

            <div class="sigas-header-actions">
                <span class="sigas-header-status">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    Acesso institucional
                </span>
            </div>
        </header>

        <!-- Ilustração esquerda -->
        <div class="sigas-scene sigas-scene-left" aria-hidden="true">
            <svg
                class="sigas-people-illustration people-left"
                viewBox="0 0 360 330"
                fill="none"
                xmlns="http://www.w3.org/2000/svg">

                <path class="scene-ground" d="M20 294H338" />

                <rect
                    class="scene-card"
                    x="32"
                    y="76"
                    width="92"
                    height="62"
                    rx="2" />

                <path class="scene-card-line" d="M50 98H105" />
                <path class="scene-card-line" d="M50 114H96" />

                <rect
                    class="scene-pattern"
                    x="44"
                    y="210"
                    width="74"
                    height="84"
                    rx="1" />

                <circle class="scene-pattern-dot" cx="63" cy="229" r="4.5" />
                <circle class="scene-pattern-dot" cx="91" cy="230" r="4.5" />
                <circle class="scene-pattern-dot" cx="74" cy="252" r="4.5" />
                <circle class="scene-pattern-dot" cx="101" cy="265" r="4.5" />
                <circle class="scene-pattern-dot" cx="59" cy="278" r="4.5" />

                <path
                    class="scene-line"
                    d="M10 52C28 70 45 69 60 49C75 29 87 43 94 54C103 68 113 61 124 50" />

                <path
                    class="scene-line"
                    d="M246 215C263 233 280 233 296 214C309 198 320 207 328 216" />

                <circle class="scene-small-dot" cx="282" cy="85" r="5" />
                <circle class="scene-small-dot" cx="307" cy="161" r="6" />

                <g>
                    <path
                        class="person-skin"
                        d="M213 75C230 75 244 89 244 106C244 123 230 137 213 137C196 137 182 123 182 106C182 89 196 75 213 75Z" />

                    <path
                        class="person-hair"
                        d="M182 107C179 87 194 69 215 69C236 69 249 84 250 104C240 93 228 91 215 98C203 105 192 107 182 107Z" />

                    <path
                        class="person-body-dark"
                        d="M177 145C191 134 235 134 249 147C259 170 261 204 255 239H169C165 204 167 168 177 145Z" />

                    <path class="person-pattern" d="M191 151L238 231" />
                    <path class="person-pattern" d="M236 151L187 230" />
                    <path class="person-pattern" d="M179 174L252 174" />
                    <path class="person-pattern" d="M172 202L257 202" />

                    <path
                        class="person-arm"
                        d="M177 160C154 171 138 192 129 218" />

                    <path
                        class="person-arm"
                        d="M247 161C269 176 281 197 286 221" />

                    <rect
                        class="document-sheet"
                        x="112"
                        y="205"
                        width="88"
                        height="61"
                        rx="4" />

                    <path class="document-line" d="M130 224H181" />
                    <path class="document-line" d="M130 240H169" />
                    <path class="document-line" d="M130 252H177" />

                    <path class="person-leg" d="M192 237L165 291" />
                    <path class="person-leg" d="M232 237L264 291" />

                    <path class="person-shoe" d="M153 295H188" />
                    <path class="person-shoe" d="M252 295H287" />
                </g>
            </svg>
        </div>

        <!-- Ilustração direita -->
        <div class="sigas-scene sigas-scene-right" aria-hidden="true">
            <svg
                class="sigas-people-illustration people-right"
                viewBox="0 0 390 340"
                fill="none"
                xmlns="http://www.w3.org/2000/svg">

                <path class="scene-ground" d="M24 302H365" />

                <rect
                    class="scene-card"
                    x="28"
                    y="83"
                    width="82"
                    height="54"
                    rx="2" />

                <path class="scene-card-line" d="M45 103H92" />
                <path class="scene-card-line" d="M45 118H85" />

                <rect
                    class="scene-pattern"
                    x="302"
                    y="222"
                    width="70"
                    height="80"
                    rx="1" />

                <circle class="scene-pattern-dot" cx="320" cy="241" r="4.5" />
                <circle class="scene-pattern-dot" cx="348" cy="241" r="4.5" />
                <circle class="scene-pattern-dot" cx="333" cy="264" r="4.5" />
                <circle class="scene-pattern-dot" cx="354" cy="281" r="4.5" />
                <circle class="scene-pattern-dot" cx="315" cy="288" r="4.5" />

                <path
                    class="scene-line"
                    d="M265 93C281 111 297 111 312 93C325 78 337 89 345 98" />

                <circle class="scene-small-dot" cx="340" cy="67" r="4.5" />
                <circle class="scene-small-dot" cx="83" cy="173" r="5" />

                <g>
                    <rect
                        class="scene-platform"
                        x="204"
                        y="211"
                        width="79"
                        height="91" />

                    <path class="scene-platform-line" d="M204 211H283" />

                    <path
                        class="person-skin"
                        d="M200 69C216 69 229 82 229 98C229 114 216 127 200 127C184 127 171 114 171 98C171 82 184 69 200 69Z" />

                    <path
                        class="person-hair"
                        d="M171 99C168 78 184 62 204 64C214 53 231 62 229 77C246 81 251 103 242 118C229 106 218 103 205 109C191 116 179 110 171 99Z" />

                    <path
                        class="person-body-dark"
                        d="M177 133C194 122 226 126 242 143C234 171 229 190 236 211H179C170 182 168 157 177 133Z" />

                    <path class="person-pattern" d="M191 136L232 205" />
                    <path class="person-pattern" d="M228 137L185 204" />
                    <path class="person-pattern" d="M176 165L239 165" />

                    <path
                        class="person-arm"
                        d="M179 148C154 159 135 177 123 198" />

                    <path
                        class="person-arm"
                        d="M238 151C259 167 270 185 276 205" />

                    <path
                        class="paper"
                        d="M98 188L184 202L169 234L83 217L98 188Z" />

                    <path class="paper-line" d="M111 202L157 210" />
                    <path class="paper-line" d="M107 214L145 221" />

                    <path
                        class="person-leg-fill"
                        d="M180 210C155 228 129 250 101 281L125 296C155 271 184 250 210 226L180 210Z" />

                    <path
                        class="person-leg-fill"
                        d="M214 210C228 235 244 260 263 289L288 276C273 247 258 224 241 207L214 210Z" />

                    <path class="person-shoe" d="M90 284L124 304" />
                    <path class="person-shoe" d="M263 292L298 274" />
                </g>
            </svg>
        </div>

        <main
            class="sigas-auth-stage"
            aria-labelledby="loginTitle">

            <section
                class="sigas-login-card"
                aria-label="Acesso ao SIGAS Coari">

                <header class="sigas-login-heading">
                    <span
                        class="sigas-login-symbol"
                        aria-hidden="true">

                        <i class="bi bi-person-lock"></i>
                    </span>

                    <h1 id="loginTitle">
                        Acessar o SIGAS
                    </h1>

                    <span>Área institucional</span>
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
            </section>
        </main>

        <footer class="sigas-auth-footer">
            <span>Prefeitura Municipal de Coari</span>
            <span>Secretaria Municipal de Assistência Social</span>
        </footer>
    </div>

    <div
        class="sigas-toast-container"
        id="toastContainer"
        aria-live="polite"
        aria-atomic="true">
    </div>

    <script src="assets/js/login.js?v=20260703"></script>
</body>

</html>