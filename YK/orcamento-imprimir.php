<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;
use App\Sales\Entity\BudgetItem;

require_once __DIR__ . '/includes/ui.php';

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();

try {
    $authorization = $application->authorization();
    $authorization->requireLogin();
    $authorization->requirePermission('orcamento.imprimir');
} catch (AuthenticationException) {
    header('Location: login.php', true, 303);
    exit;
} catch (AuthorizationException) {
    header('Location: acesso-negado.php', true, 303);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($id)) {
    http_response_code(404);
    exit('Orçamento não encontrado.');
}

try {
    $service = $application->budgetManagement();
    $budget = $service->getBudget($id);
    $items = $service->getBudgetItems($id);
} catch (Throwable) {
    http_response_code(404);
    exit('Orçamento não encontrado.');
}

function print_date(string $date): string
{
    try { return (new DateTimeImmutable($date))->format('d/m/Y'); } catch (Throwable) { return '-'; }
}

function print_money(string $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

/** @param BudgetItem[] $items */
function print_items(array $items, string $type, string $title): void
{
    $filtered = array_values(array_filter($items, static fn(BudgetItem $item): bool => $item->type() === $type));
    if ($filtered === []) return;
    echo '<h2>' . h($title) . '</h2><table><thead><tr><th>Descrição</th><th>Un.</th><th>Qtd.</th><th>Valor unit.</th><th>Desconto</th><th>Subtotal</th></tr></thead><tbody>';
    foreach ($filtered as $item) {
        echo '<tr><td>' . h($item->description()) . '</td><td>' . h($item->unit()) . '</td><td>' . h(number_format((float) $item->quantity(), 3, ',', '.')) . '</td><td>' . h(print_money($item->unitPrice())) . '</td><td>' . h(print_money($item->discount())) . '</td><td>' . h(print_money($item->subtotal())) . '</td></tr>';
    }
    echo '</tbody></table>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento <?= h($budget->displayNumber()) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef5f8; color: #0f172a; font-family: Arial, sans-serif; }
        .print-page { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 18mm; background: #fff; }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #0f7894; padding-bottom: 16px; }
        .brand h1 { margin: 0; font-size: 22px; color: #0f7894; }
        .brand p, .meta p { margin: 4px 0; color: #475569; font-size: 12px; }
        .meta { text-align: right; }
        .meta strong { display: block; font-size: 20px; }
        h2 { margin: 22px 0 8px; font-size: 15px; color: #0f7894; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 18px; }
        .field { border: 1px solid #dbe7ee; border-radius: 6px; padding: 8px; min-height: 42px; }
        .field span { display: block; color: #64748b; font-size: 11px; text-transform: uppercase; }
        .field strong { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px; }
        th, td { border: 1px solid #dbe7ee; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f8fb; color: #475569; font-size: 11px; text-transform: uppercase; }
        .totals { margin-left: auto; width: 320px; }
        .totals div { display: flex; justify-content: space-between; border-bottom: 1px solid #dbe7ee; padding: 8px 0; }
        .totals .total { font-size: 18px; font-weight: 700; color: #0f7894; border-bottom: 0; }
        .signature { margin-top: 48px; display: flex; justify-content: center; }
        .signature div { width: 300px; border-top: 1px solid #0f172a; text-align: center; padding-top: 8px; color: #475569; }
        .print-actions { position: sticky; top: 0; padding: 12px; text-align: center; background: #eef5f8; }
        .print-actions button { border: 0; border-radius: 8px; background: #0f7894; color: #fff; padding: 10px 18px; font-weight: 700; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .print-page { margin: 0; width: auto; min-height: 0; box-shadow: none; }
            .print-actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Imprimir orçamento</button></div>
    <main class="print-page">
        <header class="header">
            <div class="brand"><h1>K. Yamaguchi Refrigeração</h1><p>Orçamento comercial de serviços, produtos e itens personalizados.</p></div>
            <div class="meta"><strong><?= h($budget->displayNumber()) ?></strong><p>Emissão: <?= h(print_date($budget->issueDate())) ?></p><p>Validade: <?= h(print_date($budget->validUntil())) ?></p></div>
        </header>
        <h2>Dados do cliente</h2>
        <section class="grid">
            <div class="field"><span>Cliente</span><strong><?= h($budget->clientName()) ?></strong></div>
            <div class="field"><span>CPF/CNPJ</span><strong><?= h($budget->clientDocument() ?? '-') ?></strong></div>
            <div class="field"><span>Código do cliente</span><strong><?= h($budget->clientCode()) ?></strong></div>
            <div class="field"><span>Status comercial</span><strong><?= h($budget->displayStatus()) ?></strong></div>
        </section>
        <?php print_items($items, 'servico', 'Serviços'); ?>
        <?php print_items($items, 'produto', 'Produtos'); ?>
        <?php print_items($items, 'outro', 'Outros itens'); ?>
        <h2>Resumo financeiro</h2>
        <section class="totals">
            <div><span>Subtotal serviços</span><strong><?= h(print_money($budget->servicesSubtotal())) ?></strong></div>
            <div><span>Subtotal produtos</span><strong><?= h(print_money($budget->productsSubtotal())) ?></strong></div>
            <div><span>Subtotal outros</span><strong><?= h(print_money($budget->othersSubtotal())) ?></strong></div>
            <div><span>Desconto</span><strong><?= h(print_money($budget->discount())) ?></strong></div>
            <div><span>Acréscimo</span><strong><?= h(print_money($budget->increase())) ?></strong></div>
            <div class="total"><span>Total</span><strong><?= h(print_money($budget->total())) ?></strong></div>
        </section>
        <h2>Observações</h2>
        <p><?= nl2br(h($budget->notes() ?? 'Sem observações.')) ?></p>
        <section class="signature"><div>Assinatura comercial</div></section>
    </main>
</body>
</html>
