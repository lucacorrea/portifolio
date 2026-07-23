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

try {
    $company = $application->companySettings()->get();
} catch (Throwable) {
    $company = [];
}

function budget_print_date(string $date): string
{
    try {
        return (new DateTimeImmutable($date))->format('d/m/Y');
    } catch (Throwable) {
        return '-';
    }
}

function budget_print_money(string $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

/** @param array<string,mixed> $company */
function budget_print_company_name(array $company): string
{
    $name = trim((string) ($company['nome_fantasia'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($company['razao_social'] ?? ''));
    }

    return $name !== '' ? $name : 'K. Yamaguchi Refrigeração';
}

function budget_print_logo_url(mixed $value): ?string
{
    $logo = trim((string) ($value ?? ''));
    if (
        $logo === ''
        || str_contains($logo, "\0")
        || $logo !== strip_tags($logo)
        || preg_match('/[\x00-\x1F\x7F]/', $logo)
        || str_contains($logo, '\\')
    ) {
        return null;
    }

    $parts = parse_url($logo);
    if ($parts === false) {
        return null;
    }

    if (isset($parts['scheme'])) {
        $scheme = strtolower((string) $parts['scheme']);
        if (
            !in_array($scheme, ['http', 'https'], true)
            || filter_var($logo, FILTER_VALIDATE_URL) === false
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $logo;
    }

    if (str_starts_with($logo, '//') || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }

    $path = rawurldecode((string) ($parts['path'] ?? ''));
    if ($path === '') {
        return null;
    }

    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '..') {
            return null;
        }
    }

    return $logo;
}

function budget_print_initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $slice = static fn(string $value, int $length): string => function_exists('mb_substr')
        ? mb_substr($value, 0, $length, 'UTF-8')
        : substr($value, 0, $length);
    $upper = static fn(string $value): string => function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
    if (count($words) >= 2) {
        return $upper($slice($words[0], 1) . $slice($words[count($words) - 1], 1));
    }

    return $upper($slice($name, 2));
}

/** @param BudgetItem[] $items */
function budget_print_items(array $items, string $type, string $title): void
{
    $filtered = array_values(array_filter($items, static fn(BudgetItem $item): bool => $item->type() === $type));
    if ($filtered === []) {
        return;
    }

    echo '<section class="document-section"><h2>' . h($title) . '</h2>';
    echo '<table class="item-table"><colgroup><col class="description-column"><col class="unit-column"><col class="quantity-column"><col class="money-column"><col class="money-column"><col class="money-column"></colgroup>';
    echo '<thead><tr><th>Descrição</th><th>Un.</th><th>Qtd.</th><th>Valor unit.</th><th>Desconto</th><th>Subtotal</th></tr></thead><tbody>';
    foreach ($filtered as $item) {
        echo '<tr><td>' . h($item->description()) . '</td><td>' . h($item->unit()) . '</td><td class="numeric">' . h(number_format((float) $item->quantity(), 3, ',', '.')) . '</td><td class="numeric">' . h(budget_print_money($item->unitPrice())) . '</td><td class="numeric">' . h(budget_print_money($item->discount())) . '</td><td class="numeric">' . h(budget_print_money($item->subtotal())) . '</td></tr>';
    }
    echo '</tbody></table></section>';
}

