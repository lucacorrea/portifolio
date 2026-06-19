<?php
declare(strict_types=1);

namespace App\Access\Service;

use App\Access\DTO\AuthenticatedUser;
use App\Access\Exception\AuthorizationException;

final class AuthorizationService
{
    public function __construct(private readonly AuthenticationService $authentication)
    {
    }

    public function currentUser(): ?AuthenticatedUser
    {
        return $this->authentication->currentUser();
    }

    public function requireLogin(): AuthenticatedUser
    {
        return $this->authentication->requireAuthenticatedUser();
    }

    public function can(string $permission): bool
    {
        $user = $this->currentUser();
        if ($user === null || trim($permission) === '') {
            return false;
        }

        return in_array($permission, $user->permissions(), true);
    }

    /**
     * @param string[] $permissions
     */
    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can((string) $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $permissions
     */
    public function canAll(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->can((string) $permission)) {
                return false;
            }
        }

        return $permissions !== [];
    }

    public function requirePermission(string $permission): void
    {
        if (!$this->can($permission)) {
            throw new AuthorizationException('Acesso negado.');
        }
    }

    /**
     * @param string[] $permissions
     */
    public function requireAnyPermission(array $permissions): void
    {
        if (!$this->canAny($permissions)) {
            throw new AuthorizationException('Acesso negado.');
        }
    }
}
