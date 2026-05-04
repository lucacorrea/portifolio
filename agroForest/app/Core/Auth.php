<?php
class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function login(array $usuario): void
    {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'nivel' => $usuario['nivel'],
            'cargo' => self::cargoPorNivel($usuario['nivel']),
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function homeForNivel(string $nivel): string
    {
        return match ($nivel) {
            'dono' => route_url('dono', 'dashboard'),
            'administrativo' => route_url('administrativo', 'dashboard'),
            default => route_url('recepcao', 'dashboard'),
        };
    }

    private static function cargoPorNivel(string $nivel): string
    {
        return match ($nivel) {
            'dono' => 'Dono',
            'administrativo' => 'Administrativo',
            default => 'Recepção',
        };
    }
}
