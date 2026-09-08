<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AccessModuleCatalog;
use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Repositories\GovernanceSectorRepository;
use InvalidArgumentException;

final class GovernanceSectorService
{
    private const PROTECTED_SECTORS = ['administracao-sistema', 'ti-suporte'];

    public function __construct(
        private readonly GovernanceSectorRepository $repository,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function page(): array
    {
        $moduleCatalog = AccessModuleCatalog::operational();
        $moduleRules = $this->repository->moduleRules();
        $configuredSectors = array_fill_keys($this->repository->configuredSectorIds(), true);
        $rows = [];
        $active = 0;
        $inactive = 0;
        $users = 0;

        foreach ($this->repository->all() as $sector) {
            $sectorId = (int) ($sector['id'] ?? 0);
            $isActive = (int) ($sector['ativo'] ?? 0) === 1;
            $sectorUsers = (int) ($sector['usuarios'] ?? 0);
            $slug = (string) ($sector['slug'] ?? '');
            $hasExplicitConfiguration = isset($configuredSectors[$sectorId]);
            $allowed = $hasExplicitConfiguration
                ? array_values(array_filter(
                    $moduleRules[$sectorId] ?? [],
                    static fn (string $module): bool => isset($moduleCatalog[$module])
                ))
                : array_keys($moduleCatalog);
            sort($allowed, SORT_STRING);

            $users += $sectorUsers;
            $isActive ? $active++ : $inactive++;

            $rows[] = [
                'id' => $sectorId,
                'name' => (string) ($sector['nome'] ?? 'Setor'),
                'slug' => $slug,
                'description' => trim((string) ($sector['descricao'] ?? '')),
                'users' => $sectorUsers,
                'active' => $isActive,
                'protected' => in_array($slug, self::PROTECTED_SECTORS, true),
                'configuration_mode' => $hasExplicitConfiguration ? 'explicit' : 'legacy_fallback',
                'allowed_modules' => $allowed,
                'allowed_module_labels' => array_values(array_map(
                    static fn (string $module): string => (string) ($moduleCatalog[$module]['label'] ?? $module),
                    $allowed
                )),
            ];
        }

        return [
            'rows' => $rows,
            'modules' => $moduleCatalog,
            'stats' => [
                ['label' => 'Setores cadastrados', 'value' => (string) count($rows), 'detail' => 'Estrutura organizacional', 'icon' => 'diagram-3'],
                ['label' => 'Ativos', 'value' => (string) $active, 'detail' => 'Disponíveis para operação', 'icon' => 'check-circle'],
                ['label' => 'Inativos', 'value' => (string) $inactive, 'detail' => 'Preservados sem operação', 'icon' => 'pause-circle'],
                ['label' => 'Usuários vinculados', 'value' => (string) $users, 'detail' => 'Contas com setor definido', 'icon' => 'people'],
            ],
        ];
    }

    /** @param list<string> $modules @return array{message:string,sector_id:int,slug:string} */
    public function create(
        User $operator,
        string $name,
        ?string $description,
        array $modules,
        string $reason,
    ): array {
        $this->assertCanManage($operator);
        [$name, $description, $modules, $reason] = $this->normalize($name, $description, $modules, $reason);
        $slug = $this->uniqueSlug($name);

        $sectorId = Database::transaction(function () use ($operator, $name, $slug, $description, $modules, $reason): int {
            $sectorId = $this->repository->create($name, $slug, $description);
            $this->repository->replaceModuleRules($sectorId, $modules, $operator->id);
            $this->audit->record(
                $operator->id,
                null,
                'setor_criado',
                'governanca',
                $reason,
                null,
                [
                    'setor_id' => $sectorId,
                    'nome' => $name,
                    'slug' => $slug,
                    'descricao' => $description,
                    'modulos' => $modules,
                    'ativo' => true,
                ]
            );
            return $sectorId;
        });

        return [
            'message' => 'Setor criado com sucesso.',
            'sector_id' => $sectorId,
            'slug' => $slug,
        ];
    }

    /** @param list<string> $modules @return array{message:string,affected_users:int,revoked_sessions:int} */
    public function update(
        User $operator,
        int $sectorId,
        string $name,
        ?string $description,
        array $modules,
        string $reason,
    ): array {
        $this->assertCanManage($operator);
        $current = $this->requireSector($sectorId);
        [$name, $description, $modules, $reason] = $this->normalize($name, $description, $modules, $reason);

        $configuredSectors = array_fill_keys($this->repository->configuredSectorIds(), true);
        $currentModules = isset($configuredSectors[$sectorId])
            ? ($this->repository->moduleRules()[$sectorId] ?? [])
            : array_keys(AccessModuleCatalog::operational());
        sort($currentModules, SORT_STRING);
        $nextModules = $modules;
        sort($nextModules, SORT_STRING);

        $before = [
            'setor_id' => $sectorId,
            'nome' => (string) ($current['nome'] ?? ''),
            'slug' => (string) ($current['slug'] ?? ''),
            'descricao' => $current['descricao'] ?? null,
            'modulos' => $currentModules,
            'ativo' => (int) ($current['ativo'] ?? 0) === 1,
        ];
        $after = [
            'setor_id' => $sectorId,
            'nome' => $name,
            'slug' => (string) ($current['slug'] ?? ''),
            'descricao' => $description,
            'modulos' => $nextModules,
            'ativo' => (int) ($current['ativo'] ?? 0) === 1,
        ];

        if ($before === $after) {
            return ['message' => 'Nenhuma alteração foi necessária.', 'affected_users' => 0, 'revoked_sessions' => 0];
        }

        $modulesChanged = $currentModules !== $nextModules;
        $result = Database::transaction(function () use ($operator, $sectorId, $name, $description, $modules, $reason, $before, $after, $modulesChanged): array {
            $this->repository->update($sectorId, $name, $description);
            $this->repository->replaceModuleRules($sectorId, $modules, $operator->id);

            $affectedUsers = 0;
            $revokedSessions = 0;
            if ($modulesChanged) {
                $affectedUsers = $this->repository->incrementAuthorizationVersionsForSector($sectorId);
                $revokedSessions = $this->repository->revokeActiveSessionsForSector($sectorId);
            }

            $this->audit->record(
                $operator->id,
                null,
                'setor_atualizado',
                'governanca',
                $reason,
                $before,
                $after
            );

            return ['affected_users' => $affectedUsers, 'revoked_sessions' => $revokedSessions];
        });

        return [
            'message' => $modulesChanged
                ? 'Setor atualizado. As sessões dos usuários afetados foram encerradas para aplicar a nova matriz de módulos.'
                : 'Setor atualizado com sucesso.',
            'affected_users' => (int) $result['affected_users'],
            'revoked_sessions' => (int) $result['revoked_sessions'],
        ];
    }

    /** @return array{message:string,affected_users:int,revoked_sessions:int} */
    public function setActive(User $operator, int $sectorId, bool $active, string $reason): array
    {
        $this->assertCanManage($operator);
        $current = $this->requireSector($sectorId);
        $reason = $this->normalizeReason($reason);
        $slug = (string) ($current['slug'] ?? '');
        $currentActive = (int) ($current['ativo'] ?? 0) === 1;

        if (!$active && in_array($slug, self::PROTECTED_SECTORS, true)) {
            throw new AuthorizationException('Este setor é estrutural do SIGAS e não pode ser inativado pela interface.');
        }

        if ($currentActive === $active) {
            return [
                'message' => $active ? 'O setor já está ativo.' : 'O setor já está inativo.',
                'affected_users' => 0,
                'revoked_sessions' => 0,
            ];
        }

        $result = Database::transaction(function () use ($operator, $sectorId, $active, $reason, $current): array {
            $this->repository->setActive($sectorId, $active);
            $affectedUsers = $this->repository->incrementAuthorizationVersionsForSector($sectorId);
            $revokedSessions = $this->repository->revokeActiveSessionsForSector($sectorId);

            $this->audit->record(
                $operator->id,
                null,
                $active ? 'setor_ativado' : 'setor_inativado',
                'governanca',
                $reason,
                [
                    'setor_id' => $sectorId,
                    'nome' => (string) ($current['nome'] ?? ''),
                    'slug' => (string) ($current['slug'] ?? ''),
                    'ativo' => !$active,
                ],
                [
                    'setor_id' => $sectorId,
                    'nome' => (string) ($current['nome'] ?? ''),
                    'slug' => (string) ($current['slug'] ?? ''),
                    'ativo' => $active,
                ]
            );

            return ['affected_users' => $affectedUsers, 'revoked_sessions' => $revokedSessions];
        });

        return [
            'message' => $active
                ? 'Setor ativado e novamente disponível para operação.'
                : 'Setor inativado. Usuários operacionais vinculados precisam ser transferidos para um setor ativo.',
            'affected_users' => (int) $result['affected_users'],
            'revoked_sessions' => (int) $result['revoked_sessions'],
        ];
    }

    private function assertCanManage(User $operator): void
    {
        if (!$this->authorization->isAdministrator($operator) && !$this->authorization->isSupport($operator)) {
            throw new AuthorizationException('A gestão de setores é restrita à Governança e Acessos.');
        }
        $this->authorization->requirePermission($operator, 'governanca.setores');
    }

    /** @return array<string,mixed> */
    private function requireSector(int $sectorId): array
    {
        if ($sectorId <= 0) {
            throw new InvalidArgumentException('Setor inválido.');
        }
        $sector = $this->repository->findById($sectorId);
        if ($sector === null) {
            throw new InvalidArgumentException('Setor não localizado.');
        }
        return $sector;
    }

    /** @param list<string> $modules @return array{0:string,1:?string,2:list<string>,3:string} */
    private function normalize(string $name, ?string $description, array $modules, string $reason): array
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Informe um nome de setor entre 2 e 150 caracteres.');
        }

