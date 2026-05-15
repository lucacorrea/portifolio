<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class DashboardController
{
    public function index(): Response
    {
        return Response::html(View::render('dashboard/index', [
            'title' => 'Dashboard',
        ]));
    }
}

