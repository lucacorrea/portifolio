<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): ?Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect('/login');
        }

        return null;
    }
}

