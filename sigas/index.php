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
    new AuditService(new AuditLogRepository($pdo))
);

if ($authService->currentUser() !== null) {
    header('Location: dashboard.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $identity = isset($_POST['identity']) && is_string($_POST['identity']) ? trim($_POST['identity']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

    if (!Csrf::validateAndConsume($token, 'login')) {
        Logger::security('Invalid CSRF token on login.');
        Session::flash('login_error', 'Requisição inválida. Tente novamente.');
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
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Acesso institucional ao SIGAS Coari — Sistema Integrado de Gestão da Assistência Social.">
    <meta name="theme-color" content="#f6f7f8">
    <title>SIGAS Coari — Acesso</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/login.css?v=20260622" rel="stylesheet">
</head>
<body class="sigas-auth-page" data-page="login">
    <a class="sigas-skip-link" href="#loginForm">Ir para o formulário de acesso</a>

    <div class="sigas-auth-decoration sigas-auth-decoration--blue" aria-hidden="true"></div>
    <div class="sigas-auth-decoration sigas-auth-decoration--orange" aria-hidden="true"></div>
    <span class="sigas-auth-orbit sigas-auth-orbit--one" aria-hidden="true"></span>
    <span class="sigas-auth-orbit sigas-auth-orbit--two" aria-hidden="true"></span>

    <main class="sigas-auth-stage" aria-labelledby="loginTitle">
        <section class="sigas-login-card" aria-label="Acesso ao SIGAS Coari">
            <div class="sigas-card-shape sigas-card-shape--orange" aria-hidden="true"></div>
            <div class="sigas-card-shape sigas-card-shape--gold" aria-hidden="true"></div>
            <div class="sigas-card-shape sigas-card-shape--blue" aria-hidden="true"></div>

            <header class="sigas-login-brand">
                <div class="sigas-brand-copy">
                    <strong>SIGAS COARI</strong>
                    <span>Secretaria Municipal de Assistência Social</span>
                </div>
                <span class="sigas-environment-badge">Acesso institucional</span>
            </header>

            <div class="sigas-login-content">
                <div class="sigas-login-heading">
                    <p class="sigas-login-eyebrow">Bem-vindo ao sistema</p>
                    <p>Entre com sua conta para acessar os serviços, programas e atendimentos da assistência social municipal.</p>
                </div>

                <form id="loginForm" class="sigas-login-form" method="post" action="index.php" novalidate>
                    <?= Csrf::input('login') ?>
                    <div class="sigas-form-group">
                        <label class="sigas-visually-hidden" for="loginIdentity">CPF ou e-mail</label>
                        <div class="sigas-input-shell">
                            <span class="sigas-input-icon" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                            <input
                                id="loginIdentity"
                                name="identity"
                                type="text"
                                autocomplete="username"
                                inputmode="email"
                                placeholder="CPF ou e-mail"
                                required
                                minlength="5"
                                value="<?= e($loginIdentity) ?>"
                                aria-describedby="identityError">
                        </div>
                        <p id="identityError" class="sigas-field-error">Informe um CPF ou e-mail válido.</p>
                    </div>

                    <div class="sigas-form-group">
                        <label class="sigas-visually-hidden" for="loginPassword">Senha</label>
                        <div class="sigas-input-shell">
                            <span class="sigas-input-icon" aria-hidden="true"><i class="bi bi-lock-fill"></i></span>
                            <input
                                id="loginPassword"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="Senha"
                                required
                                minlength="8"
                                aria-describedby="passwordError capsLockWarning">
                            <button
                                class="sigas-password-toggle"
                                id="passwordToggle"
                                type="button"
                                aria-label="Mostrar senha"
                                aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p id="capsLockWarning" class="sigas-caps-warning" role="status" aria-live="polite" hidden>
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            Caps Lock está ativado.
                        </p>
                        <p id="passwordError" class="sigas-field-error">Informe uma senha com pelo menos oito caracteres.</p>
                    </div>

                    <div class="sigas-login-options">
                        <label class="sigas-remember-option" for="keepConnected">
                            <input id="keepConnected" type="checkbox" disabled>
                            <span>Manter conectado</span>
                        </label>
                        <a href="#" data-demo-action="recuperação de senha">Esqueci minha senha</a>
                    </div>

                    <button class="sigas-login-submit" id="loginSubmit" type="submit">
                        <span class="sigas-submit-label">Entrar</span>
                        <span class="sigas-submit-circle" aria-hidden="true">
                            <i class="bi bi-arrow-right"></i>
                        </span>
                        <span class="sigas-submit-loading" aria-hidden="true">
                            <span class="sigas-spinner"></span>
                            Verificando...
                        </span>
                    </button>

                    <p id="loginFeedback" class="sigas-login-feedback<?= is_string($loginError) && $loginError !== '' ? ' is-error' : '' ?>" role="status" aria-live="polite"><?= e($loginError ?? '') ?></p>
                </form>

                <div class="sigas-login-support">
                    <span>Problemas para acessar?</span>
                    <a href="#" data-demo-action="suporte técnico">Falar com o suporte</a>
                </div>
            </div>

            <footer class="sigas-login-footer">
                <div class="sigas-security-note">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>Ambiente protegido e monitorado</span>
                </div>
                <small>Prefeitura Municipal de Coari · SEMAS</small>
            </footer>
        </section>
    </main>

    <div class="sigas-toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <script src="assets/js/login.js?v=20260622"></script>
</body>
</html>
