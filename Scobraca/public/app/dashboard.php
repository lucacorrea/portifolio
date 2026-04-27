<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Dashboard da Empresa';
$pageDescription = 'Painel da empresa locatária do Tático GPS.';
$empresaId = current_empresa_id();
$pdo = db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE empresa_id = :empresa_id');
$stmt->execute([':empresa_id' => $empresaId]);
$totalClientes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM cobrancas WHERE empresa_id = :empresa_id AND status = 'Em aberto'");
$stmt->execute([':empresa_id' => $empresaId]);
$cobrancasAbertas = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(valor_pago), 0) FROM pagamentos WHERE empresa_id = :empresa_id AND MONTH(data_pagamento) = MONTH(CURDATE()) AND YEAR(data_pagamento) = YEAR(CURDATE())");
$stmt->execute([':empresa_id' => $empresaId]);
$totalRecebido = (float) $stmt->fetchColumn();
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($pageTitle) ?></title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body>
<div class="layout">
<?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
<main class="content">
<?php require APP_PATH . '/Includes/topbar.php'; ?>
<?php require APP_PATH . '/Includes/flash.php'; ?>
<section class="grid three">
<article class="card metric"><span>Clientes</span><strong><?= $totalClientes ?></strong></article>
<article class="card metric"><span>Cobranças abertas</span><strong><?= $cobrancasAbertas ?></strong></article>
<article class="card metric"><span>Recebido no mês</span><strong><?= moeda_br($totalRecebido) ?></strong></article>
</section>
<section class="card"><h2>Base SaaS pronta</h2><p>Agora os módulos atuais de clientes, cobranças, pagamentos e WhatsApp devem ser migrados para usar sempre <code>empresa_id</code>.</p></section>
</main>
</div>
</body>
</html>
