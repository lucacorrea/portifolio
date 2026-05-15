<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;

final class HomeController
{
    public function index(): Response
    {
        return Response::redirect(url('/login'));
    }
}
