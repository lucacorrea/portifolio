<?php
declare(strict_types=1);

namespace App\Company\Service;

use App\Access\DTO\AuthenticatedUser;
use App\Access\Exception\AuthorizationException;
use App\Access\Repository\UserRepository;
use App\Admin\Repository\AdminAccessRepository;
use App\Company\DTO\CompanyScope;

final class OperationalCompanyContextResolver
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AdminAccessRepository $adminAccesses,
        private readonly ActiveCompanyContext $activeCompanyContext
    ) {
    }

    public function resolve(AuthenticatedUser $user): CompanyScope
    {
        if ($user->isPlatformAdministrator()) {
            return $this->resolveSupport($user);
        }

        $this->activeCompanyContext->clear();
        $membership = $this->users->findPrimaryActiveCompanyMembership($user->id());
        if ($membership === null) {
            throw new AuthorizationException('Nenhuma empresa operacional ativa foi encontrada para este usuário.');
        }

        $membershipId = (int) ($membership['membership_id'] ?? 0);
        $companyId = (int) ($membership['empresa_id'] ?? 0);
        $profileId = (int) ($membership['perfil_id'] ?? 0);
        if ($membershipId <= 0 || $companyId <= 0 || $profileId <= 0) {
            throw new AuthorizationException('O vínculo empresarial do usuário é inválido.');
        }

        return new CompanyScope(
            $companyId,
            (string) ($membership['empresa_uuid'] ?? ''),
            (string) ($membership['empresa_nome'] ?? 'Empresa'),
            $user->id(),
            'member',
            $this->users->findCompanyMembershipPermissions($membershipId),
            $membershipId,
            null,
            $profileId,
            (string) ($membership['perfil_codigo'] ?? ''),
            (string) ($membership['perfil_nome'] ?? '')
        );
    }

    private function resolveSupport(AuthenticatedUser $user): CompanyScope
    {
        $active = $this->activeCompanyContext->current();
        $bindingHash = $user->sessionBindingHash();
        if ($active === null || !preg_match('/^[a-f0-9]{64}$/D', $bindingHash)) {
            $this->activeCompanyContext->clear();
            throw new AuthorizationException('Inicie um atendimento administrativo antes de acessar o painel operacional.');
        }

        $access = $this->adminAccesses->findOpenAuthorized(
            $active->accessId,
            $user->id(),
            $active->id,
            $bindingHash
        );
        if ($access === null) {
            $this->adminAccesses->closeForUserAndCompany(
                $active->accessId,
                $user->id(),
                $active->id
            );
            $this->activeCompanyContext->clear();
            throw new AuthorizationException('O atendimento administrativo expirou ou não é mais válido.');
        }

        return new CompanyScope(
            (int) $access['empresa_id'],
            (string) ($access['uuid'] ?? ''),
            (string) ($access['empresa_nome'] ?? 'Empresa'),
            $user->id(),
            'support',
            $user->permissions(),
            null,
            (int) $access['id'],
            $user->profileId(),
            $user->profileCode(),
            $user->profileName()
        );
    }
}
