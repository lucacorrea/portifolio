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

    public function rangeFromPayload(array $payload): array
    {
        $period = (string)($payload['period'] ?? $payload['periodo'] ?? 'dia');
        $today = new \DateTimeImmutable('today');

        if ($period === 'semana') {
            return [$today->modify('monday this week')->format('Y-m-d'), $today->format('Y-m-d')];
        }

        if ($period === 'mes') {
            return [$today->modify('first day of this month')->format('Y-m-d'), $today->format('Y-m-d')];
        }

        if ($period === 'periodo') {
            $start = (string)($payload['start'] ?? $payload['inicio'] ?? '');
            $end = (string)($payload['end'] ?? $payload['fim'] ?? '');

            if (!Validator::date($start) || !Validator::date($end) || $start === '' || $end === '') {
                throw new InvalidArgumentException('Período inválido.');
            }

            return [$start, $end];
        }

        return [$today->format('Y-m-d'), $today->format('Y-m-d')];
    }

    public function summary(int $empresaId, array $payload): array
    {
        [$start, $end] = $this->rangeFromPayload($payload);

        return [
            'period' => ['start' => $start, 'end' => $end],
            'summary' => $this->reports->summary($empresaId, $start, $end),
            'paymentMethods' => $this->reports->paymentMethods($empresaId, $start, $end),
        ];
    }

    public function sales(int $empresaId, array $payload): array
    {
        [$start, $end] = $this->rangeFromPayload($payload);

        return $this->reports->sales($empresaId, $start, $end);
    }

    public function products(int $empresaId, array $payload): array
    {
        [$start, $end] = $this->rangeFromPayload($payload);

        return [
            'sold' => $this->reports->productsSold($empresaId, $start, $end),
            'lowStock' => $this->reports->lowStock($empresaId),
        ];
    }

    public function validity(int $empresaId): array
    {
        $settings = $this->settings->getAll($empresaId);
        $days = (int)($settings['alerta_validade_dias'] ?? 7);

        return $this->reports->expiring($empresaId, $days);
    }
}
