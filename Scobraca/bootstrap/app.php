<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Config/env.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) env('SESSION_NAME', 'fluxpay_session'));

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $sessionSavePath = (string) env('SESSION_SAVE_PATH', STORAGE_PATH . '/sessions');
    $sessionCookiePath = (string) env('SESSION_COOKIE_PATH', '/');

    if ($sessionSavePath !== '') {
        if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $sessionSavePath)) {
            $sessionSavePath = BASE_PATH . '/' . ltrim($sessionSavePath, '/\\');
        }

        if (!is_dir($sessionSavePath) && !@mkdir($sessionSavePath, 0775, true) && !is_dir($sessionSavePath)) {
            error_log('[SESSION] Nao foi possivel criar o diretorio de sessoes: ' . $sessionSavePath);
        } elseif (is_writable($sessionSavePath)) {
            session_save_path($sessionSavePath);
        } else {
            error_log('[SESSION] Diretorio de sessoes sem permissao de escrita: ' . $sessionSavePath);
        }
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $sessionCookiePath !== '' ? $sessionCookiePath : '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once APP_PATH . '/Config/database.php';
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Auth/auth.php';
require_once APP_PATH . '/Auth/guards.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
}

if ((env('APP_DEBUG', 'false') === 'true')) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}
