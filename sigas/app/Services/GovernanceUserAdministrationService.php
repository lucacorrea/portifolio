<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Domain\UserStatus;
use App\DTO\UserAccessChangeData;
use App\DTO\UserApprovalData;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Repositories\AccessLevelRepository;
use App\Repositories\GovernanceUserAdminRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use InvalidArgumentException;

final class GovernanceUserAdministrationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserSessionRepository $sessions,
        private readonly SectorRepository $sectors,
        private readonly AccessLevelRepository $levels,
        private readonly UserAdministrationPolicy $policy,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit,
        private readonly GovernanceUserAdminRepository $governance,
    ) {
    }

    /** @return array{message:string,revoked_sessions:int} */
    public function execute(
        User $operator,
        string $action,
        int $targetUserId,
        ?int $sectorId,
        ?int $levelId,
        string $reason,
    ): array {
        $action = trim($action);
        $reason = $this->normalizeReason($reason);
        $target = $this->users->findById($targetUserId);

        if (!$target instanceof User) {
            throw new InvalidArgumentException('Usuário não localizado.');
        }

        return match ($action) {
            'approve' => $this->approve($operator, $target, $sectorId, $levelId, $reason),
            'change_access' => $this->changeAccess($operator, $target, $sectorId, $levelId, $reason),
            'block' => $this->block($operator, $target, $reason),
            'unblock' => $this->unblock($operator, $target, $reason),
            'revoke_sessions' => $this->revokeSessions($operator, $target, $reason),
            default => throw new InvalidArgumentException('Ação administrativa inválida.'),
        };
    }

    /** @return array{message:string,revoked_sessions:int} */
    private function approve(User $operator, User $target, ?int $sectorId, ?int $levelId, string $reason): array
    {
        if ($sectorId === null || $levelId === null) {
            throw new InvalidArgumentException('Informe o setor e o nível para aprovar o acesso.');
        }

        $sector = $this->sectors->findActiveById($sectorId);
        $level = $this->levels->findActiveById($levelId);

        if ($sector === null || $level === null) {
            throw new InvalidArgumentException('Setor ou nível de acesso indisponível.');
        }

        if (!$this->authorization->canManageUser($operator, $target, 'usuarios.aprovar')) {
            throw new AuthorizationException('Usuário não autorizado a aprovar esta conta.');
        }
        $this->authorization->requirePermission($operator, 'usuarios.alterar_setor');

        $before = $this->snapshot($target);
        $approval = new UserApprovalData($sectorId, $levelId, $reason, $operator->id);

        return Database::transaction(function () use ($operator, $target, $approval, $sector, $level, $before, $reason): array {
            $this->policy->approvePending($operator, $target, $approval, $sector, $level);
            $this->governance->registerApproval($target->id, $operator->id, $reason);
            $revoked = $this->sessions->revokeAllForUser($target->id);
            $updated = $this->requireUpdated($target->id);

            $this->audit->record(
                $operator->id,
                $target->id,
                'usuario_aprovado',
                'governanca',
                $reason,
                $before,
                $this->snapshot($updated)
            );

            return [
                'message' => 'Usuário aprovado e acesso liberado.',
                'revoked_sessions' => $revoked,
            ];
        });
    }

    /** @return array{message:string,revoked_sessions:int} */
    private function changeAccess(User $operator, User $target, ?int $sectorId, ?int $levelId, string $reason): array
    {
        if ($operator->id === $target->id) {
            throw new AuthorizationException('Não é permitido alterar o próprio setor ou nível por esta tela.');
        }

        $newSectorId = $sectorId !== null && $sectorId !== $target->setorId ? $sectorId : null;
        $newLevelId = $levelId !== null && $levelId !== $target->nivelId ? $levelId : null;

        if ($newSectorId === null && $newLevelId === null) {
            return ['message' => 'Nenhuma alteração de acesso foi necessária.', 'revoked_sessions' => 0];
        }

        $sector = $newSectorId === null ? null : $this->sectors->findActiveById($newSectorId);
        $level = $newLevelId === null ? null : $this->levels->findActiveById($newLevelId);

        if ($newSectorId !== null && $sector === null) {
            throw new InvalidArgumentException('Setor de destino indisponível.');
        }
        if ($newLevelId !== null && $level === null) {
            throw new InvalidArgumentException('Nível de destino indisponível.');
        }

        if ($newSectorId !== null && !$this->authorization->canManageUser($operator, $target, 'usuarios.alterar_setor')) {
            throw new AuthorizationException('Usuário não autorizado a alterar o setor desta conta.');
        }
        if ($newLevelId !== null && !$this->authorization->canManageUser($operator, $target, 'usuarios.alterar_nivel')) {
            throw new AuthorizationException('Usuário não autorizado a alterar o nível desta conta.');
        }

        $before = $this->snapshot($target);
        $change = new UserAccessChangeData($newSectorId, $newLevelId, $reason, $operator->id);

        return Database::transaction(function () use ($operator, $target, $change, $sector, $level, $before, $reason): array {
            $newVersion = $this->policy->changeAccess($operator, $target, $change, $sector, $level);
            $revoked = $newVersion === null ? 0 : $this->sessions->revokeAllForUser($target->id);
            $updated = $this->requireUpdated($target->id);

            $this->audit->record(
                $operator->id,
                $target->id,
                'usuario_acesso_alterado',
                'governanca',
                $reason,
                $before,
                $this->snapshot($updated)
            );

            return [
                'message' => 'Setor e nível de acesso atualizados.',
                'revoked_sessions' => $revoked,
            ];
        });
    }

    /** @return array{message:string,revoked_sessions:int} */
    private function block(User $operator, User $target, string $reason): array
    {
        if ($target->status === UserStatus::BLOCKED) {
            return ['message' => 'A conta já está bloqueada.', 'revoked_sessions' => 0];
        }
        if ($target->status !== UserStatus::ACTIVE) {
            throw new InvalidArgumentException('Somente contas ativas podem ser bloqueadas por esta ação.');
        }

        $this->policy->assertCanBlock($operator, $target);
        $before = $this->snapshot($target);

        return Database::transaction(function () use ($operator, $target, $before, $reason): array {
            $this->users->updateStatus($target->id, UserStatus::BLOCKED);
            $this->users->incrementAuthorizationVersion($target->id);
            $revoked = $this->sessions->revokeAllForUser($target->id);
            $updated = $this->requireUpdated($target->id);

            $this->audit->record(
                $operator->id,
                $target->id,
                'usuario_bloqueado',
                'governanca',
                $reason,
                $before,
                $this->snapshot($updated)
            );

            return ['message' => 'Usuário bloqueado com sucesso.', 'revoked_sessions' => $revoked];
        });
    }

    /** @return array{message:string,revoked_sessions:int} */
    private function unblock(User $operator, User $target, string $reason): array
    {
        $this->authorization->requirePermission($operator, 'usuarios.desbloquear');

        if (!$this->authorization->canManageUser($operator, $target, 'usuarios.desbloquear')) {
            throw new AuthorizationException('Usuário não autorizado a desbloquear esta conta.');
        }
        if ($target->status !== UserStatus::BLOCKED) {
            throw new InvalidArgumentException('A conta selecionada não está bloqueada.');
        }
        if ($target->setorId === null || $target->nivelId === null) {
            throw new InvalidArgumentException('Defina setor e nível antes de desbloquear a conta.');
        }
        if ($this->sectors->findActiveById($target->setorId) === null || $this->levels->findActiveById($target->nivelId) === null) {
            throw new InvalidArgumentException('O setor ou nível atual da conta está indisponível.');
        }

        $before = $this->snapshot($target);

        return Database::transaction(function () use ($operator, $target, $before, $reason): array {
            $this->governance->clearLoginLock($target->id);
            $this->users->updateStatus($target->id, UserStatus::ACTIVE);
            $this->users->incrementAuthorizationVersion($target->id);
            $updated = $this->requireUpdated($target->id);

            $this->audit->record(
                $operator->id,
                $target->id,
                'usuario_desbloqueado',
                'governanca',
                $reason,
                $before,
                $this->snapshot($updated)
            );

            return ['message' => 'Usuário desbloqueado com sucesso.', 'revoked_sessions' => 0];
        });
    }

    /** @return array{message:string,revoked_sessions:int} */
    private function revokeSessions(User $operator, User $target, string $reason): array
    {
        $this->authorization->requirePermission($operator, 'usuarios.encerrar_sessao');

        if (!$this->authorization->canManageUser($operator, $target, 'usuarios.encerrar_sessao')) {
            throw new AuthorizationException('Usuário não autorizado a encerrar sessões desta conta.');
        }
        if ($operator->id === $target->id) {
            throw new AuthorizationException('Use a opção Sair para encerrar a própria sessão.');
        }

        return Database::transaction(function () use ($operator, $target, $reason): array {
            $revoked = $this->sessions->revokeAllForUser($target->id);
            $this->audit->record(
                $operator->id,
                $target->id,
                'sessoes_usuario_revogadas',
                'governanca',
                $reason,
                ['sessoes_revogadas' => 0],
                ['sessoes_revogadas' => $revoked]
            );

            return [
                'message' => $revoked > 0
                    ? $revoked . ' sessão(ões) encerrada(s).'
                    : 'Não havia sessões ativas para encerrar.',
                'revoked_sessions' => $revoked,
            ];
        });
    }

    private function normalizeReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Informe uma justificativa com pelo menos 5 caracteres.');
        }

        return mb_substr($reason, 0, 500);
    }

    /** @return array<string,mixed> */
    private function snapshot(User $user): array
    {
        return [
            'usuario_id' => $user->id,
            'nome' => $user->nome,
            'status' => $user->status->value,
            'setor_id' => $user->setorId,
            'nivel_id' => $user->nivelId,
            'versao_autorizacao' => $user->versaoAutorizacao,
        ];
    }

    private function requireUpdated(int $userId): User
    {
        $updated = $this->users->findById($userId);

        if (!$updated instanceof User) {
            throw new InvalidArgumentException('Não foi possível reler o usuário atualizado.');
        }

        return $updated;
    }
}
