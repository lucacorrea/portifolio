<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class AuthController
{
    public function login(): Response
    {
        return Response::html(View::render('auth/login', [
            'title' => 'Entrar',
        ], 'layouts/auth'));
    }

    public function register(): Response
    {
        return Response::html(View::render('auth/register', [
            'title' => 'Registro inicial',
        ], 'layouts/auth'));
    }

    public function attemptLogin(): Response
    {
        return Response::html('Login será implementado na fase de autenticação.', 501);
    }

    public function storeRegister(): Response
    {
        return Response::html('Registro inicial será implementado na fase de autenticação.', 501);
    }
}
