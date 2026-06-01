<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;
use App\Repositories\SettingRepository;

final class DashboardService
{
    private DashboardRepository $dashboard;
    private SettingRepository $settings;

    public function __construct(?DashboardRepository $dashboard = null, ?SettingRepository $settings = null)
    {
        $this->dashboard = $dashboard ?? new DashboardRepository();
        $this->settings = $settings ?? new SettingRepository();
    }

    public function summary(int $empresaId): array
    {
        $settings = $this->settings->getAll($empresaId);
        $alertDays = (int)($settings['alerta_validade_dias'] ?? 7);

        return [
            'summary' => $this->dashboard->getTodaySummary($empresaId),
            'paymentMethods' => $this->dashboard->getPaymentMethodsToday($empresaId),
            'latestSales' => $this->dashboard->getLatestSales($empresaId, 5),
            'topProducts' => $this->dashboard->getFeaturedProducts($empresaId, 5),
            'lowStock' => $this->dashboard->getLowStockProducts($empresaId, 5),
            'expiringProducts' => $this->dashboard->getExpiringProducts($empresaId, $alertDays),
        ];
    }
}
