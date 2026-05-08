<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Cadastrar usuário da empresa';
$pageDescription = 'Crie usuários operacionais para uma empresa locatária.';

$empresaId = (int) ($_GET['empresa_id'] ?? 0);
$stmt = db()->prepare('SELECT id, nome, email FROM empresas WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $empresaId]);
$empresa = $stmt->fetch();

if (!$empresa) {
    flash('error', 'Empresa não encontrada.');
    redirect('/admin/empresas.php');
}
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
                    <h2>Novo usuário da empresa</h2>
                    <p class="muted"><?= e($empresa['nome']) ?> · <?= e($empresa['email']) ?></p>
                </div>
                <a class="btn" href="<?= e(public_url('/admin/empresas.php')) ?>">Voltar para listagem</a>
            </div>

            <form method="post" action="<?= e(public_url('/actions/admin/salvar_usuario_empresa.php')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="empresa_id" value="<?= (int) $empresa['id'] ?>">
                <label>Nome<input name="nome" required placeholder="Nome do usuário"></label>
                <label>E-mail<input type="email" name="email" required placeholder="email@empresa.com"></label>
                <label>Senha inicial<input type="password" name="senha" required autocomplete="new-password"></label>
                <label>Tipo de acesso
                    <select name="tipo">
                        <option value="operador">Operador</option>
                        <option value="empresa_admin">Admin da empresa</option>
                    </select>
                </label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar usuário</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
