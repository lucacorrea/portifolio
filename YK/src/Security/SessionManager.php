<?php
declare(strict_types=1);

namespace App\Security;

final class SessionManager
{
    private bool $started = false;

    public function __construct(
        private readonly string $name = 'YKSESSID',
        private readonly int $idleTimeout = 1800,
        private readonly int $absoluteTimeout = 28800,
        private readonly int $regenerateInterval = 900,
        private readonly string $cookiePath = '/YK',
        private readonly bool $secureCookie = true
    ) {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->secureCookie ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');

        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->cookiePath,
            'secure' => $this->secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        $this->started = true;
    }

    public function authenticate(int $userId): void
    {
        $this->start();
        session_regenerate_id(true);

        $now = time();
        $_SESSION = [
            'authenticated' => true,
            'user_id' => $userId,
            'authenticated_at' => $now,
            'last_activity' => $now,
            'last_regenerated_at' => $now,
        ];
    }

    public function isAuthenticated(): bool
    {
        $this->start();

        return ($_SESSION['authenticated'] ?? false) === true && $this->userId() !== null;
    }

    public function userId(): ?int
    {
        $this->start();
        $userId = $_SESSION['user_id'] ?? null;

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    public function refreshActivity(): bool
    {
        $this->start();

        if (!$this->isAuthenticated()) {
            return false;
        }

        $now = time();
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);
        $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? $now);

        if (($now - $lastActivity) > $this->idleTimeout || ($now - $authenticatedAt) > $this->absoluteTimeout) {
            $this->destroy();
            return false;
        }

        $lastRegeneratedAt = (int) ($_SESSION['last_regenerated_at'] ?? $authenticatedAt);
        if (($now - $lastRegeneratedAt) >= $this->regenerateInterval) {
            session_regenerate_id(true);
            $_SESSION['last_regenerated_at'] = $now;
        }

        $_SESSION['last_activity'] = $now;

        return true;
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: $this->cookiePath,
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? $this->secureCookie),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        $this->started = false;
    }

    public function flash(string $type, string $message): void
    {
        $this->start();
        if (!in_array($type, ['success', 'info', 'warning', 'danger'], true)) {
            $type = 'info';
        }

        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * @return array<int, array{type:string,message:string}>
     */
    public function consumeFlashMessages(): array
    {
        $this->start();
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);

        return is_array($messages) ? $messages : [];
    }
}