$companyName = budget_print_company_name($company);
$companyLogo = budget_print_logo_url($company['logo'] ?? null);
$legalName = trim((string) ($company['razao_social'] ?? ''));
$companyDetails = array_values(array_filter([
    $legalName !== '' && $legalName !== $companyName ? $legalName : null,
    trim((string) ($company['documento'] ?? '')) !== '' ? 'CPF/CNPJ: ' . trim((string) $company['documento']) : null,
    trim((string) ($company['telefone'] ?? '')) !== '' ? 'Telefone: ' . trim((string) $company['telefone']) : null,
    trim((string) ($company['endereco'] ?? '')) !== '' ? trim((string) $company['endereco']) : null,
], static fn(?string $value): bool => $value !== null));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento <?= h($budget->displayNumber()) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e8f0f4; color: #0f172a; font-family: Arial, sans-serif; font-size: 10px; }
        .print-page { width: 210mm; min-height: 148.5mm; margin: 12px auto; padding: 6mm 7mm 5mm; background: #fff; box-shadow: 0 8px 30px rgba(15, 23, 42, .12); }
        .document-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding-bottom: 7px; border-bottom: 2px solid #0f7894; }
        .company-heading { min-width: 0; display: flex; align-items: center; gap: 9px; }
        .company-logo { position: relative; width: 34mm; height: 18mm; display: flex; flex: 0 0 34mm; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #dbe7ee; border-radius: 6px; background: #f8fbfc; }
        .company-logo img { position: absolute; inset: 0; display: block; max-width: 100%; max-height: 100%; width: auto; height: auto; margin: auto; background: #f8fbfc; object-fit: contain; }
        .company-logo-fallback { color: #0f7894; font-size: 18px; font-weight: 800; letter-spacing: .08em; }
        .brand { min-width: 0; }
        .brand h1 { margin: 0 0 2px; color: #0f7894; font-size: 17px; line-height: 1.1; overflow-wrap: anywhere; }
        .brand p, .document-meta p { margin: 1px 0; color: #475569; font-size: 9px; line-height: 1.25; }
        .document-meta { flex: 0 0 48mm; text-align: right; }
        .document-meta strong { display: block; margin-bottom: 2px; font-size: 17px; color: #0f172a; }
        .document-title { margin: 0 0 2px; color: #0f7894; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .document-section { margin-top: 8px; break-inside: auto; }
        h2 { margin: 0 0 4px; color: #0f7894; font-size: 11px; line-height: 1.2; }
        .info-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 5px; }
        .field { min-width: 0; min-height: 30px; padding: 5px 6px; border: 1px solid #dbe7ee; border-radius: 4px; break-inside: avoid; }
        .field span { display: block; margin-bottom: 1px; color: #64748b; font-size: 8px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
        .field strong { display: block; font-size: 9.5px; overflow-wrap: anywhere; }
        .item-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5px; }
        .item-table thead { display: table-header-group; }
        .item-table tr { break-inside: avoid; page-break-inside: avoid; }
        .item-table th, .item-table td { padding: 3px 4px; border: 1px solid #dbe7ee; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        .item-table th { background: #f3f8fb; color: #475569; font-size: 7.5px; letter-spacing: .02em; text-transform: uppercase; }
        .item-table .description-column { width: 40%; }
        .item-table .unit-column { width: 7%; }
        .item-table .quantity-column { width: 9%; }
        .item-table .money-column { width: 14.66%; }
        .numeric { text-align: right !important; white-space: nowrap; }
        .document-closing { display: grid; grid-template-columns: minmax(0, 1fr) 72mm; align-items: stretch; gap: 10mm; margin-top: 8px; break-inside: avoid; }
        .closing-main { display: flex; min-width: 0; flex-direction: column; }
        .financial-summary h2 { margin-bottom: 2px; }
        .notes { min-height: 43px; margin: 0; padding: 6px; border: 1px solid #dbe7ee; border-radius: 4px; line-height: 1.35; overflow-wrap: anywhere; }
        .totals { width: 100%; break-inside: avoid; }
        .totals div { display: flex; justify-content: space-between; gap: 8px; padding: 3px 0; border-bottom: 1px solid #dbe7ee; }
        .totals .total { border-bottom: 0; color: #0f7894; font-size: 13px; font-weight: 800; }
        .signature { display: flex; justify-content: center; margin-top: auto; padding-top: 16px; break-inside: avoid; }
        .signature div { width: 72mm; padding-top: 4px; border-top: 1px solid #0f172a; color: #475569; text-align: center; }
        .document-footer { margin-top: 7px; color: #64748b; font-size: 8px; text-align: center; }
        .print-actions { position: sticky; top: 0; z-index: 2; padding: 9px; background: #e8f0f4; text-align: center; }
        .print-actions button { padding: 8px 15px; border: 0; border-radius: 6px; background: #0f7894; color: #fff; cursor: pointer; font-weight: 700; }
        @media print {
            body { background: #fff; }
            .print-page { width: 210mm; min-height: 148.5mm; margin: 0; padding: 6mm 7mm 5mm; box-shadow: none; }
            .print-actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Imprimir / reimprimir orçamento</button></div>
    <main class="print-page">
        <header class="document-header">
            <div class="company-heading">
                <div class="company-logo">
                    <span class="company-logo-fallback" aria-hidden="true"><?= h(budget_print_initials($companyName)) ?></span>
                    <?php if ($companyLogo !== null): ?>
                        <img src="<?= h($companyLogo) ?>" alt="Logo <?= h($companyName) ?>" onerror="this.style.display='none'">
                    <?php endif; ?>
                </div>
                <div class="brand">
                    <h1><?= h($companyName) ?></h1>
                    <?php foreach ($companyDetails as $detail): ?><p><?= h($detail) ?></p><?php endforeach; ?>
                </div>
            </div>
            <div class="document-meta">
                <div class="document-title">Orçamento comercial</div>
                <strong><?= h($budget->displayNumber()) ?></strong>
                <p>Emissão: <?= h(budget_print_date($budget->issueDate())) ?></p>
                <p>Validade: <?= h(budget_print_date($budget->validUntil())) ?></p>
            </div>
        </header>

        <section class="document-section">
            <h2>Dados do cliente</h2>
            <div class="info-grid">
                <div class="field"><span>Cliente</span><strong><?= h($budget->clientName()) ?></strong></div>
                <div class="field"><span>CPF/CNPJ</span><strong><?= h($budget->clientDocument() ?? '-') ?></strong></div>
                <div class="field"><span>Código</span><strong><?= h($budget->clientCode()) ?></strong></div>
                <div class="field"><span>Status comercial</span><strong><?= h($budget->displayStatus()) ?></strong></div>
            </div>
        </section>

        <?php budget_print_items($items, 'servico', 'Serviços'); ?>
        <?php budget_print_items($items, 'produto', 'Produtos'); ?>
        <?php budget_print_items($items, 'outro', 'Outros itens'); ?>

        <section class="document-closing">
            <div class="closing-main">
                <h2>Observações</h2>
                <p class="notes"><?= nl2br(h($budget->notes() ?? 'Sem observações.')) ?></p>
                <div class="signature"><div>Assinatura do cliente</div></div>
            </div>
            <div class="financial-summary">
                <h2>Resumo financeiro</h2>
                <div class="totals">
                    <div><span>Subtotal serviços</span><strong><?= h(budget_print_money($budget->servicesSubtotal())) ?></strong></div>
                    <div><span>Subtotal produtos</span><strong><?= h(budget_print_money($budget->productsSubtotal())) ?></strong></div>
                    <div><span>Subtotal outros</span><strong><?= h(budget_print_money($budget->othersSubtotal())) ?></strong></div>
                    <div><span>Desconto</span><strong><?= h(budget_print_money($budget->discount())) ?></strong></div>
                    <div><span>Acréscimo</span><strong><?= h(budget_print_money($budget->increase())) ?></strong></div>
                    <div class="total"><span>Total</span><strong><?= h(budget_print_money($budget->total())) ?></strong></div>
                </div>
            </div>
        </section>

        <footer class="document-footer">Documento comercial não fiscal.</footer>
    </main>
</body>
</html>
