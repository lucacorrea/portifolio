<?php

declare(strict_types=1);

$app = require __DIR__ . '/bootstrap.php';
$application = $app['application'];
$session = $application->session();
$session->start();
$authorization = $application->authorization();
$authorization->requireLogin();
$authorization->requirePermission('os.emitir_comprovante');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($id)) {
    http_response_code(404);
    exit;
}

$withValues = (string) ($_GET['valores'] ?? '0') === '1' && $authorization->can('os.visualizar_valores');
$orderService = $application->serviceOrderManagement();
$order = $orderService->getOrder($id);
if ($order->status() !== 'finalizada') {
    http_response_code(403);
    exit('Comprovante disponivel somente para OS finalizada.');
}
$items = $orderService->getOrderItems($id);
$team = $orderService->getOrderTeamMembers($id);
$company = $application->companySettings()->get();

function receipt_h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function receipt_money(string $value): string { return 'R$ ' . number_format((float) $value, 2, ',', '.'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Comprovante <?= receipt_h($order->displayNumber()) ?></title>
<style>
@page { size: 80mm auto; margin: 3mm; }
* { box-sizing: border-box; }
body { width: 74mm; margin: 0; font-family: Arial, sans-serif; font-size: 11px; color: #111; }
.center { text-align: center; }
.line { border-top: 1px dashed #111; margin: 6px 0; }
h1, h2 { font-size: 12px; margin: 4px 0; text-transform: uppercase; }
p { margin: 2px 0; }
.item { margin: 5px 0; }
.total { font-weight: 700; }
@media print { .no-print { display: none; } }
</style>
</head>
<body>
<div class="center">
    <h1><?= receipt_h($company['nome_fantasia'] ?? $company['razao_social'] ?? 'Empresa nao configurada') ?></h1>
    <p><?= receipt_h($company['razao_social'] ?? '') ?></p>
    <p><?= receipt_h($company['documento'] ?? '') ?></p>
    <p><?= receipt_h($company['telefone'] ?? '') ?></p>
    <p><?= receipt_h($company['endereco'] ?? '') ?></p>
    <div class="line"></div>
    <h2>DOCUMENTO NAO FISCAL</h2>
    <p><?= receipt_h($order->displayNumber()) ?></p>
    <p><?= date('d/m/Y H:i') ?></p>
</div>
<div class="line"></div>
<h2>Atendimento</h2>
<p>Cliente: <?= receipt_h($order->clientName()) ?></p>
<p>Local: <?= receipt_h($order->equipmentLocation() ?: $order->clientAddress() ?: 'Nao informado') ?></p>
<p>Data: <?= receipt_h($order->displaySchedule()) ?></p>
<?php foreach ($team as $member): ?><p><?= receipt_h($member->displayLine()) ?></p><?php endforeach; ?>
<div class="line"></div>
<h2>Servicos</h2>
<?php foreach ($items as $item): if ($item->type() !== 'servico') continue; ?>
<div class="item"><p><?= receipt_h($item->description()) ?></p><?php if ($withValues): ?><p><?= receipt_h($item->quantity()) ?> x <?= receipt_money($item->unitPrice()) ?></p><p>Subtotal: <?= receipt_money($item->subtotal()) ?></p><?php else: ?><p>Quantidade: <?= receipt_h($item->quantity()) ?></p><?php endif; ?></div>
<?php endforeach; ?>
<div class="line"></div>
<h2>Pecas utilizadas</h2>
<?php foreach ($items as $item): if ($item->type() !== 'produto') continue; ?>
<div class="item"><p><?= receipt_h($item->description()) ?></p><?php if ($withValues): ?><p><?= receipt_h($item->quantity()) ?> x <?= receipt_money($item->unitPrice()) ?></p><p>Subtotal: <?= receipt_money($item->subtotal()) ?></p><?php else: ?><p>Quantidade: <?= receipt_h($item->quantity()) ?></p><?php endif; ?></div>
<?php endforeach; ?>
<?php if ($withValues): ?>
<div class="line"></div>
<h2>Resumo financeiro</h2>
<p>Subtotal servicos: <?= receipt_money($order->servicesSubtotal()) ?></p>
<p>Subtotal pecas: <?= receipt_money($order->productsSubtotal()) ?></p>
<p>Outros itens: <?= receipt_money($order->othersSubtotal()) ?></p>
<p>Desconto: <?= receipt_money($order->discount()) ?></p>
<p>Acrescimo: <?= receipt_money($order->increase()) ?></p>
<p class="total">Total: <?= receipt_money($order->total()) ?></p>
<?php endif; ?>
<div class="line"></div>
<div class="center">
    <p>Documento nao fiscal</p>
    <br>
    <p>Desenvolvido por</p>
    <p>JL Solucoes Tecnologicas</p>
</div>
<p class="center no-print"><button onclick="window.print()">Imprimir</button></p>
</body>
</html>
