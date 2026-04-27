<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();
$pageTitle = 'Mensagens';
$pageDescription = 'Módulo da empresa locatária. Adaptar o código atual para filtrar por empresa_id.';
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($pageTitle) ?></title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body>
<div class="layout">
<?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
<main class="content">
<?php require APP_PATH . '/Includes/topbar.php'; ?>
<section class="card"><h2>Mensagens</h2><p>Traga o código atual deste módulo para cá e aplique sempre o filtro: <code>WHERE empresa_id = :empresa_id</code>.</p></section>
</main>
</div>
</body>
</html>
