<?php

require_once __DIR__ . '/../app/bootstrap.php';

use FluxEmpresa\Core\Auth;
use FluxEmpresa\Core\Csrf;

if (Auth::isLogged()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $error = 'Autenticação ainda será implementada pelo Codex usando PDO, password_verify e logs.';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | FluxEmpresa</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="brand">
            <div class="brand-mark">FE</div>
            <div>
                <h1>FluxEmpresa</h1>
                <p>Gestão de orçamentos, execução e prestação de contas.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= Csrf::field() ?>
            <label>Usuário</label>
            <input type="text" name="usuario" required autofocus>

            <label>Senha</label>
            <input type="password" name="senha" required>

            <button type="submit">Entrar no sistema</button>
        </form>
    </main>
</body>
</html>
