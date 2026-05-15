<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(): ?Response
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return null;
        }

        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!Session::verifyCsrfToken(is_string($token) ? $token : null)) {
            return Response::html('Token de segurança inválido.', 419);
        }

        return null;
    }
}

