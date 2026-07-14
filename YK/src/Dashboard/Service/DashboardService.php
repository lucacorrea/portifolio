<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Repository\DashboardRepository;

final class DashboardService
{
    public function __construct(private readonly DashboardRepository $dashboard)
    {
    }

    /** @return array<string,mixed> */
    public function overview(
        bool $showOperational,
        bool $showFinancial,
        bool $showWeeklyOrders,
        bool $showLatestOrders,
        bool $showOrderValues
    ): array
    {
        return [
            'operational' => $showOperational ? $this->dashboard->operationalIndicators() : null,
            'financial' => $showFinancial ? $this->dashboard->financialIndicators() : null,
            'weekly_orders' => $showWeeklyOrders ? $this->dashboard->weeklyOrders() : [],
            'latest_orders' => $showLatestOrders ? $this->dashboard->latestOrders($showOrderValues) : [],
            'show_order_values' => $showLatestOrders && $showOrderValues,
        ];
    }
}
