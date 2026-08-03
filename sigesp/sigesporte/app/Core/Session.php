<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Session
{
    public static function start(int $lifetime): void
    {
        $cookiePath = Url::basePath() !== '' ? Url::basePath() . '/' : '/';
        session_set_cookie_params(['lifetime' => 0, 'path' => $cookiePath, 'httponly' => true, 'secure' => self::isSecureRequest(), 'samesite' => 'Lax']);
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (isset($_SESSION['_seen']) && time() - $_SESSION['_seen'] > $lifetime * 60) { session_unset(); session_destroy(); session_start(); }
        $_SESSION['_seen'] = time();
    }
    public static function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public static function put(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }
    private static function isSecureRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        $trustProxyHeaders = filter_var(getenv('APP_TRUST_PROXY_HEADERS') ?: false, FILTER_VALIDATE_BOOL);
        return $trustProxyHeaders
            && strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https';
    }
}
