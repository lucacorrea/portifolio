<?php

declare(strict_types=1);

namespace App\Security;

use App\Access\Repository\ProfilePermissionRepository;
use App\Access\Repository\ProfileRepository;
use App\Access\Repository\UserRepository;
use InvalidArgumentException;

final class PrivilegedAuthorizationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ProfilePermissionRepository $profilePermissions,
        private readonly ProfileRepository $profiles
    ) {
    }

    public function authorize(string $identifier, string $password, string $permission): int
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '' || trim($permission) === '') {
            throw new InvalidArgumentException('Informe autorizador, senha e permissão.');
        }

        $user = $this->users->findByIdentifier($identifier);
        if ($user === null || $user->id() === null || $user->status() !== 'ativo') {
            throw new InvalidArgumentException('Autorizador inválido.');
        }

        if (!password_verify($password, $user->passwordHash())) {
            throw new InvalidArgumentException('Autorização negada.');
        }

        $profile = $this->profiles->findById($user->profileId());
        $isPlatformAdministrator = $profile !== null
            && in_array($profile->code(), ['suporte', 'super_admin'], true);
        $permissions = $this->profilePermissions->findPermissionCodesByProfile($user->profileId());
        if (!$isPlatformAdministrator && !in_array($permission, $permissions, true)) {
            throw new InvalidArgumentException('Autorizador sem permissão necessária.');
        }

        return $user->id();
    }
}
