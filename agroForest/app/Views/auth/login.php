<?php
$tituloPagina = 'Login';
$cssPagina = 'assets/css/auth/login.css';
$erro = flash_get('error');
$sucesso = flash_get('success');
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="simple-page">
    <div class="simple-card login-card">
        <h1>Entrar no sistema</h1>
        <?php if ($erro): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>
        <form action="<?= route_url('auth', 'login') ?>" method="POST" autocomplete="on">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token_value()) ?>">
            <label>
                <span>Nome ou e-mail</span>
                <input type="text" name="identificacao" placeholder="Seu nome ou e-mail" required autofocus autocomplete="username">
            </label>
            <label>
                <span>Senha</span>
                <input type="password" name="senha" placeholder="Sua senha" required>
            </label>
            <button type="submit">Entrar</button>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
