<?php

namespace FluxEmpresa\Core;

class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    }

    public static function empresaId(): ?int
    {
        return isset($_SESSION['user']['empresa_id']) ? (int) $_SESSION['user']['empresa_id'] : null;
    }

    public static function role(): string
    {
        return strtoupper((string) ($_SESSION['user']['perfil'] ?? ''));
    }

    public static function isLogged(): bool
    {
        return self::id() !== null;
    }

    public static function isSuperAdmin(): bool
    {
        return self::role() === 'SUPER_ADMIN';
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'empresa_id' => $user['empresa_id'] !== null ? (int) $user['empresa_id'] : null,
            'nome' => $user['nome'],
            'email' => $user['email'] ?? null,
            'perfil' => $user['perfil'],
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::isLogged()) {
            redirect('login.php');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        $roles = array_map('strtoupper', $roles);

        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }
}
