<?php

namespace FluxEmpresa\Core;

class Csrf
{
    public static function token(): string
    {
        $key = env('CSRF_KEY', 'csrf_token');

        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . h(self::token()) . '">';
    }

    public static function validate(?string $token): bool
    {
        $key = env('CSRF_KEY', 'csrf_token');
        $sessionToken = $_SESSION[$key] ?? '';

        return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    public static function requireValid(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!self::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada ou token inválido. Atualize a página e tente novamente.');
        }
    }
}
