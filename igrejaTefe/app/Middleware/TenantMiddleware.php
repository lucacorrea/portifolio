<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

final class TenantMiddleware implements MiddlewareInterface
{
    public function handle(): ?Response
    {
        if (!Session::get('igreja_id')) {
            return Response::html('Igreja não identificada na sessão.', 403);
        }

        return null;
    }
}

