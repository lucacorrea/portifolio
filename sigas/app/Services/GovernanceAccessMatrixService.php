<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Repositories\AccessLevelRepository;
use App\Repositories\GovernanceAccessMatrixRepository;
use InvalidArgumentException;

final class GovernanceAccessMatrixService
{
    private const PROTECTED_LEVELS = ['administrador', 'suporte'];

    private const MODULE_LABELS = [
        'kit_maternidade' => 'Kit Maternidade',
        'aluguel_social' => 'Aluguel Social',
        'beneficios_eventuais' => 'Benefícios Eventuais',
        'governanca' => 'Governança e Acessos',
        'comida_mesa' => 'Coari Comida na Mesa',
        'primeiro_emprego' => 'Coari Meu Primeiro Emprego',
    ];

    public function __construct(
        private readonly GovernanceAccessMatrixRepository $repository,
        private readonly AccessLevelRepository $levels,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function page(): array
    {
        $permissions = $this->repository->activeModulePermissions();
        $modules = [];

        foreach (self::MODULE_LABELS as $module => $label) {
            $modules[$module] = [
                'key' => $module,
                'label' => $label,
                'editable' => in_array($module, GovernanceAccessMatrixRepository::editableModules(), true),
                'permissions' => [],
            ];
        }

        foreach ($permissions as $permission) {
            $module = (string) ($permission['modulo'] ?? '');
            if (!isset($modules[$module])) {
                continue;
            }

            $modules[$module]['permissions'][] = [
                'id' => (int) ($permission['id'] ?? 0),
                'name' => (string) ($permission['nome'] ?? 'Permissão'),
                'slug' => (string) ($permission['slug'] ?? ''),
                'description' => (string) ($permission['descricao'] ?? ''),
                'is_view' => str_ends_with((string) ($permission['slug'] ?? ''), '.visualizar'),
            ];
        }

        $levels = [];
        foreach ($this->repository->activeLevels() as $level) {
            $levelId = (int) ($level['id'] ?? 0);
            $slug = (string) ($level['slug'] ?? '');
            $levels[] = [
                'id' => $levelId,
                'name' => (string) ($level['nome'] ?? 'Nível'),
                'slug' => $slug,
                'priority' => (int) ($level['prioridade'] ?? 0),
                'users' => (int) ($level['usuarios'] ?? 0),
                'protected' => in_array($slug, self::PROTECTED_LEVELS, true),
                'grants' => $this->repository->grantedPermissionIds($levelId),
            ];
        }

        return [
            'levels' => $levels,
            'modules' => array_values($modules),
            'editable_modules' => GovernanceAccessMatrixRepository::editableModules(),
        ];
    }

    /**
     * @param list<int> $requestedPermissionIds
     * @return array{message:string,revoked_sessions:int,affected_users:int,permission_ids:list<int>}
     */
    public function update(
        User $operator,
        int $levelId,
        array $requestedPermissionIds,
        string $reason,
    ): array {
        if (!$this->authorization->isAdministrator($operator) && !$this->authorization->isSupport($operator)) {
            throw new AuthorizationException('A matriz de acesso é restrita à Governança e Acessos.');
        }
        $this->authorization->requirePermission($operator, 'governanca.permissoes');

        $level = $this->levels->findActiveById($levelId);
        if ($level === null) {
            throw new InvalidArgumentException('Nível de acesso não localizado ou inativo.');
        }

        if (in_array($level->slug, self::PROTECTED_LEVELS, true)) {
            throw new AuthorizationException('Administrador e Suporte são níveis protegidos e não podem ser alterados por esta matriz.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Informe uma justificativa com pelo menos 5 caracteres.');
        }
        $reason = mb_substr($reason, 0, 500);

        $requestedPermissionIds = array_values(array_unique(array_filter(
            array_map('intval', $requestedPermissionIds),
            static fn (int $id): bool => $id > 0
        )));

        $permissionRows = $this->repository->activeModulePermissions();
        $editableModules = GovernanceAccessMatrixRepository::editableModules();
        $editableById = [];
        $viewByModule = [];
        $moduleById = [];
        $slugById = [];

        foreach ($permissionRows as $permission) {
            $id = (int) ($permission['id'] ?? 0);
            $module = (string) ($permission['modulo'] ?? '');
            $slug = (string) ($permission['slug'] ?? '');

            if ($id <= 0 || !in_array($module, $editableModules, true)) {
                continue;
            }

            $editableById[$id] = true;
            $moduleById[$id] = $module;
            $slugById[$id] = $slug;
            if (str_ends_with($slug, '.visualizar')) {
                $viewByModule[$module] = $id;
            }
        }

        foreach ($requestedPermissionIds as $permissionId) {
            if (!isset($editableById[$permissionId])) {
                throw new InvalidArgumentException('A matriz contém uma permissão inválida ou protegida. Atualize a página e tente novamente.');
            }
        }

        $selectedByModule = [];
        foreach ($requestedPermissionIds as $permissionId) {
            $selectedByModule[$moduleById[$permissionId]][] = $permissionId;
        }

        foreach ($selectedByModule as $module => $ids) {
            if ($ids !== [] && isset($viewByModule[$module]) && !in_array($viewByModule[$module], $requestedPermissionIds, true)) {
                $requestedPermissionIds[] = $viewByModule[$module];
            }
        }

        sort($requestedPermissionIds, SORT_NUMERIC);
        $beforeSlugs = $this->repository->grantedEditablePermissionSlugs($levelId);
        $beforeSlugsSorted = $beforeSlugs;
        sort($beforeSlugsSorted, SORT_STRING);

        $afterSlugs = [];
        foreach ($requestedPermissionIds as $id) {
            if (isset($slugById[$id])) {
                $afterSlugs[] = $slugById[$id];
            }
        }
        sort($afterSlugs, SORT_STRING);

        if ($beforeSlugsSorted === $afterSlugs) {
            return [
                'message' => 'Nenhuma alteração foi necessária para este nível.',
                'revoked_sessions' => 0,
                'affected_users' => 0,
                'permission_ids' => $requestedPermissionIds,
            ];
        }

        $result = Database::transaction(function () use (
            $operator,
            $level,
            $levelId,
            $requestedPermissionIds,
            $beforeSlugsSorted,
            $afterSlugs,
            $reason
        ): array {
            $this->repository->replaceEditablePermissions($levelId, $requestedPermissionIds);
            $affectedUsers = $this->repository->incrementAuthorizationVersionsForLevel($levelId);
            $revokedSessions = $this->repository->revokeActiveSessionsForLevel($levelId);

            $this->audit->record(
                $operator->id,
                null,
                'matriz_nivel_atualizada',
                'governanca',
                $reason,
                [
                    'nivel_id' => $levelId,
                    'nivel' => $level->nome,
                    'permissoes' => $beforeSlugsSorted,
                ],
                [
                    'nivel_id' => $levelId,
                    'nivel' => $level->nome,
                    'permissoes' => $afterSlugs,
                ]
            );

            return [
                'affected_users' => $affectedUsers,
                'revoked_sessions' => $revokedSessions,
            ];
        });

        return [
            'message' => 'Matriz de acesso do nível ' . $level->nome . ' atualizada com sucesso.',
            'revoked_sessions' => (int) $result['revoked_sessions'],
            'affected_users' => (int) $result['affected_users'],
            'permission_ids' => $requestedPermissionIds,
        ];
    }
}
