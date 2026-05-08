<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

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
    <title>Login administrativo - FluxPay</title>
    <link rel="stylesheet" href="<?= e(public_url('/assets/css/app.css')) ?>">
</head>
<body class="login-body login-body-admin">
    <main class="login-card">
        <div class="login-brand">FluxPay <span>Admin</span></div>
        <h1>Acesso administrativo</h1>
        <p>Área reservada para administradores internos da plataforma.</p>

        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <form method="post" action="<?= e(public_url('/actions/auth/admin_login.php')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <label>
                E-mail
                <input type="email" name="email" required autocomplete="email" placeholder="admin@empresa.com">
            </label>
            <label>
                Senha
                <input type="password" name="senha" required autocomplete="current-password" placeholder="Sua senha">
            </label>
            <button type="submit" class="btn btn-primary">Entrar no admin</button>
        </form>

        <div class="login-links">
            <a href="<?= e(public_url('/login.php')) ?>">Entrar como empresa</a>
        </div>
    </main>
</body>
</html>
