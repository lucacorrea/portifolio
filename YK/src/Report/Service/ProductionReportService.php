<?php

declare(strict_types=1);

namespace App\Report\Service;

use App\Report\Repository\ProductionReportRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class ProductionReportService
{
    private const MONTHS = [
        1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    public function __construct(private readonly ProductionReportRepository $reports)
    {
    }

    /** @param array<string,bool> $scope @return array<string,mixed> */
    public function monthlyReport(?string $competencia, array $scope = []): array
    {
        $scope += [
            'operational' => false,
            'financial' => false,
            'stock' => false,
            'stock_cost' => false,
            'employees' => false,
            'commission' => false,
            'goal' => false,
        ];
        $includeOperations = (bool) $scope['operational'];
        $includeFinancial = (bool) $scope['financial'];
        $includeStock = (bool) $scope['stock'];
        $includeStockCost = (bool) $scope['stock_cost'];
        $includeEmployees = (bool) $scope['employees'];
        $includeCommission = (bool) $scope['commission'];
        $includeGoal = (bool) $scope['goal'];

        $month = $this->competence($competencia);
        $start = $month->format('Y-m-01 00:00:00');
        $endExclusive = $month->modify('first day of next month')->format('Y-m-01 00:00:00');
        $goalRow = ($includeEmployees || $includeCommission || $includeGoal)
            ? $this->reports->activeGoal($month->format('Y-m-01'))
            : null;
        $goalCents = $goalRow === null ? 0 : self::databaseMoneyToCents((string) $goalRow['valor_meta']);
        $percentageUnits = $goalRow === null
            ? 0
            : self::databasePercentageToUnits((string) $goalRow['percentual_comissao']);

        $summaryRow = ($includeOperations || $includeFinancial)
            ? $this->reports->summary($start, $endExclusive, $includeFinancial)
            : [];
        $orders = (int) ($summaryRow['orders'] ?? 0);
        $companyTotal = self::databaseMoneyToCents((string) ($summaryRow['company_total'] ?? '0'));
        $serviceTotal = self::databaseMoneyToCents((string) ($summaryRow['service_total'] ?? '0'));
        $productTotal = self::databaseMoneyToCents((string) ($summaryRow['product_total'] ?? '0'));
        $otherTotal = self::databaseMoneyToCents((string) ($summaryRow['other_total'] ?? '0'));
        $discountTotal = self::databaseMoneyToCents((string) ($summaryRow['discount_total'] ?? '0'));
        $increaseTotal = self::databaseMoneyToCents((string) ($summaryRow['increase_total'] ?? '0'));
        $receivedTotal = self::databaseMoneyToCents((string) ($summaryRow['received_total'] ?? '0'));
        $receivableBalance = self::databaseMoneyToCents((string) ($summaryRow['receivable_balance'] ?? '0'));
        $averageTicket = $orders > 0 ? intdiv($companyTotal + intdiv($orders, 2), $orders) : 0;

        $financialRow = $includeFinancial
            ? $this->reports->financialSummary($start, $endExclusive)
            : [];
        $cashIn = self::databaseMoneyToCents((string) ($financialRow['cash_in'] ?? '0'));
        $cashOut = self::databaseMoneyToCents((string) ($financialRow['cash_out'] ?? '0'));
        $inventoryRow = $includeStock ? $this->reports->inventorySummary($includeStockCost) : [];

        $employees = [];
        $qualifiedCount = 0;
        if ($includeEmployees) {
            foreach ($this->reports->employeeProduction($start, $endExclusive, $includeCommission) as $row) {
                $realized = self::databaseMoneyToCents((string) ($row['realized'] ?? '0'));
                $employeeServices = self::databaseMoneyToCents((string) ($row['service_total'] ?? '0'));
                $outcome = self::goalOutcome(
                    $realized,
                    $goalCents,
                    $percentageUnits,
                    $goalRow !== null && $includeCommission
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
                if ($includeCommission) {
                    $employee += [
                        'realized' => self::centsToDecimal($realized),
                        'service_total' => self::centsToDecimal($employeeServices),
                        'progress_percent' => self::progressPercentage($realized, $goalCents),
                        'remaining' => self::centsToDecimal(max(0, $goalCents - $realized)),
                        'exceeded' => self::centsToDecimal(max(0, $realized - $goalCents)),
                        'qualified' => $outcome['qualified'],
                        'prize' => self::centsToDecimal($outcome['prize_cents']),
                    ];
                }
                $employees[] = $employee;
            }
        }

        $employeeDetails = [];
        if ($includeEmployees) {
            foreach ($this->reports->employeeOrderDetails($start, $endExclusive, $includeCommission) as $row) {
                $detail = [
                    'employee_name' => (string) ($row['employee_name'] ?? ''),
                    'employee_function' => (string) ($row['employee_function'] ?? ''),
                    'order_number' => (string) ($row['order_number'] ?? ''),
                    'client_name' => (string) ($row['client_name'] ?? ''),
                    'finalized_at' => (string) ($row['finalized_at'] ?? ''),
                ];
                if ($includeCommission) {
                    $detail += [
                        'service_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['service_total'] ?? '0'))),
                        'executed_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['executed_total'] ?? '0'))),
                    ];
                }
                $employeeDetails[] = $detail;
            }
        }

        $companyDetails = [];
        if ($includeOperations || $includeFinancial) {
            foreach ($this->reports->companyOrderDetails($start, $endExclusive, $includeFinancial) as $row) {
                $companyDetails[] = [
                    'order_number' => (string) ($row['order_number'] ?? ''),
                    'client_name' => (string) ($row['client_name'] ?? ''),
                    'finalized_at' => (string) ($row['finalized_at'] ?? ''),
                    'service_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['service_total'] ?? '0'))),
                    'product_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['product_total'] ?? '0'))),
                    'other_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['other_total'] ?? '0'))),
                    'discount_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['discount_total'] ?? '0'))),
                    'increase_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['increase_total'] ?? '0'))),
                    'executed_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['executed_total'] ?? '0'))),
                    'received_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['received_total'] ?? '0'))),
                    'balance' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['balance'] ?? '0'))),
                    'payment_status' => (string) ($row['payment_status'] ?? 'pendente'),
                ];
            }
        }

        $mapRanking = static fn(array $row): array => [
            'description' => (string) ($row['descricao'] ?? ''),
            'unit' => (string) ($row['unidade'] ?? ''),
            'orders' => (int) ($row['orders'] ?? 0),
            'quantity' => (string) ($row['quantity'] ?? '0.000'),
            'total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($row['total'] ?? '0'))),
        ];
        $serviceRanking = $includeOperations
            ? array_map($mapRanking, $this->reports->itemRanking($start, $endExclusive, 'servico', $includeFinancial))
            : [];
        $productRanking = $includeStock
            ? array_map($mapRanking, $this->reports->itemRanking($start, $endExclusive, 'produto', $includeFinancial))
            : [];

        return [
            'competencia' => $month->format('Y-m'),
            'period_label' => self::MONTHS[(int) $month->format('n')] . ' de ' . $month->format('Y'),
            'goal' => [
                'configured' => $goalRow !== null,
                'amount' => $includeCommission ? self::centsToDecimal($goalCents) : '0.00',
                'percentage' => $includeCommission ? self::percentageUnitsToDecimal($percentageUnits) : '0.00',
            ],
            'summary' => [
                'orders' => $orders,
                'clients' => (int) ($summaryRow['clients'] ?? 0),
                'company_total' => self::centsToDecimal($companyTotal),
                'service_total' => self::centsToDecimal($serviceTotal),
                'product_total' => self::centsToDecimal($productTotal),
                'other_total' => self::centsToDecimal($otherTotal),
                'discount_total' => self::centsToDecimal($discountTotal),
                'increase_total' => self::centsToDecimal($increaseTotal),
                'received_total' => self::centsToDecimal($receivedTotal),
                'receivable_balance' => self::centsToDecimal($receivableBalance),
                'average_ticket' => self::centsToDecimal($averageTicket),
                'paid_orders' => (int) ($summaryRow['paid_orders'] ?? 0),
                'open_orders' => (int) ($summaryRow['open_orders'] ?? 0),
                'employee_count' => count($employees),
                'qualified_count' => $qualifiedCount,
            ],
            'financial' => [
                'cash_in' => self::centsToDecimal($cashIn),
                'cash_out' => self::centsToDecimal($cashOut),
                'cash_net' => self::centsToDecimal($cashIn - $cashOut),
                'paid_installments' => (int) ($financialRow['paid_installments'] ?? 0),
                'paid_expenses' => self::centsToDecimal(self::databaseMoneyToCents((string) ($financialRow['paid_expenses'] ?? '0'))),
                'sales' => (int) ($financialRow['sales'] ?? 0),
                'sales_total' => self::centsToDecimal(self::databaseMoneyToCents((string) ($financialRow['sales_total'] ?? '0'))),
            ],
            'inventory' => [
                'active_products' => (int) ($inventoryRow['active_products'] ?? 0),
                'out_of_stock' => (int) ($inventoryRow['out_of_stock'] ?? 0),
                'low_stock' => (int) ($inventoryRow['low_stock'] ?? 0),
                'stock_cost_value' => self::centsToDecimal(self::databaseMoneyToCents((string) ($inventoryRow['stock_cost_value'] ?? '0'))),
            ],
            'company_details' => $companyDetails,
            'service_ranking' => $serviceRanking,
            'product_ranking' => $productRanking,
            'employees' => $employees,
            'details' => $employeeDetails,
        ];
    }
    /** @param array<string,mixed> $data */
    public function saveMonthlyGoal(array $data, int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido para configurar a meta.');
        }

        $month = $this->competence(isset($data['competencia']) ? (string) $data['competencia'] : null);
        $goalCents = self::brazilianMoneyToCents((string) ($data['valor_meta'] ?? ''));
        if ($goalCents <= 0 || $goalCents > 999999999999) {
            throw new InvalidArgumentException('A meta deve ser maior que zero e respeitar o limite monetário.');
        }

        $percentageUnits = self::brazilianPercentageToUnits(
            (string) ($data['percentual_comissao'] ?? $data['percentual_premio'] ?? '')
        );
        if ($percentageUnits <= 0 || $percentageUnits > 10000) {
            throw new InvalidArgumentException('O percentual deve ser maior que zero e no máximo 100%.');
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
        $normalized = str_replace(["\u{00A0}", ' ', 'R$', 'r$'], '', trim($value));
        if ($normalized === '') {
            throw new InvalidArgumentException('Informe o valor da meta.');
        }
        if (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', str_replace('.', '', $normalized));
        }

        return self::decimalToScaledInteger($normalized, 2, 'Valor monetário inválido.');
    }

    public static function brazilianPercentageToUnits(string $value): int
    {
        $normalized = str_replace(["\u{00A0}", ' ', '%'], '', trim($value));
        if ($normalized === '') {
            throw new InvalidArgumentException('Informe o percentual da comissão.');
        }
        if (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', str_replace('.', '', $normalized));
        }

        return self::decimalToScaledInteger($normalized, 2, 'Percentual inválido.');
    }

    public static function prizeCents(int $realizedCents, int $percentageUnits): int
    {
        if ($realizedCents < 0 || $percentageUnits < 0 || $percentageUnits > 10000) {
            throw new InvalidArgumentException('Valores inválidos para cálculo da comissão.');
        }
        if ($realizedCents !== 0 && $percentageUnits > intdiv(PHP_INT_MAX - 5000, $realizedCents)) {
            throw new InvalidArgumentException('Valor excede o limite de cálculo da comissão.');
        }

        return intdiv(($realizedCents * $percentageUnits) + 5000, 10000);
    }

    /** @return array{qualified:bool,prize_cents:int} */
    public static function goalOutcome(
        int $realizedCents,
        int $goalCents,
        int $percentageUnits,
        bool $configured = true
    ): array {
        if ($realizedCents < 0 || $goalCents < 0) {
            throw new InvalidArgumentException('Valores inválidos para apuração da meta.');
        }
        $qualified = $configured && $goalCents > 0 && $realizedCents >= $goalCents;

        return [
            'qualified' => $qualified,
            'prize_cents' => $qualified ? self::prizeCents($realizedCents, $percentageUnits) : 0,
        ];
    }

    private function competence(?string $value): DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return new DateTimeImmutable('first day of this month 00:00:00');
        }
        if (!preg_match('/^(\d{4})-(\d{2})(?:-01)?$/', $value, $matches)) {
            throw new InvalidArgumentException('Competência mensal inválida.');
        }
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Competência mensal inválida.');
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
    }

    private static function databaseMoneyToCents(string $value): int
    {
        return self::decimalToScaledInteger($value, 2, 'Valor monetário armazenado inválido.');
    }

    private static function databasePercentageToUnits(string $value): int
    {
        return self::decimalToScaledInteger($value, 2, 'Percentual armazenado inválido.');
    }

    private static function decimalToScaledInteger(string $value, int $scale, string $message): int
    {
        if (!preg_match('/^\d+(?:\.\d{1,' . $scale . '})?$/', $value)) {
            throw new InvalidArgumentException($message);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $factor = 10 ** $scale;
        if (strlen($whole) > 16 || (int) $whole > intdiv(PHP_INT_MAX - ($factor - 1), $factor)) {
            throw new InvalidArgumentException($message);
        }

        return ((int) $whole * $factor) + (int) str_pad($fraction, $scale, '0');
    }

    private static function centsToDecimal(int $cents): string
    {
        $absolute = abs($cents);
        return ($cents < 0 ? '-' : '')
            . intdiv($absolute, 100) . '.'
            . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function percentageUnitsToDecimal(int $units): string
    {
        return intdiv($units, 100) . '.' . str_pad((string) ($units % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function progressPercentage(int $realizedCents, int $goalCents): string
    {
        if ($goalCents <= 0) {
            return '0.00';
        }
        if ($realizedCents > intdiv(PHP_INT_MAX - intdiv($goalCents, 2), 10000)) {
            throw new InvalidArgumentException('Valor excede o limite de cálculo do progresso.');
        }
        $hundredths = intdiv(($realizedCents * 10000) + intdiv($goalCents, 2), $goalCents);

        return self::percentageUnitsToDecimal($hundredths);
    }
}
