<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\UserRepository;
use App\Security\Password;
use App\Security\Session;

final class AuthService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function login(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));

        if ($this->tooManyAttempts()) {
            $this->users->auditLogin(null, $email, false, 'Muitas tentativas');
            return [false, 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', null];
        }

        if (!Validator::email($email) || $password === '') {
            $this->registerFailedAttempt();
            $this->users->auditLogin(null, $email, false, 'Dados inválidos');
            return [false, 'E-mail ou senha inválidos.', null];
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !Password::verify($password, $user['senha_hash'])) {
            $this->registerFailedAttempt();
            $this->users->auditLogin($user['id'] ?? null, $email, false, 'Credenciais inválidas');
            return [false, 'E-mail ou senha inválidos.', null];
        }

        if ((int)$user['ativo'] !== 1 || (int)$user['empresa_ativa'] !== 1) {
            $this->users->auditLogin((int)$user['id'], $email, false, 'Usuário ou empresa inativa');
            return [false, 'Usuário sem permissão de acesso.', null];
        }

        Session::put('login_attempts', []);

        $this->users->updateLastLogin((int)$user['id']);
        $this->users->auditLogin((int)$user['id'], $email, true, 'Login realizado');

        return [true, 'Login realizado com sucesso.', $user];
    }

    private function tooManyAttempts(): bool
    {
        $now = time();
        $attempts = array_filter(Session::get('login_attempts', []), fn ($time) => ($now - (int)$time) < 900);
        Session::put('login_attempts', $attempts);

        return count($attempts) >= 5;
    }

    private function registerFailedAttempt(): void
    {
        $attempts = Session::get('login_attempts', []);
        $attempts[] = time();

        Session::put('login_attempts', $attempts);
    }
}
