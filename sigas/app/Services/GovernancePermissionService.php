<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Repositories\GovernancePermissionRepository;
use InvalidArgumentException;

final class GovernancePermissionService
{
    private const MODULES = [
        'kit_maternidade' => 'Kit Maternidade',
        'aluguel_social' => 'Aluguel Social',
        'beneficios_eventuais' => 'Benefícios Eventuais',
        'governanca' => 'Governança e Acessos',
        'comida_mesa' => 'Coari Comida na Mesa',
        'primeiro_emprego' => 'Coari Meu Primeiro Emprego',
    ];

    private const PROTECTED_SLUGS = [
        'governanca.visualizar',
        'governanca.setores',
        'governanca.cargos',
        'governanca.perfis',
        'governanca.permissoes',
        'governanca.modulos',
    ];

    public function __construct(
        private readonly GovernancePermissionRepository $repository,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function page(): array
    {
        $operational = [];
        $legacy = [];
        $active = 0;

        foreach ($this->repository->all() as $permission) {
            $module = (string) ($permission['modulo'] ?? '');
            $isOperational = isset(self::MODULES[$module]);
            $row = [
                'id' => (int) ($permission['id'] ?? 0),
                'name' => (string) ($permission['nome'] ?? 'Permissão'),
                'slug' => (string) ($permission['slug'] ?? ''),
                'description' => trim((string) ($permission['descricao'] ?? '')),
                'module' => $module,
                'module_label' => self::MODULES[$module] ?? ($module !== '' ? $module : 'Interna'),
                'active' => (int) ($permission['ativo'] ?? 0) === 1,
                'levels' => trim((string) ($permission['niveis'] ?? '')),
                'levels_count' => (int) ($permission['niveis_count'] ?? 0),
                'protected' => in_array((string) ($permission['slug'] ?? ''), self::PROTECTED_SLUGS, true),
                'operational' => $isOperational,
            ];

            if ($row['active']) {
                $active++;
            }

            if ($isOperational) {
                $operational[] = $row;
            } else {
                $legacy[] = $row;
            }
        }

        usort($operational, static function (array $a, array $b): int {
            $moduleOrder = array_flip(array_keys(self::MODULES));
            $cmp = ($moduleOrder[$a['module']] ?? PHP_INT_MAX) <=> ($moduleOrder[$b['module']] ?? PHP_INT_MAX);
            return $cmp !== 0 ? $cmp : strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'modules' => self::MODULES,
            'operational' => $operational,
            'legacy' => $legacy,
            'stats' => [
                ['label' => 'Permissões operacionais', 'value' => (string) count($operational), 'detail' => 'Gerenciáveis pela Governança', 'icon' => 'key'],
                ['label' => 'Ativas', 'value' => (string) count(array_filter($operational, static fn (array $row): bool => $row['active'])), 'detail' => 'Disponíveis na matriz', 'icon' => 'shield-check'],
                ['label' => 'Inativas', 'value' => (string) count(array_filter($operational, static fn (array $row): bool => !$row['active'])), 'detail' => 'Preservadas sem efeito', 'icon' => 'pause-circle'],
                ['label' => 'Internas/legadas', 'value' => (string) count($legacy), 'detail' => 'Somente leitura', 'icon' => 'archive'],
            ],
        ];
    }

    /** @return array{message:string,permission_id:int,slug:string} */
    public function create(
        User $operator,
        string $name,
        string $module,
        string $actionKey,
        ?string $description,
        string $reason,
    ): array {
        $this->assertCanManage($operator);
        [$name, $description, $reason] = $this->normalizeMetadata($name, $description, $reason);
        $module = $this->normalizeModule($module);
        $actionKey = strtolower(trim($actionKey));

        if (preg_match('/^[a-z][a-z0-9_]{1,59}$/', $actionKey) !== 1) {
            throw new InvalidArgumentException('Use um identificador de ação com letras minúsculas, números e underline, começando por letra.');
        }

        $slug = $module . '.' . $actionKey;
        if (strlen($slug) > 120) {
            throw new InvalidArgumentException('O identificador completo da permissão ficou muito longo.');
        }
        if ($this->repository->slugExists($slug)) {
            throw new InvalidArgumentException('Já existe uma permissão com este identificador.');
        }

        $permissionId = Database::transaction(function () use ($operator, $name, $slug, $description, $module, $reason): int {
            $permissionId = $this->repository->create($name, $slug, $description, $module);
            $this->audit->record(
                $operator->id,
                null,
                'permissao_criada',
                'governanca',
                $reason,
                null,
                [
                    'permissao_id' => $permissionId,
                    'nome' => $name,
                    'slug' => $slug,
                    'modulo' => $module,
                    'descricao' => $description,
                    'ativo' => true,
                ]
            );
            return $permissionId;
        });

        return [
            'message' => 'Permissão criada com sucesso. Agora vincule-a aos níveis pela Matriz de Acesso.',
            'permission_id' => $permissionId,
            'slug' => $slug,
        ];
    }

    /** @return array{message:string} */
    public function update(User $operator, int $permissionId, string $name, ?string $description, string $reason): array
    {
        $this->assertCanManage($operator);
        $current = $this->requireOperationalPermission($permissionId);
        [$name, $description, $reason] = $this->normalizeMetadata($name, $description, $reason);

        $before = [
            'permissao_id' => $permissionId,
            'nome' => (string) $current['nome'],
            'slug' => (string) $current['slug'],
            'modulo' => (string) $current['modulo'],
            'descricao' => $current['descricao'],
            'ativo' => (int) $current['ativo'] === 1,
        ];
        $after = $before;
        $after['nome'] = $name;
        $after['descricao'] = $description;

        if ($before === $after) {
            return ['message' => 'Nenhuma alteração foi necessária.'];
        }

        Database::transaction(function () use ($operator, $permissionId, $name, $description, $reason, $before, $after): void {
            $this->repository->updateMetadata($permissionId, $name, $description);
            $this->audit->record(
                $operator->id,
                null,
                'permissao_atualizada',
                'governanca',
                $reason,
                $before,
                $after
            );
        });

        return ['message' => 'Permissão atualizada com sucesso. O slug e o módulo foram preservados.'];
    }

    /** @return array{message:string,affected_users:int,revoked_sessions:int} */
    public function setActive(User $operator, int $permissionId, bool $active, string $reason): array
    {
        $this->assertCanManage($operator);
        $current = $this->requireOperationalPermission($permissionId);
        $reason = $this->normalizeReason($reason);
        $slug = (string) ($current['slug'] ?? '');
        $currentActive = (int) ($current['ativo'] ?? 0) === 1;

        if (!$active && in_array($slug, self::PROTECTED_SLUGS, true)) {
            throw new AuthorizationException('Esta permissão é estrutural da Governança e não pode ser inativada pela interface.');
        }

        if ($currentActive === $active) {
            return [
                'message' => $active ? 'A permissão já está ativa.' : 'A permissão já está inativa.',
                'affected_users' => 0,
                'revoked_sessions' => 0,
            ];
        }

        $result = Database::transaction(function () use ($operator, $permissionId, $active, $reason, $current): array {
            $this->repository->setActive($permissionId, $active);
            $affectedUsers = $this->repository->incrementAuthorizationVersionsForPermission($permissionId);
            $revokedSessions = $this->repository->revokeActiveSessionsForPermission($permissionId);

            $this->audit->record(
                $operator->id,
                null,
                $active ? 'permissao_ativada' : 'permissao_inativada',
                'governanca',
                $reason,
                [
                    'permissao_id' => $permissionId,
                    'nome' => (string) $current['nome'],
                    'slug' => (string) $current['slug'],
                    'modulo' => (string) $current['modulo'],
                    'ativo' => !$active,
                ],
                [
                    'permissao_id' => $permissionId,
                    'nome' => (string) $current['nome'],
                    'slug' => (string) $current['slug'],
                    'modulo' => (string) $current['modulo'],
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
                ? 'Permissão ativada. Os vínculos existentes voltaram a produzir efeito.'
                : 'Permissão inativada. Os vínculos foram preservados, mas deixaram de produzir efeito.',
            'affected_users' => (int) $result['affected_users'],
            'revoked_sessions' => (int) $result['revoked_sessions'],
        ];
    }

    private function assertCanManage(User $operator): void
    {
        if (!$this->authorization->isAdministrator($operator) && !$this->authorization->isSupport($operator)) {
            throw new AuthorizationException('A gestão de permissões é restrita à Governança e Acessos.');
        }
        $this->authorization->requirePermission($operator, 'governanca.permissoes');
    }

    /** @return array<string,mixed> */
    private function requireOperationalPermission(int $permissionId): array
    {
        if ($permissionId <= 0) {
            throw new InvalidArgumentException('Permissão inválida.');
        }
        $permission = $this->repository->findById($permissionId);
        if ($permission === null) {
            throw new InvalidArgumentException('Permissão não localizada.');
        }
        if (!isset(self::MODULES[(string) ($permission['modulo'] ?? '')])) {
            throw new AuthorizationException('Permissões internas ou legadas são somente leitura nesta tela.');
        }
        return $permission;
    }

    /** @return array{0:string,1:?string,2:string} */
    private function normalizeMetadata(string $name, ?string $description, string $reason): array
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        if (mb_strlen($name) < 3 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Informe um nome de permissão entre 3 e 160 caracteres.');
        }

        $description = $description === null ? null : trim($description);
        if ($description === '') {
            $description = null;
        }
        if ($description !== null && mb_strlen($description) > 255) {
            throw new InvalidArgumentException('A descrição deve ter no máximo 255 caracteres.');
        }

        return [$name, $description, $this->normalizeReason($reason)];
    }

    private function normalizeModule(string $module): string
    {
        $module = trim($module);
        if (!isset(self::MODULES[$module])) {
            throw new InvalidArgumentException('Selecione um módulo operacional válido.');
        }
        return $module;
    }

    private function normalizeReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Informe uma justificativa com pelo menos 5 caracteres.');
        }
        return mb_substr($reason, 0, 500);
    }
}
