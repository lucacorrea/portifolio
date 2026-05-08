<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

if (($_SESSION['usuario']['tipo'] ?? '') !== 'empresa_admin') {
    http_response_code(403);
    exit('Somente o administrador da empresa pode criar usuários.');
}

$pageTitle = 'Cadastrar usuário';
$pageDescription = 'Crie um acesso para a equipe da empresa.';
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
    <?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Novo usuário</h2>
                    <p class="muted">Defina o nível de acesso para operadores e administradores da empresa.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/app/usuarios.php')) ?>">Voltar para listagem</a>
            </div>
            <form method="post" action="<?= e(public_url('/actions/app/salvar_usuario.php')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label>Nome<input name="nome" required></label>
                <label>E-mail<input type="email" name="email" required></label>
                <label>Senha inicial<input type="password" name="senha" required autocomplete="new-password"></label>
                <label>Tipo
                    <select name="tipo">
                        <option value="operador">Operador</option>
                        <option value="empresa_admin">Administrador da empresa</option>
                    </select>
                </label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar usuário</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
