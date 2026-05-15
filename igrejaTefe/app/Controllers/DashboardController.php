<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\DashboardFinanceiroService;
use Throwable;

final class DashboardController
{
    public function index(): Response
    {
        try {
            $dashboard = (new DashboardFinanceiroService())->build((int) Session::get('igreja_id', 0));
        } catch (Throwable) {
            $dashboard = DashboardFinanceiroService::emptyDashboard(
                'Não foi possível carregar os dados financeiros do dashboard.'
            );
        }

        return Response::html(View::render('dashboard/index', [
            'title' => 'Dashboard',
            'dashboard' => $dashboard,
        ]));
    }
}
