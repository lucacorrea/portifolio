<?php
declare(strict_types=1);

namespace App\Admin\Service;

use App\Access\DTO\AuthenticatedUser;
use App\Access\Exception\AuthorizationException;

final class PlatformAdminPolicy
{
    public function canAccess(AuthenticatedUser $user): bool
    {
        return $user->isPlatformAdministrator();
    }

    public function requireAccess(AuthenticatedUser $user): void
    {
        if (!$this->canAccess($user)) throw new AuthorizationException('Acesso negado.');
    }
}
