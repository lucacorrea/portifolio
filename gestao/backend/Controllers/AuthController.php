<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\Csrf;

final class AuthController
{
    public function login(Request $request): void
    {
        if (!Csrf::validate((string)$request->post('csrf_token', ''))) {
            Response::json(['success' => false, 'message' => 'Sessão expirada.'], 419);
        }

        [$ok, $message, $user] = Auth::attempt(
            (string)$request->post('email', ''),
            (string)$request->post('senha', '')
        );

        if (!$ok || !$user) {
            Response::json(['success' => false, 'message' => $message], 401);
        }

        Auth::login($user);

        Response::json(['success' => true, 'message' => $message]);
    }

    public function logout(): void
    {
        Auth::logout();
        Response::json(['success' => true]);
    }
}
