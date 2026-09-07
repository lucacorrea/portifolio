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
     * Regra efetiva de uma ação:
     * 1. bloqueio individual explícito do módulo vence tudo;
     * 2. exceção individual da ação;
     * 3. na ausência dela, herança normal do nível.
     *
     * Um "Liberar módulo" não concede automaticamente ações internas. Ele apenas
     * remove a barreira de entrada; as ações continuam dependentes do nível ou
     * de uma exceção positiva específica.
     */
    public function hasPermissionForUser(int $userId, int $levelId, string $permission): bool
    {
        if ($this->isExplicitlyBlockedByModule($userId, $permission)) {
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

        return $this->hasPermission($levelId, $permission);
    }

    private function isExplicitlyBlockedByModule(int $userId, string $permission): bool
    {
        if (!array_key_exists($permission, $this->permissionModuleCache)) {
            $this->permissionModuleCache[$permission] = $this->permissions->permissionModule($permission);
        }

        $permissionModule = $this->permissionModuleCache[$permission];
        if ($permissionModule === null) {
            return false;
        }

        $publicModule = AccessModuleCatalog::permissionModuleIndex()[$permissionModule] ?? null;
        if (!is_string($publicModule) || $publicModule === '') {
            return false;
        }

        $cacheKey = $userId . ':' . $publicModule;
        if (!array_key_exists($cacheKey, $this->userModuleOverrideCache)) {
            $this->userModuleOverrideCache[$cacheKey] = $this->permissions->userModuleOverride($userId, $publicModule);
        }

        return $this->userModuleOverrideCache[$cacheKey] === false;
    }
}
