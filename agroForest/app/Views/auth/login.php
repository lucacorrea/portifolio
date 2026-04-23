<?php
$tituloPagina = 'Login';
$cssPagina = 'assets/css/auth/login.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="simple-page">
    <div class="simple-card login-card">
        <h1>Entrar no sistema</h1>
        <form>
            <input type="email" placeholder="E-mail">
            <input type="password" placeholder="Senha">
            <button type="submit">Entrar</button>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
