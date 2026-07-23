<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();

try {
    $authorization = $application->authorization();
    $user = $authorization->requireLogin();
} catch (AuthenticationException) {
    header('Location: login.php', true, 303);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($id)) {
    http_response_code(404);
    exit('Recibo não encontrado.');
}

$grant = isset($_SESSION['receipt_initial_print_grant']) && is_array($_SESSION['receipt_initial_print_grant'])
    ? $_SESSION['receipt_initial_print_grant']
    : [];
if ($grant !== [] && (int) ($grant['expires_at'] ?? 0) < time()) {
    unset($_SESSION['receipt_initial_print_grant']);
    $grant = [];
}
$hasInitialPrintGrant = $authorization->can('recibo.emitir')
    && (int) ($grant['receipt_id'] ?? 0) === $id
    && (int) ($grant['user_id'] ?? 0) === $user->id()
    && (int) ($grant['expires_at'] ?? 0) >= time();

try {
    if (!$authorization->can('recibo.reimprimir') && !$hasInitialPrintGrant) {
        $authorization->requirePermission('recibo.reimprimir');
    }
} catch (AuthorizationException) {
    header('Location: acesso-negado.php', true, 303);
    exit;
}

if ($hasInitialPrintGrant) {
    unset($_SESSION['receipt_initial_print_grant']);
}

try {
    $receipt = $application->receiptService()->getById($id);
} catch (InvalidArgumentException) {
    http_response_code(404);
    exit('Recibo não encontrado.');
}

