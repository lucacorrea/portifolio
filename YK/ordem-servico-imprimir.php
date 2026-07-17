<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;
use App\ServiceOrder\Entity\ServiceOrderItem;

require_once __DIR__ . '/includes/ui.php';

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();

try {
    $authorization = $application->authorization();
    $authorization->requireLogin();
    $authorization->requirePermission('os.imprimir');
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
    exit('Ordem de serviço não encontrada.');
}

try {
    $orderService = $application->serviceOrderManagement();
    $order = $orderService->getOrder($id);
    $items = $orderService->getOrderItems($id);
    $team = $orderService->getOrderTeamMembers($id);
} catch (Throwable) {
    http_response_code(404);
    exit('Ordem de serviço não encontrada.');
}

try {
    $company = $application->companySettings()->get();
} catch (Throwable) {
    $company = [];
}

$canViewValues = $authorization->can('os.visualizar_valores');
$withValues = $canViewValues && (string) ($_GET['valores'] ?? '0') === '1';

function os_print_money(string $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function os_print_datetime(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($date))->format('d/m/Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

/** @param array<string,mixed> $company */
function os_print_company_name(array $company): string
{
    $name = trim((string) ($company['nome_fantasia'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($company['razao_social'] ?? ''));
    }

    return $name !== '' ? $name : 'K. Yamaguchi Refrigeração';
}

function os_print_logo_url(mixed $value): ?string
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

function os_print_initials(string $name): string
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

/** @param ServiceOrderItem[] $items */
function os_print_items(array $items, string $type, string $title, bool $withValues): void
{
    $filtered = array_values(array_filter($items, static fn(ServiceOrderItem $item): bool => $item->type() === $type));
    if ($filtered === []) {
        return;
    }

    echo '<section class="document-section"><h2>' . h($title) . '</h2>';
    echo '<table class="item-table ' . ($withValues ? 'with-values' : 'without-values') . '"><colgroup><col class="description-column"><col class="unit-column"><col class="quantity-column">';
    if ($withValues) {
        echo '<col class="money-column"><col class="money-column"><col class="money-column">';
    }
    echo '</colgroup><thead><tr><th>Descrição</th><th>Un.</th><th>Qtd.</th>';
    if ($withValues) {
        echo '<th>Valor unit.</th><th>Desconto</th><th>Subtotal</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($filtered as $item) {
        echo '<tr><td>' . h($item->description()) . '</td><td>' . h($item->unit()) . '</td><td class="numeric">' . h(number_format((float) $item->quantity(), 3, ',', '.')) . '</td>';
        if ($withValues) {
            echo '<td class="numeric">' . h(os_print_money($item->unitPrice())) . '</td><td class="numeric">' . h(os_print_money($item->discount())) . '</td><td class="numeric">' . h(os_print_money($item->subtotal())) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></section>';
}

$companyName = os_print_company_name($company);
$companyLogo = os_print_logo_url($company['logo'] ?? null);
$legalName = trim((string) ($company['razao_social'] ?? ''));
$companyDetails = array_values(array_filter([
    $legalName !== '' && $legalName !== $companyName ? $legalName : null,
    trim((string) ($company['documento'] ?? '')) !== '' ? 'CPF/CNPJ: ' . trim((string) $company['documento']) : null,
    trim((string) ($company['telefone'] ?? '')) !== '' ? 'Telefone: ' . trim((string) $company['telefone']) : null,
    trim((string) ($company['endereco'] ?? '')) !== '' ? trim((string) $company['endereco']) : null,
], static fn(?string $value): bool => $value !== null));

$clientLocation = trim(implode(', ', array_filter([
    $order->clientAddress(),
    $order->clientNumber(),
    $order->clientDistrict(),
    $order->clientCity(),
    $order->clientState(),
], static fn(?string $value): bool => $value !== null && trim($value) !== '')));
$clientLocation = $clientLocation !== '' ? $clientLocation : '-';

$equipmentDetails = trim(implode(' · ', array_filter([
    $order->equipmentType(),
    $order->equipmentBrand(),
    $order->equipmentModel(),
    $order->equipmentCapacity(),
    $order->equipmentSerialNumber() !== null && trim($order->equipmentSerialNumber()) !== '' ? 'Série ' . $order->equipmentSerialNumber() : null,
], static fn(?string $value): bool => $value !== null && trim($value) !== '')));
$equipmentDetails = $equipmentDetails !== '' ? $equipmentDetails : '-';

$operationalNotes = array_values(array_filter([
    ['label' => 'Problema relatado', 'value' => $order->reportedProblem()],
    ['label' => 'Problema identificado', 'value' => $order->identifiedProblem()],
    ['label' => 'Diagnóstico', 'value' => $order->diagnosis()],
    ['label' => 'Solução', 'value' => $order->solution()],
    ['label' => 'Recomendação', 'value' => $order->recommendation()],
    ['label' => 'Observações', 'value' => $order->notes()],
], static fn(array $note): bool => $note['value'] !== null && trim((string) $note['value']) !== ''));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem de Serviço <?= h($order->displayNumber()) ?></title>
    <style>
        @page { size: A5 landscape; margin: 6mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e8f0f4; color: #0f172a; font-family: Arial, sans-serif; font-size: 10px; }
        .print-page { width: 210mm; min-height: 148mm; margin: 12px auto; padding: 8mm; background: #fff; box-shadow: 0 8px 30px rgba(15, 23, 42, .12); }
        .document-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding-bottom: 7px; border-bottom: 2px solid #0f7894; }
        .company-heading { min-width: 0; display: flex; align-items: center; gap: 9px; }
        .company-logo { position: relative; width: 34mm; height: 18mm; display: flex; flex: 0 0 34mm; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #dbe7ee; border-radius: 6px; background: #f8fbfc; }
        .company-logo img { position: absolute; inset: 0; display: block; max-width: 100%; max-height: 100%; width: auto; height: auto; margin: auto; background: #f8fbfc; object-fit: contain; }
        .company-logo-fallback { color: #0f7894; font-size: 18px; font-weight: 800; letter-spacing: .08em; }
        .brand { min-width: 0; }
        .brand h1 { margin: 0 0 2px; color: #0f7894; font-size: 17px; line-height: 1.1; overflow-wrap: anywhere; }
        .brand p, .document-meta p { margin: 1px 0; color: #475569; font-size: 9px; line-height: 1.25; }
        .document-meta { flex: 0 0 50mm; text-align: right; }
        .document-meta strong { display: block; margin-bottom: 2px; font-size: 17px; color: #0f172a; }
        .document-title { margin: 0 0 2px; color: #0f7894; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .status { display: inline-block; margin-top: 2px; padding: 2px 5px; border: 1px solid #b8d7e0; border-radius: 999px; color: #0f7894; font-size: 8px; font-weight: 800; text-transform: uppercase; }
        .document-section { margin-top: 8px; break-inside: auto; }
        h2 { margin: 0 0 4px; color: #0f7894; font-size: 11px; line-height: 1.2; }
        .info-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 5px; }
        .field { min-width: 0; min-height: 30px; padding: 5px 6px; border: 1px solid #dbe7ee; border-radius: 4px; break-inside: avoid; }
        .field-wide { grid-column: span 2; }
        .field > span, .note-card > span { display: block; margin-bottom: 1px; color: #64748b; font-size: 8px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
        .field strong { display: block; font-size: 9.5px; overflow-wrap: anywhere; }
        .team-member { display: block; }
        .team-member + .team-member { margin-top: 1px; }
        .item-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5px; }
        .item-table thead { display: table-header-group; }
        .item-table tr { break-inside: avoid; page-break-inside: avoid; }
        .item-table th, .item-table td { padding: 3px 4px; border: 1px solid #dbe7ee; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        .item-table th { background: #f3f8fb; color: #475569; font-size: 7.5px; letter-spacing: .02em; text-transform: uppercase; }
        .item-table .description-column { width: 40%; }
        .item-table .unit-column { width: 7%; }
        .item-table .quantity-column { width: 9%; }
        .item-table .money-column { width: 14.66%; }
        .item-table.without-values .description-column { width: 84%; }
        .numeric { text-align: right !important; white-space: nowrap; }
        .notes-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px; }
        .note-card { min-width: 0; padding: 5px 6px; border: 1px solid #dbe7ee; border-radius: 4px; break-inside: avoid; }
        .note-card p { margin: 0; line-height: 1.3; overflow-wrap: anywhere; }
        .totals { width: 72mm; margin-left: auto; break-inside: avoid; }
        .totals div { display: flex; justify-content: space-between; gap: 8px; padding: 3px 0; border-bottom: 1px solid #dbe7ee; }
        .totals .total { border-bottom: 0; color: #0f7894; font-size: 13px; font-weight: 800; }
        .signatures { display: grid; grid-template-columns: repeat(2, 70mm); justify-content: space-around; gap: 14mm; margin-top: 18px; break-inside: avoid; }
        .signatures div { padding-top: 4px; border-top: 1px solid #0f172a; color: #475569; text-align: center; }
        .document-footer { margin-top: 7px; color: #64748b; font-size: 8px; text-align: center; }
        .print-actions { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 6px; padding: 9px; background: #e8f0f4; }
        .print-actions a, .print-actions button { padding: 8px 12px; border: 1px solid #0f7894; border-radius: 6px; background: #fff; color: #0f7894; cursor: pointer; font: inherit; font-weight: 700; text-decoration: none; }
        .print-actions .primary, .print-actions .active { background: #0f7894; color: #fff; }
        @media print {
            body { background: #fff; }
            .print-page { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .print-actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <?php if ($canViewValues): ?>
            <a class="<?= $withValues ? '' : 'active' ?>" href="ordem-servico-imprimir.php?id=<?= h((string) $order->id()) ?>&amp;valores=0">Sem valores</a>
            <a class="<?= $withValues ? 'active' : '' ?>" href="ordem-servico-imprimir.php?id=<?= h((string) $order->id()) ?>&amp;valores=1">Com valores</a>
        <?php endif; ?>
        <button class="primary" type="button" onclick="window.print()">Imprimir / reimprimir OS</button>
    </div>
    <main class="print-page">
        <header class="document-header">
            <div class="company-heading">
                <div class="company-logo">
                    <span class="company-logo-fallback" aria-hidden="true"><?= h(os_print_initials($companyName)) ?></span>
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
                <div class="document-title">Ordem de Serviço</div>
                <strong><?= h($order->displayNumber()) ?></strong>
                <p>Abertura: <?= h(os_print_datetime($order->createdAt())) ?></p>
                <?php if ($order->finishedAt() !== null): ?><p>Finalização: <?= h(os_print_datetime($order->finishedAt())) ?></p><?php endif; ?>
                <span class="status"><?= h($order->displayStatus()) ?></span>
            </div>
        </header>

        <section class="document-section">
            <h2>Atendimento</h2>
            <div class="info-grid">
                <div class="field"><span>Cliente</span><strong><?= h($order->clientName()) ?></strong></div>
                <div class="field"><span>Prioridade</span><strong><?= h($order->displayPriority()) ?></strong></div>
                <div class="field field-wide"><span>Agendamento</span><strong><?= h($order->displaySchedule()) ?></strong></div>
                <div class="field field-wide"><span>Endereço do cliente</span><strong><?= h($clientLocation) ?></strong></div>
                <div class="field"><span>Local do equipamento</span><strong><?= h($order->equipmentLocation() ?: '-') ?></strong></div>
                <div class="field"><span>Ambiente</span><strong><?= h($order->equipmentEnvironment() ?: '-') ?></strong></div>
                <div class="field field-wide"><span>Equipamento</span><strong><?= h($equipmentDetails) ?></strong></div>
                <div class="field field-wide"><span>Equipe</span><strong>
                    <?php if ($team === []): ?>Sem equipe definida<?php else: ?>
                        <?php foreach ($team as $member): ?><span class="team-member"><?= h($member->displayLine()) ?></span><?php endforeach; ?>
                    <?php endif; ?>
                </strong></div>
            </div>
        </section>

        <?php os_print_items($items, 'servico', 'Serviços', $withValues); ?>
        <?php os_print_items($items, 'produto', 'Produtos utilizados', $withValues); ?>
        <?php os_print_items($items, 'outro', 'Outros itens', $withValues); ?>

        <?php if ($operationalNotes !== []): ?>
            <section class="document-section">
                <h2>Registro técnico</h2>
                <div class="notes-grid">
                    <?php foreach ($operationalNotes as $note): ?>
                        <div class="note-card"><span><?= h((string) $note['label']) ?></span><p><?= nl2br(h((string) $note['value'])) ?></p></div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($withValues): ?>
            <section class="document-section">
                <h2>Resumo financeiro</h2>
                <div class="totals">
                    <div><span>Subtotal serviços</span><strong><?= h(os_print_money($order->servicesSubtotal())) ?></strong></div>
                    <div><span>Subtotal produtos</span><strong><?= h(os_print_money($order->productsSubtotal())) ?></strong></div>
                    <div><span>Subtotal outros</span><strong><?= h(os_print_money($order->othersSubtotal())) ?></strong></div>
                    <div><span>Desconto</span><strong><?= h(os_print_money($order->discount())) ?></strong></div>
                    <div><span>Acréscimo</span><strong><?= h(os_print_money($order->increase())) ?></strong></div>
                    <div class="total"><span>Total</span><strong><?= h(os_print_money($order->total())) ?></strong></div>
                </div>
            </section>
        <?php endif; ?>

        <section class="signatures"><div>Assinatura do cliente</div><div>Responsável pelo atendimento</div></section>
        <footer class="document-footer">Documento de serviço não fiscal.</footer>
    </main>
</body>
</html>
