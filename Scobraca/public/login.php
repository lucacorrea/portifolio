<?php
require_once dirname(__DIR__) . '/bootstrap/app.php';

if (!empty($_SESSION['usuario'])) {
    if (($_SESSION['usuario']['tipo'] ?? '') === 'platform_admin') {
        redirect('/admin/dashboard.php');
    }
    redirect('/app/dashboard.php');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login da empresa - FluxPay</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="login-body">
    <main class="login-card">
        <div class="login-brand">FluxPay <span>Empresa</span></div>
        <h1>Acesso da empresa</h1>
        <p>Entre com o usuário cadastrado para a sua empresa.</p>

        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <form method="post" action="/actions/auth/login.php" class="form-stack">
            <?= csrf_field() ?>
            <label>
                E-mail
                <input type="email" name="email" required autocomplete="email" placeholder="seu@email.com">
            </label>
            <label>
                Senha
                <input type="password" name="senha" required autocomplete="current-password" placeholder="Sua senha">
            </label>
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>

        <div class="login-links">
            <a href="/admin/login.php">Acesso administrativo</a>
        </div>
    </main>
</body>
</html>
