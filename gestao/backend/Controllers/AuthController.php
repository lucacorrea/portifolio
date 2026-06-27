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

        $result = Auth::attempt(
            (string)$request->post('email', ''),
            (string)$request->post('senha', '')
        );

        if (empty($result['success'])) {
            Response::json(['success' => false, 'message' => (string)$result['message']], 401);
        }

        Response::json([
            'success' => true,
            'message' => (string)$result['message'],
            'requires_selection' => (bool)($result['requires_selection'] ?? false),
            'companies' => $result['companies'] ?? [],
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        Response::json(['success' => true]);
    }
}
