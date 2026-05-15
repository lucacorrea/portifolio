<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class HomeController
{
    public function index(): Response
    {
        return Response::html(View::render('home/index', [
            'title' => 'Base do MVP',
        ]));
    }
}

