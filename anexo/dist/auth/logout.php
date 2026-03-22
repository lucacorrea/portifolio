<?php
declare(strict_types=1);
session_start();

// Limpa todas as variáveis da sessão
$_SESSION = [];

// Se desejar, destrói completamente a sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redireciona para a página inicial (login)
header('Location: ../../index.php');
exit;

?>
