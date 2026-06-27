<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Services\CompanyContextService;
use App\Repositories\UserCompanyRepository;
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
            return $this->result(false, 'Muitas tentativas. Aguarde alguns minutos e tente novamente.');
        }

        if (!Validator::email($email) || $password === '') {
            $this->registerFailedAttempt();
            $this->users->auditLogin(null, $email, false, 'Dados inválidos');
            return $this->result(false, 'E-mail ou senha inválidos.');
        }

        $user = $this->users->findIdentityByEmail($email);

        if (!$user || !Password::verify($password, $user['senha_hash'])) {
            $this->registerFailedAttempt();
            $this->users->auditLogin($user['id'] ?? null, $email, false, 'Credenciais inválidas');
            return $this->result(false, 'E-mail ou senha inválidos.');
        }

        if ((int)$user['ativo'] !== 1) {
            $this->users->auditLogin((int)$user['id'], $email, false, 'Usuário ou empresa inativa');
            return $this->result(false, 'Usuário sem permissão de acesso.');
        }

        (new PlatformOwnerProvisioningService($this->users->connection()))->synchronizeUserAccess((int)$user['id']);

        $context = new CompanyContextService(
            new UserCompanyRepository($this->users->connection())
        );
        $companies = $context->availableCompanies((int)$user['id']);

        if (!$companies) {
            $this->users->auditLogin((int)$user['id'], $email, false, 'Sem empresa ativa');
            return $this->result(false, 'Usuário sem empresa ativa para acesso.');
        }

        Session::put('login_attempts', []);

        $this->users->updateLastLogin((int)$user['id']);
        $this->users->auditLogin((int)$user['id'], $email, true, 'Login realizado');

        if (count($companies) > 1) {
            Session::regenerate();
            Session::put('user.id', (int)$user['id']);
            Session::put('user.empresa_principal_id', (int)$user['empresa_id']);
            Session::put('user.nome', (string)$user['nome']);
            Session::put('user.email', (string)$user['email']);
            Session::put('user.company_selection_pending', true);

            return $this->result(true, 'Selecione a empresa para continuar.', $user, $companies, true);
        }

        $selectedCompany = $companies[0];
        $context->activate((int)$user['id'], (int)$selectedCompany['empresa_id'], 'login');

        return $this->result(true, 'Login realizado com sucesso.', $user, $companies, false);
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

    private function result(
        bool $success,
        string $message,
        ?array $user = null,
        array $companies = [],
        bool $requiresSelection = false
    ): array {
        return [
            'success' => $success,
            'message' => $message,
            'user' => $user,
            'companies' => $companies,
            'requires_selection' => $requiresSelection,
        ];
    }
}
