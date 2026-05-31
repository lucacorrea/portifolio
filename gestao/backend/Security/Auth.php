<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Response;
use App\Repositories\UserRepository;
use App\Services\AuthService;

final class Auth
{
    public static function check(): bool
    {
        return Session::get('user.id') !== null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => Session::get('user.id'),
            'empresa_id' => Session::get('user.empresa_id'),
            'nome' => Session::get('user.nome'),
            'email' => Session::get('user.email'),
            'nivel' => Session::get('user.nivel'),
            'empresa_nome' => Session::get('user.empresa_nome'),
        ];
    }

    public static function attempt(string $email, string $password): array
    {
        $service = new AuthService(new UserRepository());

        return $service->login($email, $password);
    }

    public static function login(array $user): void
    {
        Session::regenerate();

        Session::put('user.id', (int)$user['id']);
        Session::put('user.empresa_id', (int)$user['empresa_id']);
        Session::put('user.nome', $user['nome']);
        Session::put('user.email', $user['email']);
        Session::put('user.nivel', $user['nivel']);
        Session::put('user.empresa_nome', $user['empresa_nome'] ?? '');
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $login = str_contains($script, '/pages/') ? '../login.php' : 'login.php';
        $next = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');

        Response::redirect($login . '?next=' . $next);
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        $user = self::user();

        if (!$user || !in_array($user['nivel'], $roles, true)) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }
}
