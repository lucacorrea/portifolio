<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Repositories\GovernanceAccessLevelRepository;
use InvalidArgumentException;

final class GovernanceAccessLevelService
{
    private const PROTECTED_LEVELS = ['administrador', 'suporte'];

    public function __construct(
        private readonly GovernanceAccessLevelRepository $repository,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function page(): array
    {
        $rows = [];
        $active = 0;
        $inactive = 0;
        $users = 0;

        foreach ($this->repository->all() as $level) {
            $isActive = (int) ($level['ativo'] ?? 0) === 1;
            $slug = (string) ($level['slug'] ?? '');
            $userCount = (int) ($level['usuarios'] ?? 0);
            $permissionCount = (int) ($level['permissoes'] ?? 0);
            $users += $userCount;
            $isActive ? $active++ : $inactive++;

            $rows[] = [
                'id' => (int) ($level['id'] ?? 0),
                'name' => (string) ($level['nome'] ?? 'Nível'),
                'slug' => $slug,
                'description' => trim((string) ($level['descricao'] ?? '')),
                'priority' => (int) ($level['prioridade'] ?? 0),
                'users' => $userCount,
                'permissions' => $permissionCount,
                'active' => $isActive,
                'protected' => in_array($slug, self::PROTECTED_LEVELS, true),
            ];
        }

        return [
            'rows' => $rows,
            'stats' => [
                ['label' => 'Níveis cadastrados', 'value' => (string) count($rows), 'detail' => 'Catálogo de autorização', 'icon' => 'person-gear'],
                ['label' => 'Ativos', 'value' => (string) $active, 'detail' => 'Disponíveis para atribuição', 'icon' => 'check-circle'],
                ['label' => 'Inativos', 'value' => (string) $inactive, 'detail' => 'Preservados sem novas atribuições', 'icon' => 'pause-circle'],
                ['label' => 'Usuários vinculados', 'value' => (string) $users, 'detail' => 'Contas com nível definido', 'icon' => 'people'],
            ],
        ];
    }

    /** @return array{message:string,level_id:int,slug:string} */
    public function create(User $operator, string $name, ?string $description, int $priority, string $reason): array
    {
        $this->assertCanManage($operator);
        [$name, $description, $priority, $reason] = $this->normalize($name, $description, $priority, $reason);
        $slug = $this->uniqueSlug($name);

        if (in_array($slug, self::PROTECTED_LEVELS, true)) {
            throw new InvalidArgumentException('Este nome gera um identificador reservado. Escolha outro nome para o novo nível.');
        }

        $id = Database::transaction(function () use ($operator, $name, $slug, $description, $priority, $reason): int {
            $id = $this->repository->create($name, $slug, $description, $priority);
            $this->audit->record(
                $operator->id,
                null,
                'nivel_acesso_criado',
                'governanca',
                $reason,
                null,
                [
                    'nivel_id' => $id,
                    'nome' => $name,
                    'slug' => $slug,
                    'descricao' => $description,
                    'prioridade' => $priority,
                    'ativo' => true,
                ]
            );
            return $id;
        });

        return [
            'message' => 'Nível de acesso criado com sucesso.',
            'level_id' => $id,
            'slug' => $slug,
        ];
    }

    /** @return array{message:string,affected_users:int,revoked_sessions:int} */
    public function update(User $operator, int $levelId, string $name, ?string $description, int $priority, string $reason): array
    {
        $this->assertCanManage($operator);
        $current = $this->requireEditableLevel($levelId);
        [$name, $description, $priority, $reason] = $this->normalize($name, $description, $priority, $reason);

        $before = [
            'nivel_id' => $levelId,
            'nome' => (string) $current['nome'],
            'slug' => (string) $current['slug'],
            'descricao' => $current['descricao'],
            'prioridade' => (int) $current['prioridade'],
            'ativo' => (int) $current['ativo'] === 1,
        ];
        $after = [
            'nivel_id' => $levelId,
            'nome' => $name,
            'slug' => (string) $current['slug'],
            'descricao' => $description,
            'prioridade' => $priority,
            'ativo' => (int) $current['ativo'] === 1,
        ];

        if ($before === $after) {
            return ['message' => 'Nenhuma alteração foi necessária.', 'affected_users' => 0, 'revoked_sessions' => 0];
        }

        Database::transaction(function () use ($operator, $levelId, $name, $description, $priority, $reason, $before, $after): void {
            $this->repository->update($levelId, $name, $description, $priority);
            $this->audit->record(
                $operator->id,
                null,
                'nivel_acesso_atualizado',
                'governanca',
                $reason,
                $before,
                $after
            );
        });

        return ['message' => 'Nível de acesso atualizado com sucesso.', 'affected_users' => 0, 'revoked_sessions' => 0];
    }

    /** @return array{message:string,affected_users:int,revoked_sessions:int} */
    public function setActive(User $operator, int $levelId, bool $active, string $reason): array
    {
        $this->assertCanManage($operator);
        $current = $this->requireEditableLevel($levelId);
        $reason = $this->normalizeReason($reason);
        $currentActive = (int) ($current['ativo'] ?? 0) === 1;

        if ($currentActive === $active) {
            return [
                'message' => $active ? 'O nível já está ativo.' : 'O nível já está inativo.',
                'affected_users' => 0,
                'revoked_sessions' => 0,
            ];
        }

        $result = Database::transaction(function () use ($operator, $levelId, $active, $reason, $current): array {
            $this->repository->setActive($levelId, $active);
            $affectedUsers = $this->repository->incrementAuthorizationVersionsForLevel($levelId);
            $revokedSessions = $this->repository->revokeActiveSessionsForLevel($levelId);

            $this->audit->record(
                $operator->id,
                null,
                $active ? 'nivel_acesso_ativado' : 'nivel_acesso_inativado',
                'governanca',
                $reason,
                [
                    'nivel_id' => $levelId,
                    'nome' => (string) $current['nome'],
                    'slug' => (string) $current['slug'],
                    'ativo' => !$active,
                ],
                [
                    'nivel_id' => $levelId,
                    'nome' => (string) $current['nome'],
                    'slug' => (string) $current['slug'],
                    'ativo' => $active,
                ]
            );

            return [
                'affected_users' => $affectedUsers,
                'revoked_sessions' => $revokedSessions,
            ];
        });

        return [
            'message' => $active
                ? 'Nível ativado e disponível para novas atribuições.'
                : 'Nível inativado. Usuários vinculados precisarão receber outro nível para voltar a operar.',
            'affected_users' => (int) $result['affected_users'],
            'revoked_sessions' => (int) $result['revoked_sessions'],
        ];
    }

    private function assertCanManage(User $operator): void
    {
        if (!$this->authorization->isAdministrator($operator) && !$this->authorization->isSupport($operator)) {
            throw new AuthorizationException('A gestão de níveis é restrita à Governança e Acessos.');
        }
        $this->authorization->requirePermission($operator, 'governanca.perfis');
    }

    /** @return array<string,mixed> */
    private function requireEditableLevel(int $levelId): array
    {
        if ($levelId <= 0) {
            throw new InvalidArgumentException('Nível de acesso inválido.');
        }

        $level = $this->repository->findById($levelId);
        if ($level === null) {
            throw new InvalidArgumentException('Nível de acesso não localizado.');
        }

        if (in_array((string) ($level['slug'] ?? ''), self::PROTECTED_LEVELS, true)) {
            throw new AuthorizationException('Administrador e Suporte são níveis protegidos e não podem ser alterados nesta tela.');
        }

        return $level;
    }

    /** @return array{0:string,1:?string,2:int,3:string} */
    private function normalize(string $name, ?string $description, int $priority, string $reason): array
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Informe um nome de nível entre 2 e 120 caracteres.');
        }

        $description = $description === null ? null : trim($description);
        if ($description === '') {
            $description = null;
        }
        if ($description !== null && mb_strlen($description) > 255) {
            throw new InvalidArgumentException('A descrição deve ter no máximo 255 caracteres.');
        }

        if ($priority < 1 || $priority > 9999) {
            throw new InvalidArgumentException('A prioridade deve estar entre 1 e 9999.');
        }

        return [$name, $description, $priority, $this->normalizeReason($reason)];
    }

    private function normalizeReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Informe uma justificativa com pelo menos 5 caracteres.');
        }
        return mb_substr($reason, 0, 500);
    }

    private function uniqueSlug(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $base = strtolower((string) ($ascii !== false ? $ascii : $name));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'nivel-' . substr(hash('sha256', $name), 0, 8);
        }
        $base = substr($base, 0, 90);

        $slug = $base;
        $suffix = 2;
        while ($this->repository->slugExists($slug)) {
            $ending = '-' . $suffix++;
            $slug = substr($base, 0, 100 - strlen($ending)) . $ending;
        }
        return $slug;
    }
}
