<?php
$tituloPagina = 'Acesso negado';
$cssPagina = 'assets/css/auth/login.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="simple-page">
    <div class="simple-card login-card">
        <h1>Acesso negado</h1>
        <p>Seu usuário não tem permissão para acessar esta área.</p>
        <a class="btn-link" href="<?= htmlspecialchars(Auth::homeForNivel(current_user()['nivel'] ?? 'recepcao')) ?>">Voltar ao painel</a>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
