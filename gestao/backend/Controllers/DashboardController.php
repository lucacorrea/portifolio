<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Security\Auth;
use App\Services\DashboardService;

final class DashboardController
{
    private DashboardService $service;

    public function __construct(?DashboardService $service = null)
    {
        $this->service = $service ?? new DashboardService();
    }

    public function summary(): void
    {
        Auth::requireLogin();

        Response::success($this->service->summary((int)Auth::user()['empresa_id']));
    }
}
