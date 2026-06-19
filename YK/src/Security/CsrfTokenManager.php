<?php
declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class CsrfTokenManager
{
    public function __construct(private readonly SessionManager $session)
    {
    }

    public function token(): string
    {
        $this->session->start();
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return $this->regenerate();
        }

        return $_SESSION['csrf_token'];
    }

    public function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return hash_equals($this->token(), $token);
    }

    public function requireValid(?string $token): void
    {
        if (!$this->validate($token)) {
            throw new RuntimeException('Token CSRF invalido.');
        }
    }

    public function regenerate(): string
    {
        $this->session->start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return $_SESSION['csrf_token'];
    }
}
