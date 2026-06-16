<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\ReportRepository;
use App\Repositories\SettingRepository;
use InvalidArgumentException;

final class ReportService
{
    private ReportRepository $reports;
    private SettingRepository $settings;

    public function __construct(?ReportRepository $reports = null, ?SettingRepository $settings = null)
    {
        $this->reports = $reports ?? new ReportRepository();
        $this->settings = $settings ?? new SettingRepository();
    }

    public function build(int $empresaId, array $payload): array
    {
        $filters = $this->normalizeFilters($payload);
        $summary = $this->reports->summary($empresaId, $filters);
        $cost = $this->reports->estimatedCost($empresaId, $filters);
        $paymentMethods = $this->reports->paymentMethods($empresaId, $filters);
        $productsSold = $this->reports->productsSold($empresaId, $filters);
        $dailySales = $this->reports->dailySales($empresaId, $filters);

        $estimatedCost = (float)($cost['estimated_cost'] ?? 0);
        $totalSales = (float)$summary['total_sales'];
        $estimatedProfit = max(0.0, $totalSales - $estimatedCost);

        return [
            'filters' => $filters,
            'summary' => $summary + [
                'estimated_cost' => $estimatedCost,
                'estimated_profit' => $estimatedProfit,
                'estimated_margin' => $totalSales > 0 ? ($estimatedProfit / $totalSales) * 100 : 0,
            ],
            'paymentMethods' => $paymentMethods,
            'productsSold' => $productsSold,
            'operatorSales' => $this->reports->operatorSales($empresaId, $filters),
            'dailySales' => $dailySales,
            'clientDebtSummary' => $this->reports->clientDebtSummary($empresaId, $filters),
            'clientDebtRows' => $this->reports->clientDebtRows($empresaId, $filters),
            'sales' => $this->reports->salesList($empresaId, $filters),
            'options' => $this->reports->filterOptions($empresaId),
            'chartData' => $this->chartData($paymentMethods, $productsSold, $dailySales),
        ];
    }

    public function summary(int $empresaId, array $payload): array
    {
        $report = $this->build($empresaId, $payload);

        return [
            'period' => ['start' => substr($report['filters']['start'], 0, 10), 'end' => substr($report['filters']['end'], 0, 10)],
            'summary' => $report['summary'],
            'paymentMethods' => $report['paymentMethods'],
        ];
    }

    public function sales(int $empresaId, array $payload): array
    {
        return $this->build($empresaId, $payload)['sales'];
    }

    public function products(int $empresaId, array $payload): array
    {
        return [
            'sold' => $this->build($empresaId, $payload)['productsSold'],
            'lowStock' => $this->reports->lowStock($empresaId),
        ];
    }

    public function validity(int $empresaId): array
    {
        $settings = $this->settings->getAll($empresaId);
        $days = (int)($settings['alerta_validade_dias'] ?? 7);

        return $this->reports->expiring($empresaId, $days);
    }

    public function normalizeFilters(array $payload): array
    {
        $periodo = (string)($payload['periodo'] ?? $payload['period'] ?? 'hoje');
        $inicio = trim((string)($payload['inicio'] ?? $payload['start'] ?? ''));
        $fim = trim((string)($payload['fim'] ?? $payload['end'] ?? ''));
        $range = $this->resolveReportPeriod($periodo, $inicio !== '' ? $inicio : null, $fim !== '' ? $fim : null);

        return [
            'periodo' => $range['periodo'],
            'inicio' => substr($range['start'], 0, 10),
            'fim' => substr($range['end'], 0, 10),
            'start' => $range['start'],
            'end' => $range['end'],
            'forma_pagamento' => $this->allowedString((string)($payload['forma_pagamento'] ?? ''), ['', 'pix', 'dinheiro', 'credito', 'debito', 'conta_cliente', 'misto']),
            'status' => $this->allowedString((string)($payload['status'] ?? ''), ['', 'finalizada', 'pendente', 'cancelada', 'em_aberto']),
            'usuario_id' => $this->positiveInt($payload['usuario_id'] ?? 0),
            'cliente_id' => $this->positiveInt($payload['cliente_id'] ?? 0),
            'produto_id' => $this->positiveInt($payload['produto_id'] ?? 0),
        ];
    }

    public function resolveReportPeriod(string $periodo, ?string $inicio, ?string $fim): array
    {
        $today = new \DateTimeImmutable('today');
        $periodo = in_array($periodo, ['hoje', 'ontem', '7dias', 'mes_atual', 'mes_passado', 'personalizado'], true) ? $periodo : 'hoje';

        if ($periodo === 'ontem') {
            $start = $today->modify('-1 day');
            $end = $start;
        } elseif ($periodo === '7dias') {
            $start = $today->modify('-6 days');
            $end = $today;
        } elseif ($periodo === 'mes_atual') {
            $start = $today->modify('first day of this month');
            $end = $today->modify('last day of this month');
        } elseif ($periodo === 'mes_passado') {
            $previous = $today->modify('first day of previous month');
            $start = $previous;
            $end = $previous->modify('last day of this month');
        } elseif ($periodo === 'personalizado') {
            if (!Validator::date($inicio) || !Validator::date($fim) || $inicio === null || $fim === null) {
                throw new InvalidArgumentException('Período personalizado inválido.');
            }

            $start = new \DateTimeImmutable($inicio);
            $end = new \DateTimeImmutable($fim);

            if ($start > $end) {
                throw new InvalidArgumentException('A data inicial não pode ser maior que a final.');
            }
        } else {
            $start = $today;
            $end = $today;
            $periodo = 'hoje';
        }

        return [
            'periodo' => $periodo,
            'start' => $start->format('Y-m-d') . ' 00:00:00',
            'end' => $end->format('Y-m-d') . ' 23:59:59',
        ];
    }

    private function chartData(array $paymentMethods, array $productsSold, array $dailySales): array
    {
        return [
            'payments' => [
                'labels' => array_map(fn (array $row): string => $this->paymentLabel((string)$row['metodo']), $paymentMethods),
                'values' => array_map(fn (array $row): float => (float)$row['total_value'], $paymentMethods),
            ],
            'products' => [
                'labels' => array_map(fn (array $row): string => (string)$row['product_name'], $productsSold),
                'values' => array_map(fn (array $row): float => (float)$row['quantity_sold'], $productsSold),
            ],
            'daily' => [
                'labels' => array_map(fn (array $row): string => date('d/m', strtotime((string)$row['sale_date'])), $dailySales),
                'values' => array_map(fn (array $row): float => (float)$row['total_sales'], $dailySales),
            ],
        ];
    }

    private function paymentLabel(string $method): string
    {
        return [
            'pix' => 'PIX',
            'dinheiro' => 'Dinheiro',
            'credito' => 'Crédito',
            'debito' => 'Débito',
            'conta_cliente' => 'Conta do cliente',
            'misto' => 'Misto',
        ][$method] ?? 'Não informado';
    }

    private function allowedString(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function positiveInt(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? 0 : (int)$id;
    }
}
