<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('acesso.php'); }
try {
    verify_csrf();
    $login = post_string('login', 255);
    $password = $_POST['password'] ?? '';
    if (!is_string($password) || strlen($password) > 512) throw new HttpError('Credenciais inválidas.');
    if (login_attempt($login, $password)) redirect_to('index.php');
    $_SESSION['login_error'] = 'Usuário/e-mail ou senha inválidos. Se necessário, tente novamente mais tarde.';
} catch (Throwable $e) {
    error_log('[OBRAS360 login] ' . $e->getMessage());
    $_SESSION['login_error'] = $e instanceof HttpError ? $e->getMessage() : 'Não foi possível entrar. Confira a instalação do banco.';
}
redirect_to('acesso.php');