        $description = $description === null ? null : trim($description);
        if ($description === '') {
            $description = null;
        }
        if ($description !== null && mb_strlen($description) > 255) {
            throw new InvalidArgumentException('A descrição deve ter no máximo 255 caracteres.');
        }

        $allowedKeys = array_keys(AccessModuleCatalog::operational());
        $modules = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $modules),
            static fn (string $module): bool => $module !== ''
        )));

        foreach ($modules as $module) {
            if (!in_array($module, $allowedKeys, true)) {
                throw new InvalidArgumentException('A configuração contém um módulo inválido. Atualize a página e tente novamente.');
            }
        }
        sort($modules, SORT_STRING);

        return [$name, $description, $modules, $this->normalizeReason($reason)];
    }

    private function normalizeReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Informe uma justificativa com pelo menos 5 caracteres.');
        }
        return mb_substr($reason, 0, 500);
    }

    private function uniqueSlug(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $base = strtolower((string) ($ascii !== false ? $ascii : $name));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'setor-' . substr(hash('sha256', $name), 0, 8);
        }
        $base = substr($base, 0, 70);

        $slug = $base;
        $suffix = 2;
        while ($this->repository->slugExists($slug)) {
            $ending = '-' . $suffix++;
            $slug = substr($base, 0, 80 - strlen($ending)) . $ending;
        }
        return $slug;
    }
}
