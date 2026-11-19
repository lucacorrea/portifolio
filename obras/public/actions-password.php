<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/auth.php';
$u = user();
if (!$u) redirect_to('acesso.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_to('minha-senha.php');
try {
    verify_csrf();
    change_my_password($u, post_string('old', 256), post_string('new', 256), post_string('confirm', 256));
    $_SESSION['profile_notice'] = 'Senha atualizada com sucesso.';
    redirect_to('index.php');
} catch (Throwable $e) {
    error_log('[OBRAS360 password] ' . $e->getMessage());
    $_SESSION['password_error'] = $e instanceof HttpError ? $e->getMessage() : 'Não foi possível atualizar a senha.';
    redirect_to('minha-senha.php');
}
