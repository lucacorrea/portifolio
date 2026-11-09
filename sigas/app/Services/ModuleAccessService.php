<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AccessModuleCatalog;
use App\Models\User;
use App\Repositories\ModuleAccessRepository;

final class ModuleAccessService
{
    public function __construct(
        private readonly ModuleAccessRepository $repository,
        private readonly AuthorizationService $authorization,
    ) {
    }

    public function canAccess(User $user, string $module): bool
    {
        if ($user->status->value !== 'ativo') {
            return false;
        }

        if ($this->authorization->isAdministrator($user) || $this->authorization->isSupport($user)) {
            return true;
        }

        // Governança é deliberadamente imune a exceções individuais de usuários comuns.
        if ($module === 'gestao-acessos') {
            return false;
        }

        $override = $this->repository->userOverride($user->id, $module);
        if ($override !== null) {
            return $override && $this->hasModulePermission($user, $module);
        }

        if ($user->setorId === null) {
            return false;
        }

        if (!$this->repository->sectorHasConfiguration($user->setorId)) {
            return $this->hasModulePermission($user, $module);
        }

        return $this->repository->sectorAllows($user->setorId, $module)
            && $this->hasModulePermission($user, $module);
    }

    /** @param array<string,array<string,mixed>> $navigation */
    public function filterNavigation(User $user, array $navigation): array
    {
        return array_filter(
            $navigation,
            fn (array $environment, string $key): bool => $this->canAccess($user, $key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function hasModulePermission(User $user, string $module): bool
    {
        $definition = AccessModuleCatalog::operational()[$module] ?? null;
        $permission = is_array($definition) ? ($definition['view_permission'] ?? null) : null;

        return !is_string($permission) || $permission === '' || $this->authorization->can($user, $permission);
    }
}
