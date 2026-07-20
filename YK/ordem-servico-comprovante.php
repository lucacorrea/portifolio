<?php

declare(strict_types=1);

use App\Company\Service\CompanyBranding;

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
$companyName = trim((string) ($company['nome_fantasia'] ?? ''));
if ($companyName === '') {
    $companyName = trim((string) ($company['razao_social'] ?? ''));
}
$companyName = $companyName !== '' ? $companyName : 'Empresa não configurada';
$companyLogo = CompanyBranding::safeLogoUrl($company['logo'] ?? null);

function receipt_h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function receipt_money(string $value): string { return 'R$ ' . number_format((float) $value, 2, ',', '.'); }
function receipt_quantity(string $value): string { return number_format((float) $value, 3, ',', '.'); }

/** @param array<int,object> $items */
function receipt_print_items(array $items, string $type, string $title, bool $withValues): void
{
    $filtered = array_values(array_filter($items, static fn(object $item): bool => $item->type() === $type));
    if ($filtered === []) {
        return;
    }

    echo '<section class="receipt-section"><h2>' . receipt_h($title) . '</h2><div class="item-list">';
    foreach ($filtered as $item) {
        echo '<div class="item">';
        echo '<div class="item-heading"><strong>' . receipt_h($item->description()) . '</strong>';
        if ($withValues) {
            echo '<strong class="item-subtotal">' . receipt_h(receipt_money($item->subtotal())) . '</strong>';
        }
        echo '</div><div class="item-detail"><span>Qtd. ' . receipt_h(receipt_quantity($item->quantity())) . ' ' . receipt_h($item->unit()) . '</span>';
        if ($withValues) {
            echo '<span>' . receipt_h(receipt_money($item->unitPrice())) . ' cada</span>';
        }
        echo '</div></div>';
    }
    echo '</div></section>';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Comprovante <?= receipt_h($order->displayNumber()) ?></title>
<style>
@page { size: 80mm auto; margin: 0; }
* { box-sizing: border-box; }
html, body { width: 80mm; margin: 0; padding: 0; }
body { background: #eef2f4; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10px; line-height: 1.3; }
.receipt { position: relative; isolation: isolate; width: 80mm; min-height: 100mm; margin: 10px auto; padding: 4mm; overflow: hidden; background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, .12); }
.receipt::before { position: absolute; top: 50%; left: 50%; z-index: 0; width: 110%; color: #000; content: "DOCUMENTO NÃO FISCAL"; font-size: 17px; font-weight: 800; letter-spacing: .1em; line-height: 1; opacity: .075; text-align: center; white-space: nowrap; transform: translate(-50%, -50%) rotate(-32deg); pointer-events: none; }
.receipt > * { position: relative; z-index: 1; }
.company-header { text-align: center; }
.company-logo { display: block; width: auto; max-width: 46mm; height: auto; max-height: 18mm; margin: 0 auto 2mm; object-fit: contain; }
.company-header h1 { margin: 0; font-size: 14px; line-height: 1.15; overflow-wrap: anywhere; }
.company-header p { margin: 1px 0; font-size: 9px; }
.document-header { margin-top: 3mm; padding: 2.5mm 0; border-top: 2px solid #111; border-bottom: 1px solid #111; text-align: center; }
.document-label { display: block; font-size: 9px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.document-number { display: block; margin: 1mm 0; font-size: 18px; line-height: 1; }
.document-meta { display: flex; justify-content: center; gap: 2mm; font-size: 9px; }
.receipt-section { margin-top: 3mm; break-inside: avoid; }
.receipt-section h2 { margin: 0 0 1.5mm; padding-bottom: 1mm; border-bottom: 1px solid #111; font-size: 10px; letter-spacing: .08em; text-transform: uppercase; }
.info-list { display: grid; gap: 1mm; margin: 0; }
.info-row { display: grid; grid-template-columns: 17mm minmax(0, 1fr); gap: 2mm; }
.info-row dt { color: #555; font-weight: 700; }
.info-row dd { min-width: 0; margin: 0; font-weight: 600; overflow-wrap: anywhere; }
.team-member { display: block; }
.team-member + .team-member { margin-top: .5mm; }
.item-list { border-bottom: 1px solid #111; }
.item { padding: 1.5mm 0; }
.item + .item { border-top: 1px dotted #777; }
.item-heading, .item-detail, .total-row { display: flex; justify-content: space-between; gap: 2mm; }
.item-heading strong:first-child { min-width: 0; overflow-wrap: anywhere; }
.item-subtotal { flex: 0 0 auto; white-space: nowrap; }
.item-detail { margin-top: .5mm; color: #444; font-size: 9px; }
.totals { display: grid; gap: .8mm; }
.total-row strong { white-space: nowrap; }
.grand-total { margin-top: 1mm; padding: 1.5mm 0; border-top: 2px solid #111; border-bottom: 2px solid #111; font-size: 14px; }
.receipt-footer { margin-top: 4mm; padding-top: 2mm; border-top: 1px dashed #777; text-align: center; }
.receipt-footer p { margin: 1px 0; }
.developer-credit { margin-top: 3mm !important; color: #555; font-size: 8px; }
.print-action { margin: 3mm 0 0; text-align: center; }
.print-action button { padding: 2mm 5mm; border: 1px solid #111; border-radius: 3px; background: #111; color: #fff; cursor: pointer; font: inherit; font-weight: 700; }
@media print {
    body { background: #fff; }
    .receipt { width: 80mm; min-height: 0; margin: 0; padding: 3mm; box-shadow: none; }
    .no-print { display: none; }
}
</style>
</head>
<body>
<main class="receipt">
    <header class="company-header">
        <?php if ($companyLogo !== null): ?><img class="company-logo" src="<?= receipt_h($companyLogo) ?>" alt="Logo <?= receipt_h($companyName) ?>"><?php endif; ?>
        <h1><?= receipt_h($companyName) ?></h1>
        <?php if (trim((string) ($company['razao_social'] ?? '')) !== '' && trim((string) $company['razao_social']) !== $companyName): ?><p><?= receipt_h($company['razao_social']) ?></p><?php endif; ?>
        <?php if (trim((string) ($company['documento'] ?? '')) !== ''): ?><p>CPF/CNPJ: <?= receipt_h($company['documento']) ?></p><?php endif; ?>
        <?php if (trim((string) ($company['telefone'] ?? '')) !== ''): ?><p>Telefone: <?= receipt_h($company['telefone']) ?></p><?php endif; ?>
        <?php if (trim((string) ($company['endereco'] ?? '')) !== ''): ?><p><?= receipt_h($company['endereco']) ?></p><?php endif; ?>
    </header>

    <div class="document-header">
        <span class="document-label">Comprovante de serviço</span>
        <strong class="document-number"><?= receipt_h($order->displayNumber()) ?></strong>
        <div class="document-meta"><span>Emitido em <?= date('d/m/Y H:i') ?></span><span>•</span><span>Finalizada</span></div>
    </div>

    <section class="receipt-section">
        <h2>Atendimento</h2>
        <dl class="info-list">
            <div class="info-row"><dt>Cliente</dt><dd><?= receipt_h($order->clientName()) ?></dd></div>
            <div class="info-row"><dt>Data</dt><dd><?= receipt_h($order->displaySchedule()) ?></dd></div>
            <div class="info-row"><dt>Local</dt><dd><?= receipt_h($order->equipmentLocation() ?: $order->clientAddress() ?: 'Não informado') ?></dd></div>
            <div class="info-row"><dt>Equipe</dt><dd>
                <?php if ($team === []): ?>Não informada<?php else: ?>
                    <?php foreach ($team as $member): ?><span class="team-member"><?= receipt_h($member->displayLine()) ?></span><?php endforeach; ?>
                <?php endif; ?>
            </dd></div>
        </dl>
    </section>

    <?php receipt_print_items($items, 'servico', 'Serviços executados', $withValues); ?>
    <?php receipt_print_items($items, 'produto', 'Peças utilizadas', $withValues); ?>
    <?php receipt_print_items($items, 'outro', 'Outros itens', $withValues); ?>

    <?php if ($withValues): ?>
        <section class="receipt-section">
            <h2>Resumo financeiro</h2>
            <div class="totals">
                <div class="total-row"><span>Serviços</span><strong><?= receipt_h(receipt_money($order->servicesSubtotal())) ?></strong></div>
                <div class="total-row"><span>Peças</span><strong><?= receipt_h(receipt_money($order->productsSubtotal())) ?></strong></div>
                <div class="total-row"><span>Outros itens</span><strong><?= receipt_h(receipt_money($order->othersSubtotal())) ?></strong></div>
                <div class="total-row"><span>Desconto</span><strong><?= receipt_h(receipt_money($order->discount())) ?></strong></div>
                <div class="total-row"><span>Acréscimo</span><strong><?= receipt_h(receipt_money($order->increase())) ?></strong></div>
                <div class="total-row grand-total"><span>Total</span><strong><?= receipt_h(receipt_money($order->total())) ?></strong></div>
            </div>
        </section>
    <?php endif; ?>

    <footer class="receipt-footer">
        <p><strong>Documento de serviço não fiscal.</strong></p>
        <p class="developer-credit">Desenvolvido por JL Soluções Tecnológicas</p>
    </footer>
    <div class="print-action no-print"><button type="button" onclick="window.print()">Imprimir comprovante</button></div>
</main>
</body>
</html>
