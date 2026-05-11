<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/login.php');
}

verify_csrf();

$login = trim($_POST['email'] ?? '');
$senha = (string) ($_POST['senha'] ?? '');

if ($login === '' || $senha === '') {
    flash('error', 'Informe e-mail, CPF ou CNPJ e senha.');
    redirect('/admin/login.php');
}

if (!attempt_login($login, $senha, ['platform_admin'])) {
    flash('error', 'Credenciais administrativas inválidas ou usuário inativo.');
    redirect('/admin/login.php');
}

redirect('/admin/dashboard.php');
