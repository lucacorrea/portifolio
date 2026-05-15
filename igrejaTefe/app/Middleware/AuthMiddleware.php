<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): ?Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('/login'));
        }

        $lifetime = (int) Config::get('security.session.lifetime', 7200);
        $lastActivity = (int) Session::get('last_activity_at', time());

        if ($lifetime > 0 && (time() - $lastActivity) > $lifetime) {
            Session::destroy();

            return Response::redirect(url('/login'));
        }

        Session::put('last_activity_at', time());

        return null;
    }
}
