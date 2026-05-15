<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Response;
use App\Core\Session;
use App\Models\Igreja;
use Throwable;

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

        $this->hydrateChurchSession();

        Session::put('last_activity_at', time());

        return null;
    }

    private function hydrateChurchSession(): void
    {
        if (Session::get('igreja_nome')) {
            return;
        }

        $igrejaId = (int) Session::get('igreja_id', 0);

        if ($igrejaId <= 0) {
            return;
        }

        try {
            $igreja = (new Igreja())->findActiveSummary($igrejaId);
        } catch (Throwable) {
            return;
        }

        if ($igreja === null) {
            return;
        }

        Session::put('igreja_nome', (string) $igreja['nome']);

        if (!empty($igreja['logo_url'])) {
            Session::put('igreja_logo_url', (string) $igreja['logo_url']);
        }
    }
}
