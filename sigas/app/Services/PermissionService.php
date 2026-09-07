<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AccessModuleCatalog;
use App\Repositories\PermissionRepository;

final class PermissionService
{
    /** @var array<int, list<string>> */
    private array $cache = [];

    /** @var array<string, bool|null> */
    private array $userOverrideCache = [];

    /** @var array<string, string|null> */
    private array $permissionModuleCache = [];

    /** @var array<string, bool|null> */
    private array $userModuleOverrideCache = [];

    /** @var array<int,bool> */
    private array $sectorConfigurationCache = [];

    /** @var array<string,bool> */
    private array $sectorModuleCache = [];

    public function __construct(private readonly PermissionRepository $permissions)
    {
    }

    /** @return list<string> */
    public function getPermissionsForLevel(int $levelId): array
    {
        if (!array_key_exists($levelId, $this->cache)) {
            $this->cache[$levelId] = $this->permissions->findSlugsByLevelId($levelId);
        }

        return $this->cache[$levelId];
    }

    public function hasPermission(int $levelId, string $permission): bool
    {
        return in_array($permission, $this->getPermissionsForLevel($levelId), true);
    }

    /**
     * Regra efetiva de uma ação para usuário operacional:
     * 1. módulo precisa estar efetivamente acessível (exceção individual ou setor);
     * 2. exceção individual da ação;
     * 3. uma liberação individual do módulo concede a permissão básica de visualização;
     * 4. demais ações continuam herdando normalmente do nível.
     */
    public function hasPermissionForUser(
        int $userId,
        int $levelId,
        ?int $sectorId,
        string $permission,
    ): bool {
        if (!$this->passesModuleBarrier($userId, $sectorId, $permission)) {
            return false;
        }

        $cacheKey = $userId . ':' . $permission;

        if (!array_key_exists($cacheKey, $this->userOverrideCache)) {
            $this->userOverrideCache[$cacheKey] = $this->permissions->userPermissionOverride($userId, $permission);
        }

        $override = $this->userOverrideCache[$cacheKey];
        if ($override !== null) {
            return $override;
        }

        if ($this->individualModuleOverrideGrantsView($userId, $permission)) {
            return true;
        }

        return $this->hasPermission($levelId, $permission);
    }

    private function individualModuleOverrideGrantsView(int $userId, string $permission): bool
    {
        $publicModule = $this->publicModuleForViewPermission($permission);
        if ($publicModule === null) {
            return false;
        }

        return $this->userModuleOverride($userId, $publicModule) === true;
    }

    private function passesModuleBarrier(int $userId, ?int $sectorId, string $permission): bool
    {
        $permissionModule = $this->permissionModule($permission);
        $publicModule = null;

        if ($permissionModule !== null) {
            $candidate = AccessModuleCatalog::permissionModuleIndex()[$permissionModule] ?? null;
            if (is_string($candidate) && $candidate !== '') {
                $publicModule = $candidate;
            }
        }

        // A permissão de entrada do módulo também é reconhecida pelo catálogo
        // mesmo quando a linha correspondente ainda não existe na tabela permissoes.
        // Isso mantém portal, rota e Governança coerentes durante atualizações parciais.
        $publicModule ??= $this->publicModuleForViewPermission($permission);

        if ($publicModule === null) {
            return true;
        }

        $individualModuleRule = $this->userModuleOverride($userId, $publicModule);
        if ($individualModuleRule !== null) {
            return $individualModuleRule;
        }

        if ($sectorId === null || $sectorId <= 0) {
            return false;
        }

        if (!array_key_exists($sectorId, $this->sectorConfigurationCache)) {
            $this->sectorConfigurationCache[$sectorId] = $this->permissions->sectorHasModuleConfiguration($sectorId);
        }

        if (!$this->sectorConfigurationCache[$sectorId]) {
            return true;
        }

        $sectorModuleKey = $sectorId . ':' . $publicModule;
        if (!array_key_exists($sectorModuleKey, $this->sectorModuleCache)) {
            $this->sectorModuleCache[$sectorModuleKey] = $this->permissions->sectorAllowsModule($sectorId, $publicModule);
        }

        return $this->sectorModuleCache[$sectorModuleKey];
    }

    private function publicModuleForViewPermission(string $permission): ?string
    {
        foreach (AccessModuleCatalog::operational() as $moduleKey => $definition) {
            if ((string) ($definition['view_permission'] ?? '') === $permission) {
                return $moduleKey;
            }
        }

        return null;
    }

    private function permissionModule(string $permission): ?string
    {
        if (!array_key_exists($permission, $this->permissionModuleCache)) {
            $this->permissionModuleCache[$permission] = $this->permissions->permissionModule($permission);
        }

        return $this->permissionModuleCache[$permission];
    }

    private function userModuleOverride(int $userId, string $publicModule): ?bool
    {
        $userModuleKey = $userId . ':' . $publicModule;
        if (!array_key_exists($userModuleKey, $this->userModuleOverrideCache)) {
            $this->userModuleOverrideCache[$userModuleKey] = $this->permissions->userModuleOverride($userId, $publicModule);
        }

        return $this->userModuleOverrideCache[$userModuleKey];
    }
}
