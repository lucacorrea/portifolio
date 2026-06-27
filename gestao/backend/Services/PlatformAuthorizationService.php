<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Repositories\CompanyAuditRepository;
use App\Repositories\UserRepository;
use App\Security\Session;
use RuntimeException;

final class PlatformAuthorizationService
{
    private UserRepository $users;
    private CompanyAuditRepository $audit;

    public function __construct(?UserRepository $users = null, ?CompanyAuditRepository $audit = null)
    {
        $db = Database::connection();
        $this->users = $users ?? new UserRepository($db);
        $this->audit = $audit ?? new CompanyAuditRepository($db);
    }

    public function isPlatformOwner(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        $emails = $this->configuredOwnerEmails();
        if ($emails === []) {
            return false;
        }

        $user = $this->users->findIdentityById($usuarioId);
        if (!$user || (int)($user['ativo'] ?? 0) !== 1) {
            return false;
        }

        return in_array($this->normalizeEmail((string)$user['email']), $emails, true);
    }

    public function isPlatformOwnerEmail(string $email): bool
    {
        $emails = $this->configuredOwnerEmails();
        if ($emails === []) {
            return false;
        }

        return in_array($this->normalizeEmail($email), $emails, true);
    }

    public function assertPlatformOwner(int $usuarioId): void
    {
        if ($this->isPlatformOwner($usuarioId)) {
            return;
        }

        $this->recordDeniedAccess($usuarioId, 'Somente administradores da plataforma podem executar esta ação.');
        throw new RuntimeException('Somente administradores da plataforma podem executar esta ação.');
    }

    public function configuredOwnerEmails(): array
    {
        $raw = (string)Env::get('PLATFORM_OWNER_EMAILS', '');
        $emails = array_map(
            fn (string $email): string => $this->normalizeEmail($email),
            explode(',', $raw)
        );

        return array_values(array_unique(array_filter(
            $emails,
            static fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        )));
    }

    public function assertEmailNotReservedForCommonUser(string $email, ?int $sameUserId = null): void
    {
        if (!$this->isPlatformOwnerEmail($email)) {
            return;
        }

        if ($sameUserId !== null) {
            $user = $this->users->findIdentityById($sameUserId);
            if ($user && $this->normalizeEmail((string)$user['email']) === $this->normalizeEmail($email)) {
                return;
            }
        }

        throw new RuntimeException('Este e-mail é reservado para a administração da plataforma.');
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function recordDeniedAccess(int $usuarioId, string $reason): void
    {
        $empresaId = (int)Session::get('user.empresa_id', 0);
        if ($usuarioId <= 0 || $empresaId <= 0) {
            log_app_message(sprintf("[%s] negacao_acesso usuario=%d motivo=%s\n", date('Y-m-d H:i:s'), $usuarioId, $reason));
            return;
        }

        try {
            $this->audit->record($usuarioId, $empresaId, $empresaId, 'negacao_acesso', ['motivo' => $reason]);
        } catch (\Throwable $e) {
            log_app_exception($e);
        }
    }
}
