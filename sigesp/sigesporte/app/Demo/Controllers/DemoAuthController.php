<?php
declare(strict_types=1);

namespace Sigesp\Demo\Controllers;

use Sigesp\Core\{Request, Response, View};

final class DemoAuthController
{
    public function loginForm(Request $request): Response
    {
        return new Response(View::render('auth/login', [
            'title' => 'Acessar demonstração',
            'demoMode' => true,
            'demoUser' => ['nome' => 'Marcos Oliveira', 'perfil' => 'Administrador do Sistema'],
        ], 'layouts/auth'));
    }

    public function login(Request $request): Response
    {
        return Response::redirect('/dashboard?demo=1');
    }

    public function logout(Request $request): Response
    {
        return Response::redirect('/login');
    }

    public function forgot(Request $request): Response
    {
        return new Response(View::render('auth/forgot', ['title' => 'Recuperar acesso', 'demoMode' => true], 'layouts/auth'));
    }
}
