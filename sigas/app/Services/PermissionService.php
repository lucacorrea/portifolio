<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PermissionRepository;

final class PermissionService
{
    /** @var array<int, list<string>> */
    private array $cache = [];

    /** @var array<string, bool|null> */
    private array $userOverrideCache = [];

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
     * Regra efetiva:
     * 1. exceção individual explícita;
     * 2. na ausência dela, herança normal do nível.
     */
    public function hasPermissionForUser(int $userId, int $levelId, string $permission): bool
    {
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
}
