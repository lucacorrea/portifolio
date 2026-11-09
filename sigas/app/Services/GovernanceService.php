<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GovernanceRepository;
use DateTimeImmutable;
use Throwable;

final class GovernanceService
{
    private const MODULE_LABELS = [
        'dashboard' => 'Painel geral',
        'usuarios' => 'Usuários',
        'auditoria' => 'Auditoria',
        'perfil' => 'Perfil próprio',
        'prontuarios' => 'Prontuários',
        'atendimentos' => 'Atendimentos',
        'relatorios' => 'Relatórios',
        'configuracoes' => 'Configurações',
        'arquivos' => 'Arquivos',
        'governanca' => 'Governança e Acessos',
        'kit_maternidade' => 'Kit Maternidade',
        'aluguel_social' => 'Aluguel Social',
        'beneficios_eventuais' => 'Benefícios Eventuais',
        'comida_mesa' => 'Coari Comida na Mesa',
        'primeiro_emprego' => 'Coari Meu Primeiro Emprego',
    ];

    public function __construct(private readonly GovernanceRepository $repository)
    {
    }

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        $summary = $this->repository->summary();
        $levels = $this->levels();
        $users = array_slice($this->users(), 0, 8);

        return [
            'summary' => $summary,
            'stats' => [
                [
                    'label' => 'Usuários ativos',
                    'value' => (string) $summary['usuarios_ativos'],
                    'detail' => $summary['usuarios_pendentes'] . ' pendente(s)',
                    'icon' => 'people',
                ],
                [
                    'label' => 'Níveis ativos',
                    'value' => (string) $summary['niveis_ativos'],
                    'detail' => 'Perfis de autorização',
                    'icon' => 'person-gear',
                ],
                [
                    'label' => 'Permissões',
                    'value' => (string) $summary['permissoes_ativas'],
                    'detail' => 'Ações controladas',
                    'icon' => 'key',
                ],
                [
                    'label' => 'Sessões ativas',
                    'value' => (string) $summary['sessoes_ativas'],
                    'detail' => $summary['setores_ativos'] . ' setor(es) ativo(s)',
                    'icon' => 'activity',
                ],
            ],
            'recent_users' => $users,
            'levels' => $levels,
        ];
    }

    /**
     * @return array{
     *   rows:list<array<string,string>>,
     *   filters:list<array{label:string,options:list<string>}>,
     *   stats:list<array<string,string>>
     * }
     */
    public function usersPage(): array
    {
        $summary = $this->repository->summary();
        $rows = $this->users();
        $sectors = [];
        $levels = [];

        foreach ($rows as $row) {
            $sector = trim((string) ($row['setor'] ?? ''));
            $level = trim((string) ($row['nivel'] ?? ''));

            if ($sector !== '' && $sector !== 'Sem setor') {
                $sectors[$sector] = true;
            }

            if ($level !== '' && $level !== 'Sem nível') {
                $levels[$level] = true;
            }
        }

        $sectorOptions = array_keys($sectors);
        $levelOptions = array_keys($levels);
        sort($sectorOptions, SORT_NATURAL | SORT_FLAG_CASE);
        sort($levelOptions, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'rows' => $rows,
            'filters' => [
                ['label' => 'Setor', 'options' => $sectorOptions],
                ['label' => 'Nível', 'options' => $levelOptions],
                ['label' => 'Situação', 'options' => ['Ativo', 'Pendente', 'Bloqueado', 'Inativo']],
            ],
            'stats' => [
                [
                    'label' => 'Total de contas',
                    'value' => (string) $summary['usuarios_total'],
                    'detail' => 'Sem excluídos',
                    'icon' => 'people',
                ],
                [
                    'label' => 'Ativos',
                    'value' => (string) $summary['usuarios_ativos'],
                    'detail' => 'Acesso operacional',
                    'icon' => 'person-check',
                ],
                [
                    'label' => 'Pendentes',
                    'value' => (string) $summary['usuarios_pendentes'],
                    'detail' => 'Aguardando aprovação',
                    'icon' => 'hourglass-split',
                ],
                [
                    'label' => 'Bloqueados',
                    'value' => (string) $summary['usuarios_bloqueados'],
                    'detail' => 'Sem acesso',
                    'icon' => 'person-lock',
                ],
            ],
        ];
    }

    /** @return array{rows:list<array<string,string>>,stats:list<array<string,string>>} */
    public function levelsPage(): array
    {
        $levels = $this->levels();
        $summary = $this->repository->summary();
        $activeUsers = 0;

        foreach ($levels as $level) {
            $activeUsers += (int) ($level['_usuarios'] ?? 0);
        }

        return [
            'rows' => array_map(
                static function (array $level): array {
                    unset($level['_usuarios']);
                    return $level;
                },
                $levels
            ),
            'stats' => [
                [
                    'label' => 'Níveis ativos',
                    'value' => (string) $summary['niveis_ativos'],
                    'detail' => 'Hierarquia configurada',
                    'icon' => 'person-gear',
                ],
                [
                    'label' => 'Usuários vinculados',
                    'value' => (string) $activeUsers,
                    'detail' => 'Contas com nível',
                    'icon' => 'people',
                ],
                [
                    'label' => 'Permissões ativas',
                    'value' => (string) $summary['permissoes_ativas'],
                    'detail' => 'Catálogo disponível',
                    'icon' => 'key',
                ],
            ],
        ];
    }

    /**
     * @return array{
     *   rows:list<array<string,string>>,
     *   filters:list<array{label:string,options:list<string>}>,
     *   stats:list<array<string,string>>
     * }
     */
    public function permissionsPage(): array
    {
        $permissions = $this->repository->permissions();
        $rows = [];
        $modules = [];
        $active = 0;

        foreach ($permissions as $permission) {
            $module = trim((string) ($permission['modulo'] ?? ''));
            $moduleLabel = $this->moduleLabel($module);
            $isActive = (int) ($permission['ativo'] ?? 0) === 1;

            if ($moduleLabel !== '') {
                $modules[$moduleLabel] = true;
            }

            if ($isActive) {
                $active++;
            }

            $rows[] = [
                'permissao' => trim((string) ($permission['nome'] ?? 'Permissão')),
                'slug' => trim((string) ($permission['slug'] ?? '—')),
                'modulo' => $moduleLabel,
                'niveis' => trim((string) ($permission['niveis'] ?? '')) ?: 'Nenhum nível',
                'situacao' => $isActive ? 'Ativa' : 'Inativa',
            ];
        }

        $moduleOptions = array_keys($modules);
        sort($moduleOptions, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'rows' => $rows,
            'filters' => [
                ['label' => 'Módulo', 'options' => $moduleOptions],
                ['label' => 'Situação', 'options' => ['Ativa', 'Inativa']],
            ],
            'stats' => [
                [
                    'label' => 'Permissões cadastradas',
                    'value' => (string) count($permissions),
                    'detail' => 'Catálogo total',
                    'icon' => 'key',
                ],
                [
                    'label' => 'Permissões ativas',
                    'value' => (string) $active,
                    'detail' => 'Disponíveis para níveis',
                    'icon' => 'shield-check',
                ],
                [
                    'label' => 'Módulos controlados',
                    'value' => (string) count($moduleOptions),
                    'detail' => 'Grupos de autorização',
                    'icon' => 'grid',
                ],
            ],
        ];
    }

    /**
     * @return array{
     *   columns:list<array{key:string,label:string}>,
     *   rows:list<array<string,string>>,
     *   stats:list<array<string,string>>
     * }
     */
    public function accessMatrixPage(): array
    {
        $levels = $this->repository->levels();
        $permissions = $this->repository->permissions();
        $grants = $this->repository->levelPermissions();

        $moduleKeys = [];
        foreach ($permissions as $permission) {
            $module = trim((string) ($permission['modulo'] ?? ''));
            if ($module !== '') {
                $moduleKeys[$module] = true;
            }
        }

        $moduleKeys = array_keys($moduleKeys);
        usort($moduleKeys, fn (string $a, string $b): int => strcasecmp($this->moduleLabel($a), $this->moduleLabel($b)));

        $grantCount = [];
        foreach ($grants as $grant) {
            $levelId = (int) ($grant['nivel_id'] ?? 0);
            $module = trim((string) ($grant['modulo'] ?? ''));

            if ($levelId <= 0 || $module === '') {
                continue;
            }

            $grantCount[$levelId][$module] = ($grantCount[$levelId][$module] ?? 0) + 1;
        }

        $columns = [
            ['key' => 'nivel', 'label' => 'Nível'],
            ['key' => 'escopo', 'label' => 'Escopo'],
        ];

        foreach ($moduleKeys as $module) {
            $columns[] = [
                'key' => $this->moduleColumnKey($module),
                'label' => $this->moduleLabel($module),
            ];
        }

        $rows = [];
        foreach ($levels as $level) {
            if ((int) ($level['ativo'] ?? 0) !== 1) {
                continue;
            }

            $levelId = (int) ($level['id'] ?? 0);
            $levelSlug = trim((string) ($level['slug'] ?? ''));
            $row = [
                'nivel' => trim((string) ($level['nome'] ?? 'Nível')),
                'escopo' => in_array($levelSlug, ['administrador', 'suporte'], true) ? 'Global' : 'Setor',
            ];

            foreach ($moduleKeys as $module) {
                $count = (int) ($grantCount[$levelId][$module] ?? 0);
                $row[$this->moduleColumnKey($module)] = $count > 0
                    ? 'Liberado · ' . $count
                    : 'Sem acesso';
            }

            $rows[] = $row;
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'stats' => [
                [
                    'label' => 'Níveis avaliados',
                    'value' => (string) count($rows),
                    'detail' => 'Somente níveis ativos',
                    'icon' => 'person-gear',
                ],
                [
                    'label' => 'Áreas controladas',
                    'value' => (string) count($moduleKeys),
                    'detail' => 'Grupos de permissões',
                    'icon' => 'grid-3x3-gap',
                ],
                [
                    'label' => 'Regra',
                    'value' => 'Nível + setor',
                    'detail' => 'Com exceção individual auditável',
                    'icon' => 'shield-lock',
                ],
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function users(): array
    {
        $rows = [];

        foreach ($this->repository->users() as $user) {
            $rows[] = [
                'usuario' => trim((string) ($user['nome'] ?? 'Usuário')),
                'cpf' => $this->maskCpf((string) ($user['cpf'] ?? '')),
                'cargo' => trim((string) ($user['cargo'] ?? '')) ?: 'Não informado',
                'setor' => trim((string) ($user['setor_nome'] ?? '')) ?: 'Sem setor',
                'nivel' => trim((string) ($user['nivel_nome'] ?? '')) ?: 'Sem nível',
                'ultimo' => $this->formatDateTime($user['ultimo_login_em'] ?? null),
                'situacao' => $this->statusLabel((string) ($user['status'] ?? '')),
                '_id' => (int) ($user['id'] ?? 0),
            ];
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function levels(): array
    {
        $moduleCounts = [];

        foreach ($this->repository->levelPermissions() as $grant) {
            $levelId = (int) ($grant['nivel_id'] ?? 0);
            $module = trim((string) ($grant['modulo'] ?? ''));

            if ($levelId > 0 && $module !== '') {
                $moduleCounts[$levelId][$module] = true;
            }
        }

        $rows = [];
        foreach ($this->repository->levels() as $level) {
            $levelId = (int) ($level['id'] ?? 0);
            $slug = trim((string) ($level['slug'] ?? ''));
            $users = (int) ($level['usuarios'] ?? 0);
            $permissionCount = (int) ($level['permissoes'] ?? 0);
            $moduleCount = isset($moduleCounts[$levelId]) ? count($moduleCounts[$levelId]) : 0;

            $rows[] = [
                'nivel' => trim((string) ($level['nome'] ?? 'Nível')),
                'prioridade' => (string) ((int) ($level['prioridade'] ?? 0)),
                'usuarios' => (string) $users,
                'permissoes' => $permissionCount . ' permissão(ões)',
                'modulos' => $moduleCount . ' área(s)',
                'escopo' => in_array($slug, ['administrador', 'suporte'], true) ? 'Global' : 'Setor',
                'situacao' => (int) ($level['ativo'] ?? 0) === 1 ? 'Ativo' : 'Inativo',
                '_usuarios' => $users,
            ];
        }

        return $rows;
    }

    private function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';

        if (strlen($digits) !== 11) {
            return '—';
        }

        return substr($digits, 0, 3) . '.***.***-' . substr($digits, -2);
    }

    private function formatDateTime(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return 'Nunca';
        }

        try {
            return (new DateTimeImmutable($raw))->format('d/m/Y H:i');
        } catch (Throwable) {
            return 'Não informado';
        }
    }

    private function statusLabel(string $status): string
    {
        return match (mb_strtolower(trim($status))) {
            'ativo' => 'Ativo',
            'pendente' => 'Pendente',
            'bloqueado' => 'Bloqueado',
            'inativo' => 'Inativo',
            'rejeitado' => 'Rejeitado',
            default => $status !== '' ? ucfirst($status) : 'Não definido',
        };
    }

    private function moduleLabel(string $module): string
    {
        if ($module === '') {
            return 'Sem módulo';
        }

        if (isset(self::MODULE_LABELS[$module])) {
            return self::MODULE_LABELS[$module];
        }

        $label = str_replace(['_', '-'], ' ', $module);
        return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
    }

    private function moduleColumnKey(string $module): string
    {
        return 'modulo_' . preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($module));
    }
}
