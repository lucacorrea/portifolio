<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| LIMPA TODA A SESSÃO
|--------------------------------------------------------------------------
*/
$_SESSION = [];

/*
|--------------------------------------------------------------------------
| REMOVE O COOKIE DA SESSÃO, SE EXISTIR
|--------------------------------------------------------------------------
*/
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

/*
|--------------------------------------------------------------------------
| DESTRÓI A SESSÃO
|--------------------------------------------------------------------------
*/
session_destroy();

/*
|--------------------------------------------------------------------------
| REDIRECIONA PARA O LOGIN
|--------------------------------------------------------------------------
*/
echo "<script>
    alert('Sessão encerrada com sucesso.');
    window.location.href = '../../index.html';
</script>";
exit;

?>
