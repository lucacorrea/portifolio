<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* limpa todos os dados da sessão */
$_SESSION = [];

/* apaga o cookie da sessão */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => 'Lax',
        ]
    );
}

/* encerra a sessão */
session_unset();
session_destroy();

/* por segurança, invalida o id atual */
if (function_exists('session_write_close')) {
    @session_write_close();
}

/* redireciona para o login */
header('Location: index.php');
exit;