<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Assinaturas';
$pageDescription = 'Controle das empresas pagantes, em teste, vencidas ou bloqueadas.';
$assinaturas = db()->query(
    "SELECT a.*, e.nome AS empresa, p.nome AS plano
     FROM assinaturas a
     INNER JOIN empresas e ON e.id = a.empresa_id
     INNER JOIN planos p ON p.id = a.plano_id
     ORDER BY a.data_vencimento ASC"
)->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($pageTitle) ?></title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body>
<div class="layout">
<?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
<main class="content">
<?php require APP_PATH . '/Includes/topbar.php'; ?>
<section class="card">
<h2>Assinaturas cadastradas</h2>
<table>
<thead><tr><th>Empresa</th><th>Plano</th><th>Status</th><th>Valor</th><th>Início</th><th>Vencimento</th></tr></thead>
<tbody>
<?php foreach ($assinaturas as $a): ?>
<tr>
<td><?= e($a['empresa']) ?></td><td><?= e($a['plano']) ?></td><td><span class="badge <?= e($a['status']) ?>"><?= e($a['status']) ?></span></td><td><?= moeda_br((float) $a['valor']) ?></td><td><?= e($a['data_inicio']) ?></td><td><?= e($a['data_vencimento']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$assinaturas): ?><tr><td colspan="6">Nenhuma assinatura cadastrada.</td></tr><?php endif; ?>
</tbody>
</table>
</section>
</main>
</div>
</body>
</html>
