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
            'empresa_principal_id' => Session::get('user.empresa_principal_id'),
            'empresa_id' => Session::get('user.empresa_id'),
            'nome' => Session::get('user.nome'),
            'email' => Session::get('user.email'),
            'nivel' => Session::get('user.nivel'),
            'empresa_nome' => Session::get('user.empresa_nome'),
            'empresa_pai_id' => Session::get('user.empresa_pai_id'),
            'empresa_tipo' => Session::get('user.empresa_tipo'),
            'company_selection_pending' => (bool)Session::get('user.company_selection_pending', false),
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
        Session::put('user.empresa_principal_id', (int)($user['empresa_principal_id'] ?? $user['empresa_id']));
        Session::put('user.empresa_id', (int)$user['empresa_id']);
        Session::put('user.nome', $user['nome']);
        Session::put('user.email', $user['email']);
        Session::put('user.nivel', $user['nivel']);
        Session::put('user.empresa_nome', $user['empresa_nome'] ?? '');
        Session::put('user.empresa_pai_id', isset($user['empresa_pai_id']) ? (int)$user['empresa_pai_id'] : null);
        Session::put('user.empresa_tipo', $user['empresa_tipo'] ?? 'matriz');
        Session::put('user.company_selection_pending', false);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            if ((bool)Session::get('user.company_selection_pending', false) && !self::isSelectionRoute()) {
                $selection = self::pathPrefix() . 'selecionar-loja.php';
                Response::redirect($selection);
            }

            return;
        }

        $login = self::pathPrefix() . 'login.php';
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

    private static function pathPrefix(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        if (str_contains($script, '/api/lojas/')) {
            return '../../';
        }

        if (str_contains($script, '/pages/') || str_contains($script, '/api/')) {
            return '../';
        }

        return '';
    }

    private static function isSelectionRoute(): bool
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        return str_ends_with($script, '/selecionar-loja.php')
            || str_ends_with($script, '/login.php')
            || str_ends_with($script, '/logout.php')
            || str_ends_with($script, '/api/lojas/selecionar.php');
    }
}
