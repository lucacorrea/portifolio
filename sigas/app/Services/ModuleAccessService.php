<?php

declare(strict_types=1);

namespace App\Services;

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

        if ($module === 'gestao-acessos') {
            return false;
        }

        $override = $this->repository->userOverride($user->id, $module);
        if ($override !== null) {
            return $override;
        }

        if ($user->setorId === null) {
            return false;
        }

        // Segurança de implantação: enquanto um setor não tiver matriz configurada,
        // preserva o comportamento atual. Após a primeira configuração, passa a aplicar a matriz.
        if (!$this->repository->sectorHasConfiguration($user->setorId)) {
            return true;
        }

        return $this->repository->sectorAllows($user->setorId, $module);
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
}