function receipt_print_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function receipt_print_money(mixed $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function receipt_print_date(mixed $value): string
{
    try {
        return (new DateTimeImmutable((string) $value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

function receipt_print_form(mixed $value): string
{
    return [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'boleto' => 'Boleto',
        'cartao_debito' => 'Cartão de débito',
        'cartao_credito' => 'Cartão de crédito',
        'transferencia' => 'Transferência',
        'outro' => 'Outro',
    ][(string) $value] ?? (string) $value;
}

function receipt_print_logo(mixed $value): ?string
{
    $logo = trim((string) $value);
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
    if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }

    if (isset($parts['scheme'])) {
        if (!in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true) || filter_var($logo, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        return $logo;
    }

    if (str_starts_with($logo, '//') || isset($parts['host'])) {
        return null;
    }
    $path = rawurldecode((string) ($parts['path'] ?? ''));
    foreach (preg_split('~[\\/]+~', $path, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $segment) {
        if ($segment === '..') {
            return null;
        }
    }
    return $logo;
}

$logo = receipt_print_logo($receipt['empresa_logo'] ?? null);
$isCanceled = ($receipt['status'] ?? '') === 'cancelado';
$installmentCount = filter_var($receipt['quantidade_parcelas'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 60],
]);
if (!is_int($installmentCount)) $installmentCount = 1;
$showsInstallments = in_array((string) ($receipt['forma_pagamento'] ?? ''), ['boleto', 'cartao_credito'], true);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recibo <?= receipt_print_h($receipt['numero'] ?? '') ?></title>
<style>
@page { size: A5 portrait; margin: 10mm; }
* { box-sizing: border-box; }
body { margin: 0; background: #eef2f7; color: #111827; font-family: Arial, sans-serif; }
.receipt { position: relative; width: 148mm; min-height: 190mm; margin: 14px auto; padding: 12mm; background: #fff; border: 1px solid #d1d5db; }
.header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; border-bottom: 2px solid #111827; padding-bottom: 10px; }
.company { display: flex; align-items: flex-start; gap: 12px; }
.logo { width: 25mm; max-height: 20mm; object-fit: contain; }
.company h1 { margin: 0 0 4px; font-size: 17px; }
.company p, .meta p { margin: 2px 0; font-size: 11px; }
.meta { text-align: right; white-space: nowrap; }
.title { margin: 22px 0 16px; text-align: center; font-size: 22px; letter-spacing: .08em; }
.amount { margin: 16px 0; padding: 12px; border: 1px solid #9ca3af; border-radius: 6px; text-align: center; font-size: 21px; font-weight: 700; }
.description { min-height: 50mm; font-size: 14px; line-height: 1.65; }
.details { border-top: 1px solid #d1d5db; padding-top: 10px; font-size: 12px; line-height: 1.6; }
.signature { width: 75%; margin: 25mm auto 0; border-top: 1px solid #111827; padding-top: 5px; text-align: center; font-size: 11px; }
.non-fiscal { margin-top: 16px; text-align: center; color: #4b5563; font-size: 10px; font-weight: 700; }
.canceled { position: absolute; inset: 42% 0 auto; transform: rotate(-18deg); color: rgba(185, 28, 28, .22); font-size: 48px; font-weight: 800; text-align: center; pointer-events: none; }
.cancel-note { margin-top: 12px; padding: 8px; border: 1px solid #dc2626; color: #991b1b; font-size: 11px; }
.print-actions { width: 148mm; margin: 0 auto 14px; text-align: right; }
.print-actions button { border: 0; border-radius: 6px; padding: 9px 15px; background: #1d4ed8; color: white; cursor: pointer; }
@media print {
    body { background: #fff; }
    .receipt { width: auto; min-height: 0; margin: 0; padding: 0; border: 0; }
    .print-actions { display: none; }
}
</style>
</head>
<body>
<main class="receipt">
    <?php if ($isCanceled): ?><div class="canceled">CANCELADO</div><?php endif; ?>
    <header class="header">
        <div class="company">
            <?php if ($logo !== null): ?><img class="logo" src="<?= receipt_print_h($logo) ?>" alt="Logotipo da empresa"><?php endif; ?>
            <div>
                <h1><?= receipt_print_h($receipt['empresa_nome'] ?: 'Empresa não configurada') ?></h1>
                <?php if ($receipt['empresa_documento']): ?><p>Documento: <?= receipt_print_h($receipt['empresa_documento']) ?></p><?php endif; ?>
                <?php if ($receipt['empresa_telefone']): ?><p>Telefone: <?= receipt_print_h($receipt['empresa_telefone']) ?></p><?php endif; ?>
                <?php if ($receipt['empresa_endereco']): ?><p><?= receipt_print_h($receipt['empresa_endereco']) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="meta">
            <p><strong><?= receipt_print_h($receipt['numero']) ?></strong></p>
            <p>Emitido em <?= receipt_print_h(receipt_print_date($receipt['emitido_em'])) ?></p>
        </div>
    </header>

    <h2 class="title">RECIBO DE PAGAMENTO</h2>
    <div class="amount"><?= receipt_print_h(receipt_print_money($receipt['valor'])) ?></div>
    <section class="description">
        <p><?= receipt_print_h($receipt['descricao']) ?></p>
        <p><strong>Cliente:</strong> <?= receipt_print_h($receipt['cliente_nome']) ?></p>
        <?php if ($receipt['cliente_documento']): ?><p><strong>Documento:</strong> <?= receipt_print_h($receipt['cliente_documento']) ?></p><?php endif; ?>
    </section>
    <section class="details">
        <?php if (!empty($receipt['os_numero'])): ?><div><strong>Ordem de Serviço:</strong> <?= receipt_print_h($receipt['os_numero']) ?></div><?php endif; ?>
        <div><strong>Forma de pagamento:</strong> <?= receipt_print_h(receipt_print_form($receipt['forma_pagamento'])) ?></div>
        <?php if ($showsInstallments): ?><div><strong>Parcelas:</strong> <?= receipt_print_h((string) $installmentCount) ?>x</div><?php endif; ?>
        <div><strong>Recebido em:</strong> <?= receipt_print_h(receipt_print_date($receipt['pagamento_recebido_em'] ?: $receipt['emitido_em'])) ?></div>
        <div><strong>Emitido por:</strong> <?= receipt_print_h($receipt['emitido_por_nome']) ?></div>
    </section>
    <?php if ($isCanceled): ?>
        <div class="cancel-note">
            Cancelado em <?= receipt_print_h(receipt_print_date($receipt['cancelado_em'])) ?>.
            Motivo: <?= receipt_print_h($receipt['motivo_cancelamento'] ?? 'Não informado') ?>.
        </div>
    <?php endif; ?>
    <div class="signature"><?= receipt_print_h($receipt['empresa_nome'] ?: 'Responsável pelo recebimento') ?></div>
    <div class="non-fiscal">DOCUMENTO NÃO FISCAL</div>
</main>
<div class="print-actions"><button type="button" onclick="window.print()">Imprimir recibo</button></div>
</body>
</html>
