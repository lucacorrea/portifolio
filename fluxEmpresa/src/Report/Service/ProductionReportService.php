<?php

declare(strict_types=1);

namespace App\Report\Service;

use App\Report\Repository\ProductionReportRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class ProductionReportService
{
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

    private const CLIENT_SORTS = [
        'faturamento' => 'revenue',
        'revenue' => 'revenue',
        'quantidade_os' => 'orders',
        'orders' => 'orders',
        'nome' => 'name',
        'name' => 'name',
        'ultima_os' => 'last_order',
        'last_order' => 'last_order',
        'saldo' => 'balance',
        'balance' => 'balance',
    ];

    private const SERVICE_SORTS = [
        'faturamento' => 'revenue',
        'revenue' => 'revenue',
        'quantidade' => 'quantity',
        'quantity' => 'quantity',
        'os' => 'orders',
        'orders' => 'orders',
        'clientes' => 'clients',
        'clients' => 'clients',
        'descricao' => 'description',
        'description' => 'description',
        'ultima_execucao' => 'last_execution',
        'last_execution' => 'last_execution',
    ];

    public function __construct(
        private readonly ProductionReportRepository $reports
    ) {
    }

    /**
     * Mantém o contrato legado utilizado pela página atual.
     *
     * @return array<string,mixed>
     */
    public function monthlyReport(?string $competencia): array
    {
        $teamReport = $this->teamReport(
            [
                'modo' => 'mes',
                'competencia' => $competencia,
            ],
            true
        );

        $period = is_array($teamReport['period'] ?? null)
            ? $teamReport['period']
            : [];

        return [
            'competencia' => (string) ($period['competence'] ?? ''),
            'period_label' => (string) ($period['label'] ?? ''),
            'goal' => is_array($teamReport['goal'] ?? null)
                ? $teamReport['goal']
                : [],
            'summary' => is_array($teamReport['summary'] ?? null)
                ? $teamReport['summary']
                : [],
            'employees' => is_array($teamReport['employees'] ?? null)
                ? $teamReport['employees']
                : [],
            'details' => is_array($teamReport['details'] ?? null)
                ? $teamReport['details']
                : [],
        ];
    }

    /**
     * Resolve e valida o período global do relatório.
     *
     * A data inicial é inclusiva e o fim interno é exclusivo.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function resolvePeriod(array $input): array
    {
        $requestedMode = strtolower(trim((string) ($input['modo'] ?? $input['mode'] ?? '')));
        $hasCustomDates = trim((string) ($input['data_inicial'] ?? $input['start_date'] ?? '')) !== ''
            || trim((string) ($input['data_final'] ?? $input['end_date'] ?? '')) !== '';

        if ($requestedMode === '') {
            $requestedMode = $hasCustomDates ? 'periodo' : 'mes';
        }

        if (in_array($requestedMode, ['mes', 'month'], true)) {
            return $this->monthPeriod(
                isset($input['competencia'])
                    ? (string) $input['competencia']
                    : null
            );
        }

        if (!in_array($requestedMode, ['periodo', 'custom', 'personalizado'], true)) {
            throw new InvalidArgumentException('Modo de período inválido.');
        }

        $startDate = $this->strictDate(
            (string) ($input['data_inicial'] ?? $input['start_date'] ?? ''),
            'Data inicial inválida.'
        );

        $endDate = $this->strictDate(
            (string) ($input['data_final'] ?? $input['end_date'] ?? ''),
            'Data final inválida.'
        );

        if ($startDate > $endDate) {
            throw new InvalidArgumentException(
                'A data inicial não pode ser maior que a data final.'
            );
        }

        $days = ((int) $startDate->diff($endDate)->format('%a')) + 1;

        if ($days > 366) {
            throw new InvalidArgumentException(
                'O período personalizado pode ter no máximo 366 dias.'
            );
        }

        $endExclusive = $endDate->modify('+1 day');

        return $this->periodPayload(
            'custom',
            null,
            $startDate,
            $endExclusive,
            $this->dateRangeLabel($startDate, $endDate),
            [
                'modo' => 'periodo',
                'data_inicial' => $startDate->format('Y-m-d'),
                'data_final' => $endDate->format('Y-m-d'),
            ]
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function companyReport(
        array $input,
        bool $canViewFinancial
    ): array {
        $period = $this->resolvePeriod($input);

        $currentRow = $this->reports->companySummary(
            (string) $period['start'],
            (string) $period['end_exclusive']
        );

        $previousRow = $this->reports->companyPreviousPeriodSummary(
            (string) $period['previous_start'],
            (string) $period['previous_end_exclusive']
        );

        $currentSummary = $this->normalizeCompanySummary(
            $currentRow,
            $canViewFinancial
        );

        $previousSummary = $this->normalizeCompanySummary(
            $previousRow,
            $canViewFinancial
        );

        $currentRevenueCents = self::databaseMoneyToCents(
            (string) ($currentRow['company_total'] ?? '0')
        );

        $previousRevenueCents = self::databaseMoneyToCents(
            (string) ($previousRow['company_total'] ?? '0')
        );

        $evolution = $this->dailyEvolution(
            $period,
            $this->reports->companyDailyEvolution(
                (string) $period['start'],
                (string) $period['end_exclusive']
            ),
            $canViewFinancial
        );

        $rankings = [
            'clients_by_revenue' => [],
            'services_by_revenue' => [],
            'services_by_quantity' => $this->normalizeServiceRanking(
                $this->reports->topServicesByQuantity(
                    (string) $period['start'],
                    (string) $period['end_exclusive'],
                    10
                ),
                false
            ),
        ];

        if ($canViewFinancial) {
            $rankings['clients_by_revenue'] = $this->normalizeClientRanking(
                $this->reports->topClientsByRevenue(
                    (string) $period['start'],
                    (string) $period['end_exclusive'],
                    10
                )
            );

            $rankings['services_by_revenue'] = $this->normalizeServiceRanking(
                $this->reports->topServicesByRevenue(
                    (string) $period['start'],
                    (string) $period['end_exclusive'],
                    10
                ),
                true
            );
        }

        return [
            'period' => $period,
            'can_view_financial' => $canViewFinancial,
            'summary' => $currentSummary,
            'previous_summary' => $previousSummary,
            'comparison' => [
                'orders' => self::comparison(
                    (int) ($currentRow['orders'] ?? 0),
                    (int) ($previousRow['orders'] ?? 0)
                ),
                'unique_clients' => self::comparison(
                    (int) ($currentRow['unique_clients'] ?? 0),
                    (int) ($previousRow['unique_clients'] ?? 0)
                ),
                'company_total' => $canViewFinancial
                    ? self::comparison(
                        $currentRevenueCents,
                        $previousRevenueCents
                    )
                    : null,
            ],
            'daily_evolution' => $evolution,
            'rankings' => $rankings,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function clientReport(
        array $input,
        bool $canViewFinancial
    ): array {
        $period = $this->resolvePeriod($input);
        $pagination = $this->pagination($input);
        $search = $this->searchTerm($input['busca'] ?? $input['search'] ?? '');
        $sort = $this->allowedSort(
            (string) (
                $input['ordenar']
                ?? $input['sort']
                ?? ($canViewFinancial ? 'faturamento' : 'quantidade_os')
            ),
            self::CLIENT_SORTS,
            $canViewFinancial ? 'revenue' : 'orders'
        );

        if (!$canViewFinancial && in_array($sort, ['revenue', 'balance'], true)) {
            $sort = 'orders';
        }
        $direction = $this->direction(
            (string) ($input['direcao'] ?? $input['direction'] ?? 'desc')
        );

        $total = $this->reports->countClientSummary(
            (string) $period['start'],
            (string) $period['end_exclusive'],
            $search
        );

        $pagination = $this->paginationWithTotal(
            $pagination,
            $total
        );

        $rows = $this->reports->clientSummary(
            (string) $period['start'],
            (string) $period['end_exclusive'],
            $search,
            $sort,
            $direction,
            (int) $pagination['per_page'],
            (int) $pagination['offset']
        );

        return [
            'period' => $period,
            'can_view_financial' => $canViewFinancial,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'pagination' => $pagination,
            'rows' => array_map(
                fn(array $row): array => $this->normalizeClientRow(
                    $row,
                    $canViewFinancial
                ),
                $rows
            ),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function clientOrderDetailsReport(
        array $input,
        bool $canViewFinancial
    ): array {
        $period = $this->resolvePeriod($input);
        $pagination = $this->pagination($input);
        $clientId = $this->positiveInt(
            $input['client_id'] ?? $input['cliente_id'] ?? null,
            'Cliente inválido.'
        );

        $total = $this->reports->countClientOrderDetails(
            $clientId,
            (string) $period['start'],
            (string) $period['end_exclusive']
        );

        $pagination = $this->paginationWithTotal(
            $pagination,
            $total
        );

        $rows = $this->reports->clientOrderDetails(
            $clientId,
            (string) $period['start'],
            (string) $period['end_exclusive'],
            (int) $pagination['per_page'],
            (int) $pagination['offset']
        );

        return [
            'period' => $period,
            'client_id' => $clientId,
            'can_view_financial' => $canViewFinancial,
            'pagination' => $pagination,
            'rows' => array_map(
                fn(array $row): array => $this->normalizeClientOrderDetail(
                    $row,
                    $canViewFinancial
                ),
                $rows
            ),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function serviceReport(
        array $input,
        bool $canViewFinancial
    ): array {
        $period = $this->resolvePeriod($input);
        $pagination = $this->pagination($input);
        $search = $this->searchTerm($input['busca'] ?? $input['search'] ?? '');
        $sort = $this->allowedSort(
            (string) (
                $input['ordenar']
                ?? $input['sort']
                ?? ($canViewFinancial ? 'faturamento' : 'quantidade')
            ),
            self::SERVICE_SORTS,
            $canViewFinancial ? 'revenue' : 'quantity'
        );

        if (!$canViewFinancial && $sort === 'revenue') {
            $sort = 'quantity';
        }
        $direction = $this->direction(
            (string) ($input['direcao'] ?? $input['direction'] ?? 'desc')
        );

        $total = $this->reports->countServiceSummary(
            (string) $period['start'],
            (string) $period['end_exclusive'],
            $search
        );

        $pagination = $this->paginationWithTotal(
            $pagination,
            $total
        );

        $rows = $this->reports->serviceSummary(
            (string) $period['start'],
            (string) $period['end_exclusive'],
            $search,
            $sort,
            $direction,
            (int) $pagination['per_page'],
            (int) $pagination['offset']
        );

        return [
            'period' => $period,
            'can_view_financial' => $canViewFinancial,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'pagination' => $pagination,
            'rows' => array_map(
                fn(array $row): array => $this->normalizeServiceRow(
                    $row,
                    $canViewFinancial
                ),
                $rows
            ),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function serviceExecutionDetailsReport(
        array $input,
        bool $canViewFinancial
    ): array {
        $period = $this->resolvePeriod($input);
        $pagination = $this->pagination($input);
        [$serviceId, $descriptionHash] = $this->serviceGroup(
            (string) ($input['group_key'] ?? '')
        );

        $total = $this->reports->countServiceExecutionDetails(
            $serviceId,
            $descriptionHash,
            (string) $period['start'],
            (string) $period['end_exclusive']
        );

        $pagination = $this->paginationWithTotal(
            $pagination,
            $total
        );

        $rows = $this->reports->serviceExecutionDetails(
            $serviceId,
            $descriptionHash,
            (string) $period['start'],
            (string) $period['end_exclusive'],
            (int) $pagination['per_page'],
            (int) $pagination['offset']
        );

        return [
            'period' => $period,
            'group_key' => (string) ($input['group_key'] ?? ''),
            'can_view_financial' => $canViewFinancial,
            'pagination' => $pagination,
            'rows' => array_map(
                fn(array $row): array => $this->normalizeServiceExecutionDetail(
                    $row,
                    $canViewFinancial
                ),
                $rows
            ),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function teamReport(
        array $input,
        bool $canViewCommission = true
    ): array {
        $period = $this->resolvePeriod($input);
        $goalApplicable = ($period['mode'] ?? '') === 'month';
        $goalRow = null;

        if ($goalApplicable) {
            $goalRow = $this->reports->activeGoal(
                (string) $period['competence'] . '-01'
            );
        }

        $goalCents = $goalRow === null
            ? 0
            : self::databaseMoneyToCents(
                (string) ($goalRow['valor_meta'] ?? '0')
            );

        $percentageUnits = $goalRow === null
            ? 0
            : self::databasePercentageToUnits(
                (string) ($goalRow['percentual_comissao'] ?? '0')
            );

        $summaryRow = $this->reports->summary(
            (string) $period['start'],
            (string) $period['end_exclusive']
        );

        $companyTotal = self::databaseMoneyToCents(
            (string) ($summaryRow['company_total'] ?? '0')
        );

        $companyServices = self::databaseMoneyToCents(
            (string) ($summaryRow['service_total'] ?? '0')
        );

        $employees = [];
        $qualifiedCount = 0;

        foreach (
            $this->reports->employeeProduction(
                (string) $period['start'],
                (string) $period['end_exclusive']
            ) as $row
        ) {
            $realized = self::databaseMoneyToCents(
                (string) ($row['realized'] ?? '0')
            );

            $serviceTotal = self::databaseMoneyToCents(
                (string) ($row['service_total'] ?? '0')
            );

            $outcome = self::goalOutcome(
                $realized,
                $goalCents,
                $percentageUnits,
                $goalApplicable && $goalRow !== null
            );

            if ($outcome['qualified']) {
                ++$qualifiedCount;
            }

            $employee = [
                'id' => (int) ($row['id'] ?? 0),
                'code' => (string) ($row['codigo'] ?? ''),
                'name' => (string) ($row['nome'] ?? ''),
                'function' => (string) ($row['funcao'] ?? ''),
                'orders' => (int) ($row['orders'] ?? 0),
            ];

            if ($canViewCommission) {
                $employee['realized'] = self::centsToDecimal($realized);
                $employee['service_total'] = self::centsToDecimal($serviceTotal);
                $employee['progress_percent'] = self::progressPercentage(
                    $realized,
                    $goalCents
                );
                $employee['remaining'] = self::centsToDecimal(
                    max(0, $goalCents - $realized)
                );
                $employee['exceeded'] = self::centsToDecimal(
                    max(0, $realized - $goalCents)
                );
                $employee['qualified'] = $outcome['qualified'];
                $employee['prize'] = self::centsToDecimal(
                    $outcome['prize_cents']
                );
            }

            $employees[] = $employee;
        }

        $details = array_map(
            function (array $row) use ($canViewCommission): array {
                $detail = [
                    'employee_id' => (int) ($row['employee_id'] ?? 0),
                    'employee_name' => (string) ($row['employee_name'] ?? ''),
                    'employee_function' => (string) ($row['employee_function'] ?? ''),
                    'order_id' => (int) ($row['order_id'] ?? 0),
                    'order_number' => (string) ($row['order_number'] ?? ''),
                    'client_name' => (string) ($row['client_name'] ?? ''),
                    'finalized_at' => (string) ($row['finalized_at'] ?? ''),
                ];

                if ($canViewCommission) {
                    $detail['service_total'] = self::centsToDecimal(
                        self::databaseMoneyToCents(
                            (string) ($row['service_total'] ?? '0')
                        )
                    );

                    $detail['executed_total'] = self::centsToDecimal(
                        self::databaseMoneyToCents(
                            (string) ($row['executed_total'] ?? '0')
                        )
                    );
                }

                return $detail;
            },
            $this->reports->employeeOrderDetails(
                (string) $period['start'],
                (string) $period['end_exclusive']
            )
        );

        $summary = [
            'orders' => (int) ($summaryRow['orders'] ?? 0),
            'employee_count' => count($employees),
            'qualified_count' => $canViewCommission
                ? $qualifiedCount
                : 0,
        ];

        if ($canViewCommission) {
            $summary['company_total'] = self::centsToDecimal($companyTotal);
            $summary['service_total'] = self::centsToDecimal($companyServices);
        }

        return [
            'period' => $period,
            'can_view_commission' => $canViewCommission,
            'goal' => [
                'applicable' => $goalApplicable,
                'configured' => $goalApplicable && $goalRow !== null,
                'amount' => $canViewCommission
                    ? self::centsToDecimal($goalCents)
                    : null,
                'percentage' => $canViewCommission
                    ? self::percentageUnitsToDecimal($percentageUnits)
                    : null,
                'version' => $goalRow === null
                    ? null
                    : (int) ($goalRow['versao'] ?? 0),
            ],
            'summary' => $summary,
            'employees' => $employees,
            'details' => $details,
        ];
    }

    /** @param array<string,mixed> $data */
    public function saveMonthlyGoal(array $data, int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException(
                'Usuário inválido para configurar a meta.'
            );
        }

        $month = $this->competence(
            isset($data['competencia'])
                ? (string) $data['competencia']
                : null
        );

        $goalCents = self::brazilianMoneyToCents(
            (string) ($data['valor_meta'] ?? '')
        );

        if ($goalCents <= 0 || $goalCents > 999999999999) {
            throw new InvalidArgumentException(
                'A meta deve ser maior que zero e respeitar o limite monetário.'
            );
        }

        $percentageUnits = self::brazilianPercentageToUnits(
            (string) (
                $data['percentual_comissao']
                ?? $data['percentual_premio']
                ?? ''
            )
        );

        if ($percentageUnits <= 0 || $percentageUnits > 10000) {
            throw new InvalidArgumentException(
                'O percentual deve ser maior que zero e no máximo 100%.'
            );
        }

        $this->reports->saveGoal(
            $month->format('Y-m-01'),
            self::centsToDecimal($goalCents),
            self::percentageUnitsToDecimal($percentageUnits),
            $userId
        );
    }

    public static function brazilianMoneyToCents(string $value): int
    {
        $normalized = str_replace(
            ["\u{00A0}", ' ', 'R$', 'r$'],
            '',
            trim($value)
        );

        if ($normalized === '') {
            throw new InvalidArgumentException(
                'Informe o valor da meta.'
            );
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace(
                ',',
                '.',
                str_replace('.', '', $normalized)
            );
        }

        return self::decimalToScaledInteger(
            $normalized,
            2,
            'Valor monetário inválido.'
        );
    }

    public static function brazilianPercentageToUnits(string $value): int
    {
        $normalized = str_replace(
            ["\u{00A0}", ' ', '%'],
            '',
            trim($value)
        );

        if ($normalized === '') {
            throw new InvalidArgumentException(
                'Informe o percentual da comissão.'
            );
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace(
                ',',
                '.',
                str_replace('.', '', $normalized)
            );
        }

        return self::decimalToScaledInteger(
            $normalized,
            2,
            'Percentual inválido.'
        );
    }

    public static function prizeCents(
        int $realizedCents,
        int $percentageUnits
    ): int {
        if (
            $realizedCents < 0
            || $percentageUnits < 0
            || $percentageUnits > 10000
        ) {
            throw new InvalidArgumentException(
                'Valores inválidos para cálculo da comissão.'
            );
        }

        if (
            $realizedCents !== 0
            && $percentageUnits > intdiv(
                PHP_INT_MAX - 5000,
                $realizedCents
            )
        ) {
            throw new InvalidArgumentException(
                'Valor excede o limite de cálculo da comissão.'
            );
        }

        return intdiv(
            ($realizedCents * $percentageUnits) + 5000,
            10000
        );
    }

    /** @return array{qualified:bool,prize_cents:int} */
    public static function goalOutcome(
        int $realizedCents,
        int $goalCents,
        int $percentageUnits,
        bool $configured = true
    ): array {
        if ($realizedCents < 0 || $goalCents < 0) {
            throw new InvalidArgumentException(
                'Valores inválidos para apuração da meta.'
            );
        }

        $qualified = $configured
            && $goalCents > 0
            && $realizedCents >= $goalCents;

        return [
            'qualified' => $qualified,
            'prize_cents' => $qualified
                ? self::prizeCents(
                    $realizedCents,
                    $percentageUnits
                )
                : 0,
        ];
    }

    /** @return array<string,mixed> */
    private function monthPeriod(?string $competence): array
    {
        $month = $this->competence($competence);
        $endExclusive = $month->modify('first day of next month');

        return $this->periodPayload(
            'month',
            $month->format('Y-m'),
            $month,
            $endExclusive,
            self::MONTHS[(int) $month->format('n')]
                . ' de '
                . $month->format('Y'),
            [
                'modo' => 'mes',
                'competencia' => $month->format('Y-m'),
            ]
        );
    }

    /**
     * @param array<string,string> $query
     * @return array<string,mixed>
     */
    private function periodPayload(
        string $mode,
        ?string $competence,
        DateTimeImmutable $start,
        DateTimeImmutable $endExclusive,
        string $label,
        array $query
    ): array {
        $days = (int) $start->diff($endExclusive)->format('%a');
        $displayEnd = $endExclusive->modify('-1 day');
        $previousStart = $start->modify('-' . $days . ' days');
        $previousEndExclusive = $start;
        $previousDisplayEnd = $previousEndExclusive->modify('-1 day');

        return [
            'mode' => $mode,
            'competence' => $competence,
            'start' => $start->format('Y-m-d 00:00:00'),
            'end_exclusive' => $endExclusive->format('Y-m-d 00:00:00'),
            'display_start' => $start->format('Y-m-d'),
            'display_end' => $displayEnd->format('Y-m-d'),
            'label' => $label,
            'days' => $days,
            'previous_start' => $previousStart->format('Y-m-d 00:00:00'),
            'previous_end_exclusive' => $previousEndExclusive->format('Y-m-d 00:00:00'),
            'previous_display_start' => $previousStart->format('Y-m-d'),
            'previous_display_end' => $previousDisplayEnd->format('Y-m-d'),
            'previous_label' => $this->dateRangeLabel(
                $previousStart,
                $previousDisplayEnd
            ),
            'query' => $query,
        ];
    }

    private function competence(?string $value): DateTimeImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return new DateTimeImmutable(
                'first day of this month 00:00:00'
            );
        }

        if (
            !preg_match(
                '/^(\d{4})-(\d{2})(?:-01)?$/',
                $value,
                $matches
            )
        ) {
            throw new InvalidArgumentException(
                'Competência mensal inválida.'
            );
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];

        if (
            $year < 2000
            || $year > 2100
            || $month < 1
            || $month > 12
        ) {
            throw new InvalidArgumentException(
                'Competência mensal inválida.'
            );
        }

        return new DateTimeImmutable(
            sprintf(
                '%04d-%02d-01 00:00:00',
                $year,
                $month
            )
        );
    }

    private function strictDate(
        string $value,
        string $message
    ): DateTimeImmutable {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date instanceof DateTimeImmutable
            || $date->format('Y-m-d') !== $value
            || (
                is_array($errors)
                && (
                    ($errors['warning_count'] ?? 0) > 0
                    || ($errors['error_count'] ?? 0) > 0
                )
            )
        ) {
            throw new InvalidArgumentException($message);
        }

        $year = (int) $date->format('Y');

        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException($message);
        }

        return $date;
    }

    private function dateRangeLabel(
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): string {
        return $start->format('d/m/Y')
            . ' a '
            . $end->format('d/m/Y');
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeCompanySummary(
        array $row,
        bool $canViewFinancial
    ): array {
        $orders = (int) ($row['orders'] ?? 0);
        $companyTotalCents = self::databaseMoneyToCents(
            (string) ($row['company_total'] ?? '0')
        );

        $summary = [
            'orders' => $orders,
            'unique_clients' => (int) ($row['unique_clients'] ?? 0),
            'company_total' => null,
            'service_total' => null,
            'product_total' => null,
            'other_total' => null,
            'discount_total' => null,
            'addition_total' => null,
            'average_ticket' => null,
            'received_total' => null,
            'receivable_balance' => null,
            'pending_accounts' => null,
            'overdue_accounts' => null,
        ];

        if (!$canViewFinancial) {
            return $summary;
        }

        $summary['company_total'] = self::centsToDecimal(
            $companyTotalCents
        );
        $summary['service_total'] = $this->normalizedMoney(
            $row['service_total'] ?? '0'
        );
        $summary['product_total'] = $this->normalizedMoney(
            $row['product_total'] ?? '0'
        );
        $summary['other_total'] = $this->normalizedMoney(
            $row['other_total'] ?? '0'
        );
        $summary['discount_total'] = $this->normalizedMoney(
            $row['discount_total'] ?? '0'
        );
        $summary['addition_total'] = $this->normalizedMoney(
            $row['addition_total'] ?? '0'
        );
        $summary['average_ticket'] = self::centsToDecimal(
            self::divideRounded(
                $companyTotalCents,
                $orders
            )
        );
        $summary['received_total'] = $this->normalizedMoney(
            $row['received_total'] ?? '0'
        );
        $summary['receivable_balance'] = $this->normalizedMoney(
            $row['receivable_balance'] ?? '0'
        );
        $summary['pending_accounts'] = (int) ($row['pending_accounts'] ?? 0);
        $summary['overdue_accounts'] = (int) ($row['overdue_accounts'] ?? 0);

        return $summary;
    }

    /**
     * @param array<string,mixed> $period
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function dailyEvolution(
        array $period,
        array $rows,
        bool $canViewFinancial
    ): array {
        $indexed = [];

        foreach ($rows as $row) {
            $date = (string) ($row['reference_date'] ?? '');

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                continue;
            }

            $indexed[$date] = $row;
        }

        $current = new DateTimeImmutable(
            (string) $period['display_start'] . ' 00:00:00'
        );

        $endExclusive = new DateTimeImmutable(
            (string) $period['end_exclusive']
        );

        $evolution = [];

        while ($current < $endExclusive) {
            $date = $current->format('Y-m-d');
            $row = $indexed[$date] ?? [];

            $evolution[] = [
                'date' => $date,
                'label' => $current->format('d/m'),
                'orders' => (int) ($row['orders'] ?? 0),
                'company_total' => $canViewFinancial
                    ? $this->normalizedMoney(
                        $row['company_total'] ?? '0'
                    )
                    : null,
            ];

            $current = $current->modify('+1 day');
        }

        return $evolution;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeClientRanking(array $rows): array
    {
        return array_map(
            fn(array $row): array => [
                'client_id' => (int) ($row['client_id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'orders' => (int) ($row['order_count'] ?? 0),
                'revenue_total' => $this->normalizedMoney(
                    $row['executed_total'] ?? '0'
                ),
            ],
            $rows
        );
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeServiceRanking(
        array $rows,
        bool $includeRevenue
    ): array {
        return array_map(
            fn(array $row): array => [
                'group_key' => (string) ($row['group_key'] ?? ''),
                'service_id' => isset($row['service_id'])
                    && $row['service_id'] !== null
                    ? (int) $row['service_id']
                    : null,
                'code' => (string) ($row['code'] ?? ''),
                'description' => (string) ($row['historical_description'] ?? ''),
                'origin' => (string) ($row['origin'] ?? 'manual'),
                'quantity_total' => $this->normalizedQuantity(
                    $row['quantity_total'] ?? '0'
                ),
                'orders' => (int) ($row['order_count'] ?? 0),
                'revenue_total' => $includeRevenue
                    ? $this->normalizedMoney(
                        $row['revenue_total'] ?? '0'
                    )
                    : null,
            ],
            $rows
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeClientRow(
        array $row,
        bool $canViewFinancial
    ): array {
        $normalized = [
            'client_id' => (int) ($row['client_id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'document' => (string) ($row['document'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'order_count' => (int) ($row['order_count'] ?? 0),
            'first_finalized_at' => (string) ($row['first_finalized_at'] ?? ''),
            'last_finalized_at' => (string) ($row['last_finalized_at'] ?? ''),
            'financial' => null,
        ];

        if ($canViewFinancial) {
            $normalized['financial'] = [
                'executed_total' => $this->normalizedMoney(
                    $row['executed_total'] ?? '0'
                ),
                'service_total' => $this->normalizedMoney(
                    $row['service_total'] ?? '0'
                ),
                'product_total' => $this->normalizedMoney(
                    $row['product_total'] ?? '0'
                ),
                'other_total' => $this->normalizedMoney(
                    $row['other_total'] ?? '0'
                ),
                'discount_total' => $this->normalizedMoney(
                    $row['discount_total'] ?? '0'
                ),
                'addition_total' => $this->normalizedMoney(
                    $row['addition_total'] ?? '0'
                ),
                'average_ticket' => $this->normalizedMoney(
                    $row['average_ticket'] ?? '0'
                ),
                'received_total' => $this->normalizedMoney(
                    $row['received_total'] ?? '0'
                ),
                'pending_balance' => $this->normalizedMoney(
                    $row['pending_balance'] ?? '0'
                ),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeClientOrderDetail(
        array $row,
        bool $canViewFinancial
    ): array {
        $normalized = [
            'order_id' => (int) ($row['order_id'] ?? 0),
            'order_number' => (string) ($row['order_number'] ?? ''),
            'finalized_at' => (string) ($row['finalized_at'] ?? ''),
            'team_members' => (string) ($row['team_members'] ?? ''),
            'financial' => null,
        ];

        if ($canViewFinancial) {
            $normalized['financial'] = [
                'executed_total' => $this->normalizedMoney(
                    $row['executed_total'] ?? '0'
                ),
                'service_total' => $this->normalizedMoney(
                    $row['service_total'] ?? '0'
                ),
                'product_total' => $this->normalizedMoney(
                    $row['product_total'] ?? '0'
                ),
                'other_total' => $this->normalizedMoney(
                    $row['other_total'] ?? '0'
                ),
                'status' => (string) ($row['financial_status'] ?? 'sem_conta'),
                'received_total' => $this->normalizedMoney(
                    $row['received_total'] ?? '0'
                ),
                'pending_balance' => $this->normalizedMoney(
                    $row['pending_balance'] ?? '0'
                ),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeServiceRow(
        array $row,
        bool $canViewFinancial
    ): array {
        $normalized = [
            'group_key' => (string) ($row['group_key'] ?? ''),
            'service_id' => isset($row['service_id'])
                && $row['service_id'] !== null
                ? (int) $row['service_id']
                : null,
            'code' => (string) ($row['code'] ?? ''),
            'historical_description' => (string) ($row['historical_description'] ?? ''),
            'current_name' => (string) ($row['current_name'] ?? ''),
            'origin' => (string) ($row['origin'] ?? 'manual'),
            'quantity_total' => $this->normalizedQuantity(
                $row['quantity_total'] ?? '0'
            ),
            'order_count' => (int) ($row['order_count'] ?? 0),
            'client_count' => (int) ($row['client_count'] ?? 0),
            'first_executed_at' => (string) ($row['first_executed_at'] ?? ''),
            'last_executed_at' => (string) ($row['last_executed_at'] ?? ''),
            'financial' => null,
        ];

        if ($canViewFinancial) {
            $normalized['financial'] = [
                'revenue_total' => $this->normalizedMoney(
                    $row['revenue_total'] ?? '0'
                ),
                'discount_total' => $this->normalizedMoney(
                    $row['discount_total'] ?? '0'
                ),
                'average_unit_value' => $this->normalizedMoney(
                    $row['average_unit_value'] ?? '0'
                ),
                'average_order_ticket' => $this->normalizedMoney(
                    $row['average_order_ticket'] ?? '0'
                ),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeServiceExecutionDetail(
        array $row,
        bool $canViewFinancial
    ): array {
        $normalized = [
            'order_id' => (int) ($row['order_id'] ?? 0),
            'order_number' => (string) ($row['order_number'] ?? ''),
            'client_id' => (int) ($row['client_id'] ?? 0),
            'client_name' => (string) ($row['client_name'] ?? ''),
            'executed_at' => (string) ($row['executed_at'] ?? ''),
            'historical_description' => (string) ($row['historical_description'] ?? ''),
            'quantity' => $this->normalizedQuantity(
                $row['quantity'] ?? '0'
            ),
            'unit' => (string) ($row['unit'] ?? ''),
            'team_members' => (string) ($row['team_members'] ?? ''),
            'financial' => null,
        ];

        if ($canViewFinancial) {
            $normalized['financial'] = [
                'unit_value' => $this->normalizedMoney(
                    $row['unit_value'] ?? '0'
                ),
                'discount' => $this->normalizedMoney(
                    $row['discount'] ?? '0'
                ),
                'subtotal' => $this->normalizedMoney(
                    $row['subtotal'] ?? '0'
                ),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{page:int,per_page:int,offset:int}
     */
    private function pagination(array $input): array
    {
        $page = filter_var(
            $input['pagina'] ?? $input['page'] ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $perPage = filter_var(
            $input['por_pagina'] ?? $input['per_page'] ?? 20,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 100,
                ],
            ]
        );

        if (!is_int($page) || !is_int($perPage)) {
            throw new InvalidArgumentException(
                'Paginação inválida.'
            );
        }

        if ($page > 1000000) {
            throw new InvalidArgumentException(
                'Página solicitada inválida.'
            );
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * @param array{page:int,per_page:int,offset:int} $pagination
     * @return array<string,int|bool>
     */
    private function paginationWithTotal(
        array $pagination,
        int $total
    ): array {
        $perPage = $pagination['per_page'];
        $lastPage = max(
            1,
            (int) ceil($total / $perPage)
        );

        $page = min(
            $pagination['page'],
            $lastPage
        );

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'total' => max(0, $total),
            'last_page' => $lastPage,
            'has_previous' => $page > 1,
            'has_next' => $page < $lastPage,
        ];
    }

    private function searchTerm(mixed $value): string
    {
        $search = trim((string) $value);
        $length = function_exists('mb_strlen')
            ? mb_strlen($search, 'UTF-8')
            : strlen($search);

        if ($length > 120 || str_contains($search, "\0")) {
            throw new InvalidArgumentException(
                'Busca inválida para o relatório.'
            );
        }

        return $search;
    }

    /**
     * @param array<string,string> $allowed
     */
    private function allowedSort(
        string $value,
        array $allowed,
        string $default
    ): string {
        $normalized = strtolower(trim($value));

        return $allowed[$normalized] ?? $default;
    }

    private function direction(string $value): string
    {
        return strtolower(trim($value)) === 'asc'
            ? 'asc'
            : 'desc';
    }

    private function positiveInt(
        mixed $value,
        string $message
    ): int {
        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!is_int($id)) {
            throw new InvalidArgumentException($message);
        }

        return $id;
    }

    /** @return array{0:?int,1:string} */
    private function serviceGroup(string $groupKey): array
    {
        $groupKey = strtolower(trim($groupKey));

        if (
            preg_match(
                '/^manual:([a-f0-9]{64})$/',
                $groupKey,
                $matches
            ) === 1
        ) {
            return [null, $matches[1]];
        }

        if (
            preg_match(
                '/^registered:([1-9]\d*):([a-f0-9]{64})$/',
                $groupKey,
                $matches
            ) === 1
        ) {
            return [(int) $matches[1], $matches[2]];
        }

        throw new InvalidArgumentException(
            'Agrupamento de serviço inválido.'
        );
    }

    private function normalizedMoney(mixed $value): string
    {
        return self::centsToDecimal(
            self::databaseMoneyToCents((string) $value)
        );
    }

    private function normalizedQuantity(mixed $value): string
    {
        return self::scaledIntegerToDecimal(
            self::decimalToScaledInteger(
                (string) $value,
                3,
                'Quantidade armazenada inválida.'
            ),
            3
        );
    }

    private static function databaseMoneyToCents(string $value): int
    {
        return self::decimalToScaledIntegerRounded(
            $value,
            2,
            'Valor monetário armazenado inválido.'
        );
    }

    private static function databasePercentageToUnits(string $value): int
    {
        return self::decimalToScaledInteger(
            $value,
            2,
            'Percentual armazenado inválido.'
        );
    }

    private static function decimalToScaledIntegerRounded(
        string $value,
        int $scale,
        string $message
    ): int {
        $value = trim($value);

        if (
            $scale < 0
            || $scale > 6
            || preg_match('/^\d+(?:\.\d+)?$/', $value) !== 1
        ) {
            throw new InvalidArgumentException($message);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        $keptFraction = substr(
            str_pad($fraction, $scale, '0'),
            0,
            $scale
        );

        $normalized = $whole;

        if ($scale > 0) {
            $normalized .= '.' . $keptFraction;
        }

        $scaled = self::decimalToScaledInteger(
            $normalized,
            $scale,
            $message
        );

        $roundingDigit = isset($fraction[$scale])
            ? (int) $fraction[$scale]
            : 0;

        if ($roundingDigit >= 5) {
            if ($scaled === PHP_INT_MAX) {
                throw new InvalidArgumentException($message);
            }

            ++$scaled;
        }

        return $scaled;
    }

    private static function decimalToScaledInteger(
        string $value,
        int $scale,
        string $message
    ): int {
        $value = trim($value);

        if (
            $scale < 0
            || $scale > 6
            || !preg_match(
                '/^\d+(?:\.\d{1,' . $scale . '})?$/',
                $value
            )
        ) {
            throw new InvalidArgumentException($message);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        $factor = 10 ** $scale;

        if (
            strlen($whole) > 16
            || (int) $whole > intdiv(
                PHP_INT_MAX - ($factor - 1),
                $factor
            )
        ) {
            throw new InvalidArgumentException($message);
        }

        return ((int) $whole * $factor)
            + (int) str_pad(
                $fraction,
                $scale,
                '0'
            );
    }

    private static function centsToDecimal(int $cents): string
    {
        return self::scaledIntegerToDecimal($cents, 2);
    }

    private static function scaledIntegerToDecimal(
        int $value,
        int $scale
    ): string {
        $negative = $value < 0;
        $absolute = abs($value);
        $factor = 10 ** $scale;

        return ($negative ? '-' : '')
            . intdiv($absolute, $factor)
            . '.'
            . str_pad(
                (string) ($absolute % $factor),
                $scale,
                '0',
                STR_PAD_LEFT
            );
    }

    private static function percentageUnitsToDecimal(int $units): string
    {
        return self::scaledIntegerToDecimal($units, 2);
    }

    private static function progressPercentage(
        int $realizedCents,
        int $goalCents
    ): string {
        if ($goalCents <= 0) {
            return '0.00';
        }

        $hundredths = self::roundedRatio(
            $realizedCents,
            $goalCents,
            10000
        );

        return self::percentageUnitsToDecimal($hundredths);
    }

    private static function divideRounded(
        int $value,
        int $divisor
    ): int {
        if ($divisor <= 0 || $value <= 0) {
            return 0;
        }

        return intdiv(
            $value + intdiv($divisor, 2),
            $divisor
        );
    }

    /** @return array<string,mixed> */
    private static function comparison(
        int $current,
        int $previous
    ): array {
        if ($current === $previous) {
            return [
                'direction' => 'stable',
                'percentage' => '0.00',
                'comparable' => true,
            ];
        }

        if ($previous === 0) {
            return [
                'direction' => $current > 0 ? 'up' : 'down',
                'percentage' => null,
                'comparable' => false,
            ];
        }

        $difference = abs($current - $previous);
        $hundredths = self::roundedRatio(
            $difference,
            abs($previous),
            10000
        );

        return [
            'direction' => $current > $previous ? 'up' : 'down',
            'percentage' => self::percentageUnitsToDecimal($hundredths),
            'comparable' => true,
        ];
    }

    private static function roundedRatio(
        int $numerator,
        int $denominator,
        int $factor
    ): int {
        if ($numerator < 0 || $denominator <= 0 || $factor <= 0) {
            throw new InvalidArgumentException(
                'Valores inválidos para cálculo proporcional.'
            );
        }

        $whole = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($whole > intdiv(PHP_INT_MAX, $factor)) {
            throw new InvalidArgumentException(
                'Valor excede o limite de cálculo proporcional.'
            );
        }

        if (
            $remainder !== 0
            && $remainder > intdiv(
                PHP_INT_MAX - intdiv($denominator, 2),
                $factor
            )
        ) {
            throw new InvalidArgumentException(
                'Valor excede o limite de cálculo proporcional.'
            );
        }

        $fraction = intdiv(
            ($remainder * $factor)
                + intdiv($denominator, 2),
            $denominator
        );

        return ($whole * $factor) + $fraction;
    }
}