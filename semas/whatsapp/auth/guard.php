<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/permissoes.php';

function whatsapp_auth_check(): bool
{
    if (whatsapp_session_expired()) {
        whatsapp_logout(false);
        return false;
    }

    return !empty($_SESSION['semas_whatsapp_user_id']) && !empty($_SESSION['semas_whatsapp_role']);
}

function whatsapp_auth_guard(): void
{
    if (!whatsapp_auth_check()) {
        header('Location: login.php');
        exit;
    }

    whatsapp_session_touch();
}

function whatsapp_logout(bool $redirect = true): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    if ($redirect) {
        header('Location: login.php');
        exit;
    }
}
