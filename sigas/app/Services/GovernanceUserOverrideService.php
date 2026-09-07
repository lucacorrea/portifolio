<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AccessModuleCatalog;
use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Repositories\GovernanceUserOverrideRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use InvalidArgumentException;

final class GovernanceUserOverrideService
{
    public function __construct(
        private readonly GovernanceUserOverrideRepository $overrides,
        private readonly UserRepository $users,
        private readonly UserSessionRepository $sessions,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * @param array<string,mixed> $moduleStates
     * @param array<string,mixed> $permissionStates
     * @return array{message:string,revoked_sessions:int}
     */
    public function save(
        User $operator,
        int $targetUserId,
        array $moduleStates,
        array $permissionStates,
        string $reason,
    ): array {
        $this->authorization->requirePermission($operator, 'governanca.excecoes_usuario');

        if (!$this->authorization->isAdministrator($operator) && !$this->authorization->isSupport($operator)) {
            throw new AuthorizationException('Somente Governança pode alterar exceções individuais.');
        }
        if ($operator->id === $targetUserId) {
            throw new AuthorizationException('Não é permitido alterar as próprias exceções individuais.');
        }

        $target = $this->users->findById($targetUserId);
        if (!$target instanceof User) {
            throw new InvalidArgumentException('Usuário não localizado.');
        }
        if ($this->authorization->isAdministrator($target) || $this->authorization->isSupport($target)) {
            throw new AuthorizationException('Contas Administrador e Suporte usam escopo global e não aceitam exceções individuais nesta tela.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Informe uma justificativa com pelo menos 5 caracteres.');
        }
        $reason = mb_substr($reason, 0, 500);

        $catalog = AccessModuleCatalog::operational();
        $permissionModuleIndex = AccessModuleCatalog::permissionModuleIndex();
        $permissionCatalog = $this->overrides->permissionCatalog(array_keys($permissionModuleIndex));
        $allowedPermissionSlugs = [];
        foreach ($permissionCatalog as $permission) {
            $slug = trim((string) ($permission['slug'] ?? ''));
            if ($slug !== '') {
                $allowedPermissionSlugs[$slug] = true;
            }
        }

        $normalizedModules = [];
        foreach ($catalog as $moduleKey => $_definition) {
            $normalizedModules[$moduleKey] = $this->normalizeState($moduleStates[$moduleKey] ?? 'inherit');
        }

        $normalizedPermissions = [];
        foreach ($permissionStates as $slug => $state) {
            $slug = trim((string) $slug);
            if ($slug === '' || !isset($allowedPermissionSlugs[$slug])) {
                continue;
            }
            $normalizedPermissions[$slug] = $this->normalizeState($state);
        }

        foreach (array_keys($allowedPermissionSlugs) as $slug) {
            if (!array_key_exists($slug, $normalizedPermissions)) {
                $normalizedPermissions[$slug] = null;
            }
        }

        $before = [
            'modulos' => $this->overrides->moduleOverrides($targetUserId),
            'permissoes' => $this->overrides->permissionOverrides($targetUserId),
        ];

        return Database::transaction(function () use (
            $operator,
            $target,
            $normalizedModules,
            $normalizedPermissions,
            $reason,
            $before
        ): array {
            foreach ($normalizedModules as $module => $allowed) {
                $this->overrides->setModuleOverride($target->id, $module, $allowed, $operator->id, $reason);
            }

            foreach ($normalizedPermissions as $permission => $allowed) {
                $this->overrides->setPermissionOverride($target->id, $permission, $allowed, $operator->id, $reason);
            }

            $this->users->incrementAuthorizationVersion($target->id);
            $revoked = $this->sessions->revokeAllForUser($target->id);
            $after = [
                'modulos' => $this->overrides->moduleOverrides($target->id),
                'permissoes' => $this->overrides->permissionOverrides($target->id),
            ];

            $this->audit->record(
                $operator->id,
                $target->id,
                'usuario_excecoes_acesso_alteradas',
                'governanca',
                $reason,
                $before,
                $after
            );

            return [
                'message' => 'Exceções individuais de acesso atualizadas.',
                'revoked_sessions' => $revoked,
            ];
        });
    }

    private function normalizeState(mixed $state): ?bool
    {
        $state = strtolower(trim((string) $state));
        return match ($state) {
            'allow', '1', 'sim', 'permitir' => true,
            'deny', '0', 'nao', 'não', 'bloquear' => false,
            'inherit', '', 'herdar' => null,
            default => throw new InvalidArgumentException('Estado de permissão inválido.'),
        };
    }
}
