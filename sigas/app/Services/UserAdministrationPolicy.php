<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\AccessLevelSlug;
use App\Domain\UserStatus;
use App\DTO\UserAccessChangeData;
use App\DTO\UserApprovalData;
use App\Exceptions\AuthorizationException;
use App\Models\AccessLevel;
use App\Models\Sector;
use App\Models\User;
use App\Repositories\UserRepository;

final class UserAdministrationPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly UserRepository $users,
    ) {
    }

    public function assertCanBlock(User $operator, User $target): void
    {
        $this->authorization->requirePermission($operator, 'usuarios.bloquear');

        if (!$this->authorization->canManageUser($operator, $target, 'usuarios.bloquear')) {
            throw new AuthorizationException('Usuário não autorizado a bloquear esta conta.');
        }

        if ($operator->id === $target->id) {
            throw new AuthorizationException('Usuário não pode bloquear a própria conta.');
        }

        $this->protectLastAdministrator($target, UserStatus::BLOCKED);
    }

    public function assertCanInactivate(User $operator, User $target): void
    {
        // Permissão temporária: criar `usuarios.inativar` em uma migração futura.
        $this->authorization->requirePermission($operator, 'usuarios.editar');

        if (!$this->authorization->canManageUser($operator, $target, 'usuarios.editar')) {
            throw new AuthorizationException('Usuário não autorizado a inativar esta conta.');
        }

        if ($operator->id === $target->id) {
            throw new AuthorizationException('Usuário não pode inativar a própria conta.');
        }

        $this->protectLastAdministrator($target, UserStatus::INACTIVE);
    }

    public function assertCanAssignLevel(User $operator, User $target, AccessLevel $newLevel): void
    {
        if (!$this->authorization->canAssignLevel($operator, $newLevel)) {
            throw new AuthorizationException('Nível não autorizado.');
        }

        if ($operator->id === $target->id && $this->authorization->isSupport($operator)) {
            throw new AuthorizationException('Suporte não pode alterar o próprio nível.');
        }

        if ($newLevel->slug === AccessLevelSlug::ADMINISTRATOR->value && !$this->authorization->canPromoteAdministrator($operator)) {
            throw new AuthorizationException('Promoção para administrador negada.');
        }

        if ($newLevel->slug === AccessLevelSlug::SUPPORT->value && !$this->authorization->canPromoteAdministrator($operator)) {
            throw new AuthorizationException('Promoção para suporte negada.');
        }

        if ($target->status === UserStatus::ACTIVE && $this->authorization->isAdministrator($target) && $newLevel->slug !== AccessLevelSlug::ADMINISTRATOR->value) {
            $this->protectLastAdministrator($target, null);
        }
    }

    public function assertCanAssignSector(Sector $sector): void
    {
        if (!$sector->ativo || $sector->excluidoEm !== null) {
            throw new AuthorizationException('Setor indisponível.');
        }
    }

    public function assertPendingHasNoOperationalAccess(User $user): void
    {
        if ($user->status === UserStatus::PENDING && ($user->setorId !== null || $user->nivelId !== null)) {
            throw new AuthorizationException('Pendente não recebe acesso operacional.');
        }
    }

    public function approvePending(User $operator, User $target, UserApprovalData $approval, Sector $sector, AccessLevel $level): int
    {
        $this->authorization->requirePermission($operator, 'usuarios.aprovar');

        if ($target->status !== UserStatus::PENDING) {
            throw new AuthorizationException('Apenas usuários pendentes podem ser aprovados.');
        }

        if ($approval->setorId !== $sector->id || $approval->nivelId !== $level->id) {
            throw new AuthorizationException('Dados de aprovação inconsistentes.');
        }

        $this->assertCanAssignSector($sector);
        $this->assertCanAssignLevel($operator, $target, $level);

        $this->users->assignSector($target->id, $approval->setorId);
        $this->users->assignAccessLevel($target->id, $approval->nivelId);
        $this->users->updateStatus($target->id, UserStatus::ACTIVE);

        return $this->users->incrementAuthorizationVersion($target->id);
    }

    public function changeAccess(User $operator, User $target, UserAccessChangeData $change, ?Sector $sector, ?AccessLevel $level): ?int
    {
        if ($change->operadorId !== $operator->id || trim($change->motivo) === '') {
            throw new AuthorizationException('Alteração de acesso inválida.');
        }

        if ($target->status === UserStatus::PENDING) {
            throw new AuthorizationException('Usuário pendente deve ser aprovado antes de receber acesso operacional.');
        }

        $changed = false;

        if ($sector !== null) {
            if ($change->novoSetorId !== $sector->id) {
                throw new AuthorizationException('Setor de destino inconsistente.');
            }

            $this->authorization->requirePermission($operator, 'usuarios.alterar_setor');
            $this->assertCanAssignSector($sector);

            if ($target->setorId !== $sector->id) {
                $this->users->assignSector($target->id, $sector->id);
                $changed = true;
            }
        }

        if ($level !== null) {
            if ($change->novoNivelId !== $level->id) {
                throw new AuthorizationException('Nível de destino inconsistente.');
            }

            $this->assertCanAssignLevel($operator, $target, $level);

            if ($target->nivelId !== $level->id) {
                $this->users->assignAccessLevel($target->id, $level->id);
                $changed = true;
            }
        }

        return $changed ? $this->users->incrementAuthorizationVersion($target->id) : null;
    }

    private function protectLastAdministrator(User $target, ?UserStatus $newStatus): void
    {
        if ($target->nivelId === null || $target->status !== UserStatus::ACTIVE || !$this->authorization->isAdministrator($target)) {
            return;
        }

        if ($this->users->countActiveAdministrators() <= 1 && ($newStatus === null || $newStatus !== UserStatus::ACTIVE)) {
            throw new AuthorizationException('Último administrador ativo não pode ser removido.');
        }
    }

}
