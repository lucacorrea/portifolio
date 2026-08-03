<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Session
{
    public static function start(int $lifetime): void
    {
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Lax']);
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (isset($_SESSION['_seen']) && time() - $_SESSION['_seen'] > $lifetime * 60) { session_unset(); session_destroy(); session_start(); }
        $_SESSION['_seen'] = time();
    }
    public static function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public static function put(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }
}
