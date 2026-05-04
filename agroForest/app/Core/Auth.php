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

    public static function verifyPassword(string $senha, string $hash): bool
    {
        if (substr($hash, 0, 14) === 'pbkdf2_sha256') {
            return self::verifyPbkdf2($senha, $hash);
        }

        return password_verify($senha, $hash);
    }

    private static function verifyPbkdf2(string $senha, string $hash): bool
    {
        $partes = explode('$', $hash);

        if (count($partes) !== 4 || $partes[0] !== 'pbkdf2_sha256') {
            return false;
        }

        [, $iteracoes, $salt, $hashEsperado] = $partes;
        $iteracoes = filter_var($iteracoes, FILTER_VALIDATE_INT, ['options' => ['min_range' => 10000]]);

        if (!$iteracoes || $salt === '' || $hashEsperado === '') {
            return false;
        }

        $hashCalculado = hash_pbkdf2('sha256', $senha, $salt, $iteracoes, 64);

        return hash_equals($hashEsperado, $hashCalculado);
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
