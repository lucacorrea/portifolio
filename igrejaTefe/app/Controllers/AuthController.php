<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

final class AuthController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function login(): Response
    {
        return Response::html(View::render('auth/login', [
            'title' => 'Entrar',
            'error' => Session::pullFlash('auth_error'),
            'old' => Session::pullFlash('old_login', []),
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
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = $this->auth->attempt(
            $email,
            $password,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        );

        if ($user === null) {
            Session::flash('auth_error', 'Email ou senha inválidos.');
            Session::flash('old_login', [
                'email' => $email,
            ]);

            return Response::redirect(url('/login'));
        }

        $this->auth->login($user);

        return Response::redirect(url('/dashboard'));
    }

    public function logout(): Response
    {
        $this->auth->logout();

        return Response::redirect(url('/login'));
    }

    public function me(): Response
    {
        return Response::json([
            'id' => Session::get('user_id'),
            'igreja_id' => Session::get('igreja_id'),
            'nome' => Session::get('user_name'),
            'email' => Session::get('user_email'),
            'papel' => Session::get('user_role'),
        ]);
    }

    public function storeRegister(): Response
    {
        return Response::html('Registro inicial será implementado na fase de autenticação.', 501);
    }
}
