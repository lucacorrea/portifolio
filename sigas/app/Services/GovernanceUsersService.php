<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AccessModuleCatalog;
use App\Repositories\GovernanceUserOverrideRepository;
use App\Repositories\GovernanceUsersRepository;
use DateTimeImmutable;
use Throwable;

final class GovernanceUsersService
{
    public function __construct(
        private readonly GovernanceUsersRepository $repository,
        private readonly ?GovernanceUserOverrideRepository $overrides = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function page(): array
    {
        $summary = $this->repository->summary();
        $rows = [];
        $sectors = [];
        $levels = [];

        foreach ($this->repository->users() as $user) {
            $sector = trim((string) ($user['setor_nome'] ?? '')) ?: 'Sem setor';
            $level = trim((string) ($user['nivel_nome'] ?? '')) ?: 'Sem nível';
            $status = $this->statusLabel((string) ($user['status'] ?? ''));
            $userId = (int) ($user['id'] ?? 0);

            if ($sector !== 'Sem setor') {
                $sectors[$sector] = true;
            }
            if ($level !== 'Sem nível') {
                $levels[$level] = true;
            }

            $rows[] = [
                'usuario' => trim((string) ($user['nome'] ?? 'Usuário')),
                'cpf' => $this->maskCpf((string) ($user['cpf'] ?? '')),
                'cargo' => trim((string) ($user['cargo'] ?? '')) ?: 'Não informado',
                'setor' => $sector,
                'nivel' => $level,
                'ultimo_acesso' => $this->formatDateTime($user['ultimo_login_em'] ?? null, 'Nunca'),
                'situacao' => $status,
                'ID do usuário' => (string) $userId,
                'CPF completo' => $this->formatCpf((string) ($user['cpf'] ?? '')),
                'Matrícula' => trim((string) ($user['matricula'] ?? '')) ?: 'Não informada',
                'E-mail' => trim((string) ($user['email'] ?? '')) ?: 'Não informado',
                'Telefone' => trim((string) ($user['telefone'] ?? '')) ?: 'Não informado',
                'Setor solicitado' => trim((string) ($user['setor_solicitado_nome'] ?? '')) ?: 'Não informado',
                'Último IP' => trim((string) ($user['ultimo_login_ip'] ?? '')) ?: 'Não registrado',
                'Bloqueado até' => $this->formatDateTime($user['bloqueado_ate'] ?? null, 'Não'),
                'Tentativas de login' => (string) ((int) ($user['tentativas_login'] ?? 0)),
                'Troca de senha obrigatória' => (int) ($user['precisa_trocar_senha'] ?? 0) === 1 ? 'Sim' : 'Não',
                'Sessões ativas' => (string) ((int) ($user['sessoes_ativas'] ?? 0)),
                'Versão de autorização' => (string) ((int) ($user['versao_autorizacao'] ?? 1)),
                'Aprovado por' => trim((string) ($user['aprovado_por_nome'] ?? '')) ?: 'Não informado',
                'Aprovado em' => $this->formatDateTime($user['aprovado_em'] ?? null, 'Não informado'),
                'Rejeitado por' => trim((string) ($user['rejeitado_por_nome'] ?? '')) ?: 'Não se aplica',
                'Rejeitado em' => $this->formatDateTime($user['rejeitado_em'] ?? null, 'Não se aplica'),
                'Motivo da rejeição' => trim((string) ($user['motivo_rejeicao'] ?? '')) ?: 'Não se aplica',
                'Observação interna' => trim((string) ($user['observacao_interna'] ?? '')) ?: 'Sem observações',
                'Criado em' => $this->formatDateTime($user['criado_em'] ?? null, 'Não informado'),
                'Atualizado em' => $this->formatDateTime($user['atualizado_em'] ?? null, 'Nunca atualizado'),
                '__user_id' => $userId,
                '__sector_id' => (int) ($user['setor_id'] ?? 0),
                '__requested_sector_id' => (int) ($user['setor_solicitado_id'] ?? 0),
                '__level_id' => (int) ($user['nivel_id'] ?? 0),
                '__level_slug' => trim((string) ($user['nivel_slug'] ?? '')),
                '__status' => trim((string) ($user['status'] ?? '')),
                '__active_sessions' => (int) ($user['sessoes_ativas'] ?? 0),
                '_actions' => [
                    [
                        'kind' => 'detail',
                        'label' => 'Ver dados completos',
                        'description' => 'Consultar os dados administrativos desta conta sem realizar alterações.',
                        'icon' => 'person-vcard',
                    ],
                    [
                        'kind' => 'navigate',
                        'label' => 'Gerenciar acesso',
                        'description' => 'Alterar setor, nível, módulos, permissões, status ou sessões com validação e auditoria.',
                        'icon' => 'shield-lock',
                        'variant' => 'primary',
                        'href' => 'governanca-acessos/usuarios.php?usuario={__user_id}',
                    ],
                    [
                        'kind' => 'navigate',
                        'label' => 'Ver auditoria',
                        'description' => 'Consultar os eventos administrativos relacionados a esta conta.',
                        'icon' => 'journal-text',
                        'href' => 'governanca-acessos/auditoria.php?usuario={__user_id}',
                    ],
                ],
            ];
        }

        $sectorOptions = array_keys($sectors);
        $levelOptions = array_keys($levels);
        sort($sectorOptions, SORT_NATURAL | SORT_FLAG_CASE);
        sort($levelOptions, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'rows' => $rows,
            'sectors' => $this->repository->activeSectors(),
            'levels' => $this->repository->activeLevels(),
            'filters' => [
                ['label' => 'Setor', 'options' => $sectorOptions],
                ['label' => 'Nível', 'options' => $levelOptions],
                ['label' => 'Situação', 'options' => ['Ativo', 'Pendente', 'Bloqueado', 'Inativo', 'Rejeitado']],
            ],
            'stats' => [
                ['label' => 'Total de contas', 'value' => (string) $summary['total'], 'detail' => 'Sem excluídos', 'icon' => 'people'],
                ['label' => 'Ativos', 'value' => (string) $summary['ativos'], 'detail' => 'Acesso operacional', 'icon' => 'person-check'],
                ['label' => 'Pendentes', 'value' => (string) $summary['pendentes'], 'detail' => 'Aguardando aprovação', 'icon' => 'hourglass-split'],
                ['label' => 'Bloqueados', 'value' => (string) $summary['bloqueados'], 'detail' => 'Sem acesso', 'icon' => 'person-lock'],
            ],
        ];
    }

    /**
     * Perfil efetivo mostrado na Governança.
     * A tela distingue claramente o que vem do nível/setor e o que é exceção individual.
     *
     * @return array{modules:list<array<string,mixed>>,protected:bool}
     */
    public function accessProfile(int $userId, ?int $levelId, ?int $sectorId, string $levelSlug = ''): array
    {
        if ($this->overrides === null) {
            return ['modules' => [], 'protected' => true];
        }

        $catalog = AccessModuleCatalog::operational();
        $permissionModuleIndex = AccessModuleCatalog::permissionModuleIndex();
        $permissions = $this->overrides->permissionCatalog(array_keys($permissionModuleIndex));
        $levelPermissions = array_fill_keys($this->overrides->levelPermissionSlugs($levelId), true);
        $moduleOverrides = $this->overrides->moduleOverrides($userId);
        $permissionOverrides = $this->overrides->permissionOverrides($userId);
        $sectorRules = $this->overrides->sectorModuleRules($sectorId);
        $sectorConfigured = $this->overrides->sectorHasConfiguration($sectorId);
        $grouped = [];

        foreach ($permissions as $permission) {
            $permissionModule = trim((string) ($permission['modulo'] ?? ''));
            $moduleKey = $permissionModuleIndex[$permissionModule] ?? null;
            if (!is_string($moduleKey)) {
                continue;
            }
            $grouped[$moduleKey][] = $permission;
        }

        $modules = [];
        foreach ($catalog as $moduleKey => $definition) {
            $baseModuleAllowed = !$sectorConfigured || (bool) ($sectorRules[$moduleKey] ?? false);
            $moduleOverride = array_key_exists($moduleKey, $moduleOverrides) ? $moduleOverrides[$moduleKey] : null;
            $effectiveModuleRule = $moduleOverride ?? $baseModuleAllowed;
            $viewPermission = $definition['view_permission'];
            $baseViewAllowed = isset($levelPermissions[$viewPermission]);
            $viewOverride = array_key_exists($viewPermission, $permissionOverrides) ? $permissionOverrides[$viewPermission] : null;
            $effectiveViewAllowed = $viewOverride ?? ($moduleOverride === true ? true : $baseViewAllowed);
            $permissionRows = [];

            foreach ($grouped[$moduleKey] ?? [] as $permission) {
                $slug = trim((string) ($permission['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }

                $baseAllowed = isset($levelPermissions[$slug]);
                $override = array_key_exists($slug, $permissionOverrides) ? $permissionOverrides[$slug] : null;
                $actionAllowed = $override ?? $baseAllowed;

                // Ao liberar individualmente o módulo, a permissão básica de visualização
                // é concedida automaticamente. Uma negativa explícita da própria ação
                // ainda prevalece sobre essa liberação.
                if ($slug === $viewPermission && $override === null && $moduleOverride === true) {
                    $actionAllowed = true;
                }

                $permissionRows[] = [
                    'slug' => $slug,
                    'label' => trim((string) ($permission['nome'] ?? $slug)),
                    'description' => trim((string) ($permission['descricao'] ?? '')),
                    'base_allowed' => $baseAllowed,
                    'override_state' => $this->stateLabel($override),
                    'effective_allowed' => $effectiveModuleRule && $actionAllowed,
                ];
            }

            $modules[] = [
                'key' => $moduleKey,
                'label' => $definition['label'],
                'base_module_allowed' => $baseModuleAllowed,
                'module_override_state' => $this->stateLabel($moduleOverride),
                'effective_access' => $effectiveModuleRule && $effectiveViewAllowed,
                'permissions' => $permissionRows,
            ];
        }

        return [
            'modules' => $modules,
            'protected' => in_array($levelSlug, ['administrador', 'suporte'], true),
        ];
    }

    private function stateLabel(?bool $state): string
    {
        return $state === null ? 'inherit' : ($state ? 'allow' : 'deny');
    }

    private function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        return strlen($digits) === 11
            ? substr($digits, 0, 3) . '.***.***-' . substr($digits, -2)
            : '—';
    }

    private function formatCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        if (strlen($digits) !== 11) {
            return 'Não informado';
        }
        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }

    private function formatDateTime(mixed $value, string $fallback): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return $fallback;
        }

        try {
            return (new DateTimeImmutable($raw))->format('d/m/Y H:i');
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function statusLabel(string $status): string
    {
        return match (mb_strtolower(trim($status))) {
            'ativo' => 'Ativo',
            'pendente' => 'Pendente',
            'bloqueado' => 'Bloqueado',
            'inativo', 'rejeitado' => 'Inativo',
            'rejeitado' => 'Rejeitado',
            default => $status !== '' ? ucfirst($status) : 'Não definido',
        };
    }
}
