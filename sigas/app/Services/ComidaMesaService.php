<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\DTO\ComidaMesaFilter;
use App\Repositories\ComidaMesaRepository;

final class ComidaMesaService
{
    /** @var array<int,string> */
    private const MONTHS = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    public function __construct(private readonly ComidaMesaRepository $repository)
    {
    }

    /** @param array<string,mixed> $query */
    public function buildFilter(array $query): ComidaMesaFilter
    {
        return new ComidaMesaFilter(
            $this->stringValue($query['search'] ?? null),
            $this->intValue($query['competencia_id'] ?? null),
            $this->stringValue($query['program_status'] ?? null),
            $this->stringValue($query['delivery_status'] ?? null),
            $this->stringValue($query['zone'] ?? null),
            $this->stringValue($query['district'] ?? null),
            $this->stringValue($query['community'] ?? null),
            $this->intValue($query['pole_id'] ?? null),
            $this->intValue($query['page'] ?? null) ?? 1,
        );
    }

    /** @return array<string,mixed>|null */
    public function resolveCompetence(?int $competenceId): ?array
    {
        if ($competenceId !== null) {
            return $this->repository->findCompetenceById($competenceId);
        }

        return $this->repository->findDefaultCompetence();
    }

    /** @param array<string,mixed> $query */
    public function getDashboardData(array $query): array
    {
        $requestedFilter = $this->buildFilter($query);
        $competence = $this->resolveCompetence($requestedFilter->competenceId);
        $filter = new ComidaMesaFilter(
            $requestedFilter->search,
            $competence === null ? null : (int) $competence['id'],
            $requestedFilter->programStatus,
            $requestedFilter->deliveryStatus,
            $requestedFilter->zone,
            $requestedFilter->district,
            $requestedFilter->community,
            $requestedFilter->poleId,
            $requestedFilter->page,
        );

        return [
            'filter' => $filter,
            'competence' => $competence,
            'competences' => $this->repository->listCompetences(),
            'poles' => $this->repository->listActivePoles(),
            'statistics' => $this->repository->getStatistics($filter->competenceId),
            'registrations' => $this->repository->paginate($filter),
        ];
    }

    /** @return array<string,mixed> */
    public function consultCpf(string $cpf, ?int $competenceId): array
    {
        $digits = Validator::onlyDigits($cpf);
        $competence = $this->resolveCompetence($competenceId);
        $row = $this->repository->findByCpf($digits, $competence === null ? null : (int) $competence['id']);

        if ($row === null) {
            return [
                'ok' => true,
                'state' => 'nao_localizado',
                'person' => null,
            ];
        }

        $person = [
            'id' => (int) $row['pessoa_id'],
            'name' => (string) $row['responsavel_nome'],
            'cpf_masked' => $this->maskCpf((string) $row['cpf']),
            'nis' => $row['nis'] === null ? null : (string) $row['nis'],
        ];

        $family = $row['familia_id'] === null ? null : [
            'id' => (int) $row['familia_id'],
            'code' => (string) $row['familia_codigo'],
        ];

        if ($row['inscricao_id'] === null) {
            return [
                'ok' => true,
                'state' => 'pessoa_sem_inscricao',
                'person' => $person,
                'family' => $family,
            ];
        }

        $delivery = $this->deliveryStatusForRow($row, $competence);

        return [
            'ok' => true,
            'state' => 'inscrito',
            'person' => $person,
            'family' => $family,
            'registration' => [
                'id' => (int) $row['inscricao_id'],
                'status' => (string) $row['inscricao_status'],
                'status_label' => $this->programStatusLabel((string) $row['inscricao_status']),
                'pole' => $row['polo_nome'] === null ? null : (string) $row['polo_nome'],
            ],
            'competence' => $competence === null ? null : [
                'id' => (int) $competence['id'],
                'label' => $this->formatCompetence((int) $competence['mes'], (int) $competence['ano']),
            ],
            'delivery' => [
                'status' => $delivery['status'],
                'status_label' => $delivery['label'],
                'delivered_at' => $delivery['delivered_at'],
            ],
        ];
    }

    public function maskCpf(string $cpf): string
    {
        $digits = Validator::onlyDigits($cpf);

        if (strlen($digits) !== 11) {
            return '***.***.***-**';
        }

        return '***.***.***-**';
    }

    public function formatCompetence(int $month, int $year): string
    {
        return (self::MONTHS[$month] ?? 'Mês inválido') . ' de ' . $year;
    }

    public function programStatusLabel(string $status): string
    {
        return match ($status) {
            'ativa' => 'Beneficiária ativa',
            'em_analise' => 'Em análise',
            'lista_espera' => 'Lista de espera',
            'suspensa' => 'Suspensa',
            'bloqueada' => 'Bloqueada',
            'encerrada' => 'Encerrada',
            default => 'Não informado',
        };
    }

    /** @param array<string,mixed> $row @param array<string,mixed>|null $competence @return array<string,mixed> */
    public function deliveryStatusForRow(array $row, ?array $competence): array
    {
        if ($competence === null) {
            return [
                'status' => 'sem_competencia',
                'label' => 'Sem competência',
                'class' => 'status-neutral',
                'icon' => 'dash-circle',
                'delivered_at' => null,
            ];
        }

        if (!empty($row['entrega_id']) && ($row['entrega_status'] ?? null) === 'entregue') {
            return [
                'status' => 'recebida',
                'label' => 'Recebida',
                'class' => 'status-success',
                'icon' => 'bag-check',
                'delivered_at' => $row['entrega_data'] ?? null,
            ];
        }

        $registrationStatus = (string) ($row['inscricao_status'] ?? '');

        if ($registrationStatus === 'ativa') {
            return [
                'status' => 'aguardando',
                'label' => 'Aguardando retirada',
                'class' => 'status-warning',
                'icon' => 'clock',
                'delivered_at' => null,
            ];
        }

        if (in_array($registrationStatus, ['suspensa', 'bloqueada'], true)) {
            return [
                'status' => 'bloqueada',
                'label' => 'Bloqueada',
                'class' => 'status-danger',
                'icon' => 'slash-circle',
                'delivered_at' => null,
            ];
        }

        return [
            'status' => 'indisponivel',
            'label' => 'Não disponível',
            'class' => 'status-neutral',
            'icon' => 'dash-circle',
            'delivered_at' => null,
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
