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

        $alertDays = (int)(
            $settings['alerta_validade_dias']
            ?? $settings['expiration_alert_days']
            ?? 7
        );

        if ($alertDays <= 0) {
            $alertDays = 7;
        }

        return [
            'today' => $this->dashboard->getTodaySummary($empresaId),
            'month' => $this->dashboard->getMonthSummary($empresaId),
            'clientAccounts' => $this->dashboard->getClientAccountsSummary($empresaId),
            'paymentMethods' => $this->dashboard->getPaymentMethodsToday($empresaId),
            'salesEvolution' => $this->dashboard->getSalesEvolution($empresaId, 7),
            'latestSales' => $this->dashboard->getLatestSales($empresaId, 5),
            'topProducts' => $this->dashboard->getFeaturedProducts($empresaId, 5),
            'lowStock' => $this->dashboard->getLowStockProducts($empresaId, 5),
            'expiringProducts' => $this->dashboard->getExpiringProducts($empresaId, $alertDays, 5),
            'expiredProducts' => $this->dashboard->getExpiredProducts($empresaId, 5),
            'settings' => [
                'alertDays' => $alertDays,
            ],
        ];
    }
}