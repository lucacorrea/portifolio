<?php
declare(strict_types=1);

require_once APP_PATH . '/Modules/Auth/Models/Admin.php';

final class LoginController
{
    public function show(): void
    {
        if (!empty($_SESSION['auth']['guard']) && $_SESSION['auth']['guard'] === 'admin') {
            redirect('/admin/dashboard');
        }

        View::render('Auth/Views/login', [
            'flashError'   => flash_get('error'),
            'flashSuccess' => flash_get('success'),
            'oldEmail'     => $_SESSION['old']['email'] ?? '',
        ]);

        unset($_SESSION['old']);
    }

    public function authenticate(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');

        $_SESSION['old']['email'] = $email;

        if ($email === '' || $senha === '') {
            flash_set('error', 'Preencha e-mail e senha.');
            redirect('/admin/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Informe um e-mail válido.');
            redirect('/admin/login');
        }

        try {
            $admin = Admin::findByEmail($email);
        } catch (Throwable $e) {
            flash_set('error', 'Não foi possível conectar ao banco de dados.');
            redirect('/admin/login');
        }

        if (!$admin || !password_verify($senha, (string)$admin['senha_hash'])) {
            flash_set('error', 'E-mail ou senha inválidos.');
            redirect('/admin/login');
        }

        if (($admin['status'] ?? 'inativo') !== 'ativo') {
            flash_set('error', 'Seu acesso está inativo.');
            redirect('/admin/login');
        }

        Admin::updateLastLogin((int)$admin['id']);

        session_regenerate_id(true);

        $_SESSION['auth'] = [
            'id'    => (int)$admin['id'],
            'nome'  => (string)$admin['nome'],
            'email' => (string)$admin['email'],
            'nivel' => (string)$admin['nivel'],
            'guard' => 'admin',
        ];

        unset($_SESSION['old']);

        redirect('/admin/dashboard');
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
        session_start();
        session_regenerate_id(true);

        flash_set('success', 'Logout realizado com sucesso.');
        redirect('/admin/login');
    }
}
