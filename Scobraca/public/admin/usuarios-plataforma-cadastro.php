<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Cadastrar administrador';
$pageDescription = 'Crie um usuário interno para administrar a plataforma.';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Novo administrador</h2>
                    <p class="muted">Conceda acesso administrativo apenas para usuários internos autorizados.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/admin/usuarios-plataforma.php')) ?>">Voltar para listagem</a>
            </div>
            <form method="post" action="<?= e(public_url('/actions/admin/salvar_usuario_plataforma.php')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label>Nome<input name="nome" required placeholder="Nome do administrador"></label>
                <label>E-mail<input type="email" name="email" required placeholder="admin@fluxpay.com.br"></label>
                <label>CPF ou CNPJ<input name="documento" required inputmode="numeric" placeholder="CPF ou CNPJ para login"></label>
                <label>Senha inicial<input type="password" name="senha" required minlength="6" autocomplete="new-password"></label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar administrador</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
