<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function configure(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = (string) Environment::get('SESSION_NAME', 'SIGAS_SESSION');
        $lifetime = Environment::int('SESSION_LIFETIME', 7200);
        $cookiePath = self::cookiePath();
        $secure = self::isHttps();

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
    }

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        self::enforceIdleTimeout();
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();

        if (func_num_args() === 2) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $stored = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $stored;
    }

    private static function enforceIdleTimeout(): void
    {
        $timeout = Environment::int('SESSION_IDLE_TIMEOUT', 1800);
        $lastActivity = $_SESSION['last_activity'] ?? null;

        if (is_int($lastActivity) && $timeout > 0 && (time() - $lastActivity) > $timeout) {
            self::destroy();
            return;
        }

        $_SESSION['last_activity'] = time();
    }

    private static function isHttps(): bool
    {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')) {
            return true;
        }

        if (!Environment::bool('TRUST_PROXY_HEADERS', false)) {
            return false;
        }

        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        return is_string($forwardedProto) && strtolower($forwardedProto) === 'https';
    }

    private static function cookiePath(): string
    {
        $path = (string) Environment::get('SESSION_COOKIE_PATH', '/');

        if ($path === '' || !str_starts_with($path, '/')) {
            throw new \RuntimeException('Invalid session cookie path.');
        }

        return $path;
    }
}
