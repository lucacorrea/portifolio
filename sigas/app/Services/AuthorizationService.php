<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\AccessLevelSlug;
use App\Exceptions\AuthorizationException;
use App\Models\AccessLevel;
use App\Models\User;
use App\Repositories\AccessLevelRepository;

final class AuthorizationService
{
    /** @var array<int, AccessLevel|null> */
    private array $levelCache = [];

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly AccessLevelRepository $accessLevels,
    ) {
    }

    public function can(User $user, string $permission): bool
    {
        return $user->nivelId !== null
            && $user->status->value === 'ativo'
            && $this->permissions->hasPermission($user->nivelId, $permission);
    }

    public function requirePermission(User $user, string $permission): void
    {
        if (!$this->can($user, $permission)) {
            throw new AuthorizationException('Acesso negado.');
        }
    }

    public function canAccessOperationalSector(User $user, int $sectorId): bool
    {
        if ($user->status->value !== 'ativo') {
            return false;
        }

        if ($this->isAdministrator($user)) {
            return true;
        }

        if ($this->isSupport($user)) {
            return false;
        }

        return $user->setorId === $sectorId;
    }

    public function requireOperationalSector(User $user, int $sectorId): void
    {
        if (!$this->canAccessOperationalSector($user, $sectorId)) {
            throw new AuthorizationException('Acesso ao setor negado.');
        }
    }

    public function canManageUser(User $operator, User $target, string $permission): bool
    {
        if (!$this->can($operator, $permission)) {
            return false;
        }

        return $this->isAdministrator($operator) || $this->isSupport($operator);
    }

    public function canAssignLevel(User $operator, AccessLevel $targetLevel): bool
    {
        if (!$this->can($operator, 'usuarios.alterar_nivel')) {
            return false;
        }

        if ($this->isSupport($operator)) {
            return !in_array($targetLevel->slug, [AccessLevelSlug::SUPPORT->value, AccessLevelSlug::ADMINISTRATOR->value], true);
        }

        if (!$this->isAdministrator($operator)) {
            return false;
        }

        if ($targetLevel->slug === AccessLevelSlug::ADMINISTRATOR->value) {
            return $this->canPromoteAdministrator($operator);
        }

        return true;
    }

    public function canPromoteAdministrator(User $operator): bool
    {
        return $this->isAdministrator($operator)
            && $this->can($operator, 'usuarios.promover_administrador');
    }

    public function isAdministrator(User $user): bool
    {
        $level = $this->levelFor($user);

        return $level !== null
            && $level->ativo
            && $level->slug === AccessLevelSlug::ADMINISTRATOR->value;
    }

    public function isSupport(User $user): bool
    {
        $level = $this->levelFor($user);

        return $level !== null
            && $level->ativo
            && $level->slug === AccessLevelSlug::SUPPORT->value;
    }

    private function levelFor(User $user): ?AccessLevel
    {
        if ($user->nivelId === null) {
            return null;
        }

        if (!array_key_exists($user->nivelId, $this->levelCache)) {
            $this->levelCache[$user->nivelId] = $this->accessLevels->findById($user->nivelId);
        }

        return $this->levelCache[$user->nivelId];
    }
}
