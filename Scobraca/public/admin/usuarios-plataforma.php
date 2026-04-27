<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();
$pageTitle = 'Usuários da plataforma';
$pageDescription = 'Administradores internos do SaaS.';
$usuarios = db()->query("SELECT * FROM usuarios WHERE tipo = 'platform_admin' ORDER BY id DESC")->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($pageTitle) ?></title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body>
<div class="layout">
<?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
<main class="content">
<?php require APP_PATH . '/Includes/topbar.php'; ?>
<section class="card"><h2>Administradores</h2><table><thead><tr><th>Nome</th><th>E-mail</th><th>Último login</th></tr></thead><tbody>
<?php foreach ($usuarios as $u): ?><tr><td><?= e($u['nome']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['ultimo_login']) ?></td></tr><?php endforeach; ?>
</tbody></table></section>
</main>
</div>
</body>
</html>
