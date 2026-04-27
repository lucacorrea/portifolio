<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login.php');
}

verify_csrf();

$email = trim($_POST['email'] ?? '');
$senha = (string) ($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    flash('error', 'Informe e-mail e senha.');
    redirect('/login.php');
}

if (!attempt_login($email, $senha)) {
    flash('error', 'Credenciais inválidas ou usuário inativo.');
    redirect('/login.php');
}

$tipo = $_SESSION['usuario']['tipo'] ?? '';

if ($tipo === 'platform_admin') {
    redirect('/admin/dashboard.php');
}

redirect('/app/dashboard.php');
