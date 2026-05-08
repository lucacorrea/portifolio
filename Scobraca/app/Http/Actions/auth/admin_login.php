<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/login.php');
}

verify_csrf();

$email = trim($_POST['email'] ?? '');
$senha = (string) ($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    flash('error', 'Informe e-mail e senha.');
    redirect('/admin/login.php');
}

if (!attempt_login($email, $senha, ['platform_admin'])) {
    flash('error', 'Credenciais administrativas inválidas ou usuário inativo.');
    redirect('/admin/login.php');
}

redirect('/admin/dashboard.php');
