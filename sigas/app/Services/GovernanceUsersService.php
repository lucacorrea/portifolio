<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GovernanceUsersRepository;
use DateTimeImmutable;
use Throwable;

final class GovernanceUsersService
{
    public function __construct(private readonly GovernanceUsersRepository $repository)
    {
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
                'ID do usuário' => (string) ((int) ($user['id'] ?? 0)),
                'CPF completo' => $this->formatCpf((string) ($user['cpf'] ?? '')),
                'Matrícula' => trim((string) ($user['matricula'] ?? '')) ?: 'Não informada',
                'E-mail' => trim((string) ($user['email'] ?? '')) ?: 'Não informado',
                'Telefone' => trim((string) ($user['telefone'] ?? '')) ?: 'Não informado',
                'Setor solicitado' => trim((string) ($user['setor_solicitado_nome'] ?? '')) ?: 'Não informado',
                'Último IP' => trim((string) ($user['ultimo_login_ip'] ?? '')) ?: 'Não registrado',
                'Bloqueado até' => $this->formatDateTime($user['bloqueado_ate'] ?? null, 'Não'),
                'Tentativas de login' => (string) ((int) ($user['tentativas_login'] ?? 0)),
                'Troca de senha obrigatória' => (int) ($user['precisa_trocar_senha'] ?? 0) === 1 ? 'Sim' : 'Não',
                'Versão de autorização' => (string) ((int) ($user['versao_autorizacao'] ?? 1)),
                'Aprovado por' => trim((string) ($user['aprovado_por_nome'] ?? '')) ?: 'Não informado',
                'Aprovado em' => $this->formatDateTime($user['aprovado_em'] ?? null, 'Não informado'),
                'Rejeitado por' => trim((string) ($user['rejeitado_por_nome'] ?? '')) ?: 'Não se aplica',
                'Rejeitado em' => $this->formatDateTime($user['rejeitado_em'] ?? null, 'Não se aplica'),
                'Motivo da rejeição' => trim((string) ($user['motivo_rejeicao'] ?? '')) ?: 'Não se aplica',
                'Observação interna' => trim((string) ($user['observacao_interna'] ?? '')) ?: 'Sem observações',
                'Criado em' => $this->formatDateTime($user['criado_em'] ?? null, 'Não informado'),
                'Atualizado em' => $this->formatDateTime($user['atualizado_em'] ?? null, 'Nunca atualizado'),
                '_actions' => [
                    [
                        'kind' => 'detail',
                        'label' => 'Ver dados completos',
                        'description' => 'Consultar os dados administrativos desta conta sem realizar alterações.',
                        'icon' => 'person-vcard',
                        'variant' => 'primary',
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
            'inativo' => 'Inativo',
            'rejeitado' => 'Rejeitado',
            default => $status !== '' ? ucfirst($status) : 'Não definido',
        };
    }
}
