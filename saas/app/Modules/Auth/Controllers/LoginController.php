<?php
declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

final class LoginController
{
    public function index(): string
    {
        return view('auth/login', [
            'title'   => 'Login',
            'error'   => flash_get('error'),
            'success' => flash_get('success'),
        ]);
    }

    public function authenticate(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $senha = trim((string)($_POST['senha'] ?? ''));

        if ($email === 'admin@saas.com' && $senha === '123456') {
            $_SESSION['auth'] = [
                'nome'  => 'Administrador',
                'email' => $email,
            ];

            flash_set('success', 'Login realizado com sucesso.');
            header('Location: ' . url('dashboard'));
            exit;
        }

        flash_set('error', 'E-mail ou senha inválidos.');
        header('Location: ' . url('login'));
        exit;
    }
}
