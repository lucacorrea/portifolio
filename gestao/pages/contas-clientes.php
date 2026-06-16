<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;
use App\Security\Csrf;
use App\Services\ClientAccountService;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);
$currentUserId = (int)($user['id'] ?? 0);
$currentNivel = (string)($user['nivel'] ?? '');
$accountService = new ClientAccountService();

function canClientAccountAccess(string $action, string $nivel): bool
{
    $permissions = [
        'view' => ['admin', 'gerente', 'operador', 'leitor'],
        'pay' => ['admin', 'gerente', 'operador'],
        'settle' => ['admin', 'gerente', 'operador'],
        'print' => ['admin', 'gerente', 'operador', 'leitor'],
    ];

    return in_array($nivel, $permissions[$action] ?? [], true);
}

function requireClientAccountAccess(string $action, string $nivel): void
{
    if (!canClientAccountAccess($action, $nivel)) {
        throw new RuntimeException('Você não tem permissão para executar esta ação.');
    }
}

function accountMoney(mixed $value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function accountDate(mixed $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Sem data';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y', $timestamp) : 'Sem data';
}

function accountDateTime(mixed $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Sem data';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Sem data';
}

function accountStatusLabel(string $status): string
{
    return [
        'em_aberto' => 'Em aberto',
        'parcial' => 'Parcial',
        'pago' => 'Pago',
        'atrasado' => 'Atrasado',
        'cancelado' => 'Cancelado',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function accountStatusClass(string $status): string
{
    return match ($status) {
        'pago' => 'green',
        'atrasado' => 'red',
        'parcial' => 'orange',
        'cancelado' => 'muted',
        default => 'blue',
    };
}

function accountPaymentLabel(string $method): string
{
    return [
        'pix' => 'PIX',
        'dinheiro' => 'Dinheiro',
        'credito' => 'Crédito',
        'debito' => 'Débito',
        'transferencia' => 'Transferência',
        'outro' => 'Outro',
    ][$method] ?? ucfirst(str_replace('_', ' ', $method));
}

function redirectAccounts(string $type, string $message, array $receipt = []): void
{
    $_SESSION['account_flash'] = [
        'type' => $type,
        'message' => $message,
        'receipt' => $receipt,
    ];

    $params = $_GET;
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    header('Location: contas-clientes.php' . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

function accountFilterUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 'todas') {
            unset($params[$key]);
        }
    }

    return 'contas-clientes.php' . ($params ? '?' . http_build_query($params) : '');
}

function accountCurrentActionUrl(): string
{
    $params = $_GET;
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return 'contas-clientes.php' . ($params ? '?' . http_build_query($params) : '');
}

try {
    requireClientAccountAccess('view', $currentNivel);
} catch (RuntimeException $e) {
    http_response_code(403);
    exit('Acesso negado.');
}

$allowedStatus = ['todas', 'em_aberto', 'parcial', 'pago', 'atrasado', 'cancelado'];
$status = (string)($_GET['status'] ?? 'todas');
$status = in_array($status, $allowedStatus, true) ? $status : 'todas';

$filters = [
    'status' => $status,
    'q' => trim((string)($_GET['q'] ?? '')),
    'cliente_id' => trim((string)($_GET['cliente_id'] ?? '')),
    'inicio' => trim((string)($_GET['inicio'] ?? '')),
    'fim' => trim((string)($_GET['fim'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
        }

        $action = (string)($_POST['action'] ?? '');
        $contaId = filter_var($_POST['conta_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($contaId === false || $contaId === null) {
            throw new InvalidArgumentException('Conta inválida.');
        }

        if ($action === 'pay') {
            requireClientAccountAccess('pay', $currentNivel);

            $valorPagoRaw = (string)($_POST['valor_pago'] ?? '0');
            $formaPagamento = (string)($_POST['forma_pagamento'] ?? '');
            $observacao = trim((string)($_POST['observacao'] ?? ''));

            $before = $accountService->details($empresaId, (int)$contaId)['account'];

            $accountService->pay($empresaId, $currentUserId, (int)$contaId, [
                'valor_pago' => $valorPagoRaw,
                'forma_pagamento' => $formaPagamento,
                'observacao' => $observacao,
            ]);

            $valorPago = str_contains($valorPagoRaw, ',')
                ? (float)str_replace(',', '.', str_replace('.', '', $valorPagoRaw))
                : (float)$valorPagoRaw;

            $saldoRestante = max(((float)$before['saldo_aberto']) - $valorPago, 0);

            redirectAccounts('success', 'Pagamento registrado com sucesso.', [
                'cliente' => (string)$before['cliente_nome'],
                'conta_id' => (int)$before['id'],
                'venda_id' => $before['venda_id'],
                'valor_pago' => $valorPago,
                'forma_pagamento' => $formaPagamento,
                'saldo_restante' => $saldoRestante,
                'operador' => (string)($user['nome'] ?? $user['usuario'] ?? $user['email'] ?? 'Operador'),
                'data' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($action === 'settle') {
            requireClientAccountAccess('settle', $currentNivel);

            $formaPagamento = (string)($_POST['forma_pagamento'] ?? 'dinheiro');
            $before = $accountService->details($empresaId, (int)$contaId)['account'];
            $saldo = (float)$before['saldo_aberto'];

            $accountService->settle($empresaId, $currentUserId, (int)$contaId, $formaPagamento);

            redirectAccounts('success', 'Conta quitada com sucesso.', [
                'cliente' => (string)$before['cliente_nome'],
                'conta_id' => (int)$before['id'],
                'venda_id' => $before['venda_id'],
                'valor_pago' => $saldo,
                'forma_pagamento' => $formaPagamento,
                'saldo_restante' => 0,
                'operador' => (string)($user['nome'] ?? $user['usuario'] ?? $user['email'] ?? 'Operador'),
                'data' => date('Y-m-d H:i:s'),
            ]);
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException | RuntimeException $e) {
        redirectAccounts('danger', $e->getMessage());
    } catch (Throwable $e) {
        log_app_exception($e);
        redirectAccounts('danger', 'Não foi possível processar esta conta agora.');
    }
}

$accounts = [];
$summary = [
    'total_aberto' => 0,
    'total_vencido' => 0,
    'total_pago' => 0,
    'contas_abertas' => 0,
    'contas_vencidas' => 0,
    'clientes_com_divida' => 0,
];
$loadError = null;

try {
    $summary = $accountService->summary($empresaId, $filters);
    $accounts = $accountService->list($empresaId, $filters);
} catch (Throwable $e) {
    log_app_exception($e);
    $loadError = 'Não foi possível carregar as contas de clientes agora.';
}

$flash = $_SESSION['account_flash'] ?? null;
unset($_SESSION['account_flash']);

$receipt = is_array($flash['receipt'] ?? null) ? $flash['receipt'] : [];
$actionUrl = accountCurrentActionUrl();

$totalAberto = (float)($summary['total_aberto'] ?? 0);
$totalVencido = (float)($summary['total_vencido'] ?? 0);
$totalPago = (float)($summary['total_pago'] ?? 0);
$qtdAbertas = (int)($summary['contas_abertas'] ?? 0);
$qtdVencidas = (int)($summary['contas_vencidas'] ?? 0);
$qtdClientesDivida = (int)($summary['clientes_com_divida'] ?? 0);

$pageId = 'contas-clientes-server';
$pageTitle = 'Contas de Clientes';
$activeMenu = 'clientes';

require_once __DIR__ . '/layout/header.php';
?>

<style>
  .account-page {
    display: grid;
    gap: 18px;
  }

  .account-alert {
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid var(--line);
    font-size: 13px;
    font-weight: 850;
  }

  .account-alert.success {
    color: var(--green);
    background: rgba(37,196,132,.10);
    border-color: rgba(37,196,132,.25);
  }

  .account-alert.danger {
    color: var(--red);
    background: rgba(230,83,103,.10);
    border-color: rgba(230,83,103,.25);
  }

  .account-alert .alert-actions {
    margin-top: 10px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .account-hero {
    display: grid;
    gap: 14px;
  }

  .account-hero-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 18px;
    border-radius: 24px;
    border: 1px solid var(--line);
    background: #fff;
    box-shadow: 0 10px 28px rgba(29,55,95,.055);
  }

  .account-hero-card h2 {
    margin: 0;
    color: var(--ink);
    font-size: 23px;
    line-height: 1.08;
  }

  .account-hero-card p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
  }

  .account-hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .account-filter-panel,
  .account-summary-card,
  .account-card,
  .account-table-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 24px;
    box-shadow: 0 10px 28px rgba(29,55,95,.055);
  }

  .account-filter-panel {
    overflow: hidden;
  }

  .account-filter-header {
    padding: 16px 18px;
    border-bottom: 1px solid var(--line);
  }

  .account-filter-header h3 {
    margin: 0;
    color: var(--ink);
    font-size: 18px;
  }

  .account-filter-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .account-filter-body {
    display: grid;
    gap: 14px;
    padding: 16px 18px 18px;
  }

  .account-status-pills {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
  }

  .account-status-pills::-webkit-scrollbar {
    display: none;
  }

  .account-status-pills a {
    white-space: nowrap;
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    padding: 0 14px;
    color: var(--muted);
    background: #F7FAFE;
    border: 1px solid var(--line);
    border-radius: 999px;
    font-size: 12px;
    font-weight: 850;
    text-decoration: none;
  }

  .account-status-pills a.active {
    color: var(--blue);
    background: var(--blue-soft);
    border-color: var(--blue-line);
  }

  .account-filter-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(160px, .8fr) minmax(160px, .8fr) auto;
    gap: 12px;
    align-items: end;
  }

  .account-filter-grid .secondary-btn {
    min-height: 54px;
    min-width: 130px;
  }

  .account-summary-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0,1fr));
    gap: 12px;
  }

  .account-summary-card {
    position: relative;
    min-height: 104px;
    padding: 16px;
    overflow: hidden;
  }

  .account-summary-card::after {
    content: "";
    position: absolute;
    right: -28px;
    top: -28px;
    width: 82px;
    height: 82px;
    border-radius: 999px;
    background: rgba(22,87,167,.08);
  }

  .account-summary-card.success::after {
    background: rgba(37,196,132,.12);
  }

  .account-summary-card.warning::after {
    background: rgba(245,158,11,.13);
  }

  .account-summary-card.danger::after {
    background: rgba(230,83,103,.13);
  }

  .account-summary-card span {
    display: block;
    color: var(--muted);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
  }

  .account-summary-card strong {
    display: block;
    margin-top: 8px;
    color: var(--ink);
    font-size: 22px;
    line-height: 1.08;
    font-weight: 950;
    word-break: break-word;
  }

  .account-summary-card small {
    display: block;
    margin-top: 6px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 750;
  }

  .account-section-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
  }

  .account-section-header h2 {
    margin: 0;
    color: var(--ink);
    font-size: 22px;
  }

  .account-section-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .account-desktop-table {
    display: block;
  }

  .account-mobile-list {
    display: none;
  }

  .account-table-card {
    overflow: hidden;
  }

  .account-table-wrap {
    width: 100%;
    overflow-x: auto;
  }

  .account-table {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
  }

  .account-table th {
    padding: 14px 16px;
    text-align: left;
    color: var(--muted);
    background: #F8FBFF;
    border-bottom: 1px solid var(--line);
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
    white-space: nowrap;
  }

  .account-table td {
    padding: 15px 16px;
    border-bottom: 1px solid var(--line);
    color: var(--ink);
    font-size: 13px;
    font-weight: 750;
    vertical-align: middle;
  }

  .account-table tr:last-child td {
    border-bottom: 0;
  }

  .account-table tr:hover td {
    background: #F8FBFF;
  }

  .account-client-cell {
    min-width: 220px;
  }

  .account-client-cell strong {
    display: block;
    color: var(--ink);
    font-size: 14px;
    font-weight: 950;
  }

  .account-client-cell span {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .account-ref-cell strong {
    display: block;
    color: var(--blue);
    font-weight: 950;
  }

  .account-ref-cell span {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 12px;
  }

  .account-money-cell {
    white-space: nowrap;
    font-weight: 950;
  }

  .account-actions-cell {
    width: 250px;
  }

  .account-inline-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .account-inline-actions .primary-btn,
  .account-inline-actions .secondary-btn {
    min-height: 38px;
    min-width: 82px;
    padding: 0 12px;
    font-size: 12px;
    border: 0;
    cursor: pointer;
    text-decoration: none;
  }

  .account-card {
    padding: 17px;
  }

  .account-card-header {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
  }

  .account-card-meta {
    display: grid;
    gap: 5px;
    min-width: 0;
  }

  .account-card-meta h3 {
    margin: 0;
    color: var(--ink);
    font-size: 19px;
    line-height: 1.15;
  }

  .account-card-meta p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
    line-height: 1.45;
  }

  .account-card-meta .account-reference {
    color: var(--blue);
    font-weight: 850;
  }

  .account-values {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 10px;
    margin-top: 15px;
  }

  .account-values div {
    padding: 13px;
    border-radius: 17px;
    background: #F8FBFF;
    border: 1px solid var(--line);
    min-width: 0;
  }

  .account-values span {
    display: block;
    color: var(--muted);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .025em;
  }

  .account-values strong {
    display: block;
    margin-top: 5px;
    color: var(--ink);
    font-size: 16px;
  }

  .account-card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
  }

  .account-card-actions .primary-btn,
  .account-card-actions .secondary-btn {
    min-height: 46px;
    flex: 1 1 160px;
    text-align: center;
    border: 0;
    cursor: pointer;
    text-decoration: none;
  }

  .account-card-actions .secondary-btn:disabled,
  .account-card-actions .primary-btn:disabled,
  .account-inline-actions .secondary-btn:disabled,
  .account-inline-actions .primary-btn:disabled {
    opacity: .55;
    cursor: not-allowed;
  }

  .badge.muted {
    color: var(--muted);
    background: #F3F5F8;
    border-color: var(--line);
  }

  .account-empty {
    padding: 22px;
    color: var(--muted);
    font-weight: 800;
    text-align: center;
  }

  .account-modal[hidden] {
    display: none !important;
  }

  .account-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
  }

  .account-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15,23,42,.46);
    backdrop-filter: blur(2px);
  }

  .account-modal-dialog {
    position: relative;
    width: min(100%, 720px);
    background: #fff;
    border-radius: 26px;
    border: 1px solid var(--line);
    box-shadow: 0 24px 60px rgba(15,23,42,.22);
    overflow: hidden;
    z-index: 1;
  }

  .account-modal-dialog.small {
    width: min(100%, 560px);
  }

  .account-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 19px 21px;
    border-bottom: 1px solid var(--line);
  }

  .account-modal-header h2 {
    margin: 4px 0 0;
    color: var(--ink);
    font-size: 24px;
    line-height: 1.1;
  }

  .account-modal-body {
    padding: 20px;
    display: grid;
    gap: 16px;
  }

  .modal-account-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 10px;
  }

  .modal-account-summary div {
    padding: 14px;
    border: 1px solid var(--line);
    border-radius: 17px;
    background: #F8FBFF;
    min-width: 0;
  }

  .modal-account-summary span {
    display: block;
    color: var(--muted);
    font-size: 11px;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: .03em;
  }

  .modal-account-summary strong {
    display: block;
    margin-top: 6px;
    font-size: 16px;
    color: var(--ink);
    overflow-wrap: anywhere;
  }

  .account-modal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 12px;
  }

  .account-modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-wrap: wrap;
  }

  .account-modal-actions .primary-btn,
  .account-modal-actions .secondary-btn {
    min-width: 160px;
    min-height: 46px;
  }

  .receipt-print-area {
    background: #fff;
    color: #111;
    border-radius: 16px;
    border: 1px dashed var(--line);
    padding: 18px;
  }

  .receipt-print-area h2 {
    margin: 0 0 8px;
    font-size: 19px;
    text-align: center;
  }

  .receipt-print-area p {
    margin: 5px 0;
    font-size: 12px;
  }

  .receipt-line {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    border-top: 1px dashed #bbb;
    padding-top: 8px;
    margin-top: 8px;
  }

  body.modal-open {
    overflow: hidden;
  }

  @media (max-width: 1180px) {
    .account-summary-grid {
      grid-template-columns: repeat(3, minmax(0,1fr));
    }

    .account-filter-grid {
      grid-template-columns: repeat(2, minmax(0,1fr));
    }

    .account-filter-grid .filter-button {
      grid-column: span 2;
    }
  }

  @media (max-width: 860px) {
    .account-hero-card {
      align-items: flex-start;
      flex-direction: column;
    }

    .account-hero-actions {
      width: 100%;
      justify-content: stretch;
    }

    .account-hero-actions .secondary-btn {
      width: 100%;
    }

    .account-desktop-table {
      display: none;
    }

    .account-mobile-list {
      display: grid;
      gap: 14px;
    }
  }

  @media (max-width: 760px) {
    .account-summary-grid,
    .account-values,
    .modal-account-summary,
    .account-modal-grid {
      grid-template-columns: 1fr;
    }

    .account-filter-grid {
      grid-template-columns: 1fr;
    }

    .account-filter-grid .filter-button {
      grid-column: auto;
    }

    .account-card-header {
      flex-direction: column;
    }

    .account-card-actions {
      flex-direction: column;
    }

    .account-modal {
      padding: 12px;
      align-items: flex-end;
    }

    .account-modal-dialog,
    .account-modal-dialog.small {
      width: 100%;
      border-radius: 24px 24px 0 0;
      max-height: 92vh;
      overflow-y: auto;
    }

    .account-modal-actions {
      flex-direction: column;
    }

    .account-modal-actions .primary-btn,
    .account-modal-actions .secondary-btn {
      width: 100%;
      min-width: 0;
    }
  }

  @media (max-width: 430px) {
    .account-page {
      gap: 14px;
    }

    .account-hero-card,
    .account-filter-body,
    .account-filter-header {
      padding: 15px;
    }

    .account-hero-card h2 {
      font-size: 20px;
    }

    .account-summary-card strong {
      font-size: 20px;
    }

    .account-card {
      padding: 15px;
    }
  }

  @media print {
    body * {
      visibility: hidden;
    }

    .receipt-print-area,
    .receipt-print-area * {
      visibility: visible;
    }

    .receipt-print-area {
      position: absolute;
      left: 0;
      top: 0;
      width: 100%;
      border: 0;
      border-radius: 0;
      padding: 0;
      margin: 0;
    }

    .no-print {
      display: none !important;
    }
  }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Fiado e pagamentos</p>
      <h1>Contas de Clientes</h1>
    </div>

    <a class="icon-btn light no-print" href="clientes.php" aria-label="Voltar para clientes">‹</a>
  </div>
</header>

<section class="content-pad">
  <div class="account-page">
    <?php if ($receipt): ?>
      <div class="account-alert success no-print" role="status">
        <?= e((string)($flash['message'] ?? 'Pagamento registrado com sucesso.')) ?>

        <div class="alert-actions">
          <button type="button" class="secondary-btn" id="openReceiptModalBtn">Ver recibo</button>
        </div>
      </div>
    <?php elseif (is_array($flash)): ?>
      <div class="account-alert <?= e((string)($flash['type'] ?? 'danger')) ?>" role="status">
        <?= e((string)($flash['message'] ?? '')) ?>
      </div>
    <?php endif; ?>

    <?php if ($loadError !== null): ?>
      <div class="account-alert danger" role="alert"><?= e($loadError) ?></div>
    <?php endif; ?>

    <section class="account-hero">
      <article class="account-hero-card">
        <div>
          <h2>Gestão de contas e fiado</h2>
          <p>Controle pagamentos parciais, quitações, vencimentos e recibos de clientes.</p>
        </div>

        <div class="account-hero-actions no-print">
          <a class="secondary-btn" href="clientes.php">Ver clientes</a>
        </div>
      </article>
    </section>

    <section class="account-summary-grid">
      <article class="account-summary-card warning">
        <span>Total em aberto</span>
        <strong><?= e(accountMoney($totalAberto)) ?></strong>
        <small><?= $qtdAbertas ?> conta<?= $qtdAbertas === 1 ? '' : 's' ?> em aberto</small>
      </article>

      <article class="account-summary-card <?= $totalVencido > 0 ? 'danger' : 'success' ?>">
        <span>Total vencido</span>
        <strong><?= e(accountMoney($totalVencido)) ?></strong>
        <small><?= $qtdVencidas ?> conta<?= $qtdVencidas === 1 ? '' : 's' ?> vencida<?= $qtdVencidas === 1 ? '' : 's' ?></small>
      </article>

      <article class="account-summary-card success">
        <span>Total pago</span>
        <strong><?= e(accountMoney($totalPago)) ?></strong>
        <small>Recebimentos registrados</small>
      </article>

      <article class="account-summary-card">
        <span>Contas abertas</span>
        <strong><?= $qtdAbertas ?></strong>
        <small>Não quitadas</small>
      </article>

      <article class="account-summary-card <?= $qtdVencidas > 0 ? 'danger' : '' ?>">
        <span>Contas vencidas</span>
        <strong><?= $qtdVencidas ?></strong>
        <small>Exigem atenção</small>
      </article>

      <article class="account-summary-card">
        <span>Clientes com dívida</span>
        <strong><?= $qtdClientesDivida ?></strong>
        <small>Com saldo pendente</small>
      </article>
    </section>

    <form class="account-filter-panel no-print" method="get" action="contas-clientes.php">
      <div class="account-filter-header">
        <h3>Filtros</h3>
        <p>Busque por cliente, status ou período de vencimento.</p>
      </div>

      <div class="account-filter-body">
        <nav class="account-status-pills" aria-label="Status das contas">
          <?php foreach (['todas' => 'Todas', 'em_aberto' => 'Em aberto', 'parcial' => 'Parcial', 'atrasado' => 'Atrasadas', 'pago' => 'Pagas', 'cancelado' => 'Canceladas'] as $key => $label): ?>
            <a class="<?= $status === $key ? 'active' : '' ?>" href="<?= e(accountFilterUrl(['status' => $key])) ?>">
              <?= e($label) ?>
            </a>
          <?php endforeach; ?>
        </nav>

        <input type="hidden" name="status" value="<?= e($status) ?>">

        <div class="account-filter-grid">
          <label class="field">
            <span>Buscar cliente</span>
            <input type="search" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Nome, telefone ou CPF/CNPJ">
          </label>

          <label class="field">
            <span>Início vencimento</span>
            <input type="date" name="inicio" value="<?= e((string)$filters['inicio']) ?>">
          </label>

          <label class="field">
            <span>Fim vencimento</span>
            <input type="date" name="fim" value="<?= e((string)$filters['fim']) ?>">
          </label>

          <button class="secondary-btn filter-button" type="submit">Filtrar</button>
        </div>
      </div>
    </form>

    <section>
      <div class="account-section-header">
        <div>
          <h2>Contas encontradas</h2>
          <p><?= count($accounts) ?> registro<?= count($accounts) === 1 ? '' : 's' ?> no filtro atual.</p>
        </div>
      </div>
    </section>

    <section class="account-desktop-table">
      <div class="account-table-card">
        <?php if (!$accounts && $loadError === null): ?>
          <div class="account-empty">Nenhuma conta encontrada.</div>
        <?php else: ?>
          <div class="account-table-wrap">
            <table class="account-table">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Conta</th>
                  <th>Vencimento</th>
                  <th>Status</th>
                  <th>Valor original</th>
                  <th>Valor pago</th>
                  <th>Saldo</th>
                  <th>Ações</th>
                </tr>
              </thead>

              <tbody>
                <?php foreach ($accounts as $account): ?>
                  <?php
                    $visualStatus = (string)($account['status_visual'] ?? $account['status']);
                    $canReceive = canClientAccountAccess('pay', $currentNivel)
                      && !in_array($account['status'], ['pago', 'cancelado'], true)
                      && (float)$account['saldo_aberto'] > 0;

                    $contaId = (int)$account['id'];
                    $vendaId = (int)($account['venda_id'] ?? 0);
                    $saldoNumber = number_format((float)$account['saldo_aberto'], 2, '.', '');
                  ?>
                  <tr>
                    <td class="account-client-cell">
                      <strong><?= e((string)$account['cliente_nome']) ?></strong>
                      <span><?= e((string)($account['cliente_telefone'] ?: 'Sem telefone')) ?> · <?= e((string)($account['cliente_documento'] ?: 'Sem CPF/CNPJ')) ?></span>
                    </td>

                    <td class="account-ref-cell">
                      <strong>#<?= $contaId ?></strong>
                      <span><?= $vendaId > 0 ? 'Venda #' . $vendaId : 'Sem venda vinculada' ?></span>
                    </td>

                    <td><?= e(accountDate($account['vencimento'])) ?></td>

                    <td>
                      <span class="badge <?= e(accountStatusClass($visualStatus)) ?>">
                        <?= e(accountStatusLabel($visualStatus)) ?>
                      </span>
                    </td>

                    <td class="account-money-cell"><?= e(accountMoney($account['valor_original'])) ?></td>
                    <td class="account-money-cell"><?= e(accountMoney($account['valor_pago'])) ?></td>
                    <td class="account-money-cell"><?= e(accountMoney($account['saldo_aberto'])) ?></td>

                    <td class="account-actions-cell no-print">
                      <div class="account-inline-actions">
                        <?php if ($vendaId > 0): ?>
                          <a class="secondary-btn" href="venda-detalhes.php?id=<?= $vendaId ?>">Venda</a>
                        <?php endif; ?>

                        <?php if ($canReceive): ?>
                          <button
                            type="button"
                            class="secondary-btn open-payment-modal"
                            data-conta-id="<?= $contaId ?>"
                            data-cliente="<?= e((string)$account['cliente_nome']) ?>"
                            data-venda-id="<?= $vendaId ?>"
                            data-saldo="<?= e($saldoNumber) ?>"
                            data-saldo-formatado="<?= e(accountMoney($account['saldo_aberto'])) ?>"
                          >Pagar</button>

                          <?php if (canClientAccountAccess('settle', $currentNivel)): ?>
                            <button
                              type="button"
                              class="primary-btn open-settle-modal"
                              data-conta-id="<?= $contaId ?>"
                              data-cliente="<?= e((string)$account['cliente_nome']) ?>"
                              data-venda-id="<?= $vendaId ?>"
                              data-saldo="<?= e($saldoNumber) ?>"
                              data-saldo-formatado="<?= e(accountMoney($account['saldo_aberto'])) ?>"
                            >Quitar</button>
                          <?php endif; ?>
                        <?php elseif (in_array($account['status'], ['pago'], true) || (float)$account['saldo_aberto'] <= 0): ?>
                          <button type="button" class="secondary-btn" disabled>Paga</button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="account-mobile-list">
      <?php if (!$accounts && $loadError === null): ?>
        <article class="account-card account-empty">Nenhuma conta encontrada.</article>
      <?php endif; ?>

      <?php foreach ($accounts as $account): ?>
        <?php
          $visualStatus = (string)($account['status_visual'] ?? $account['status']);
          $canReceive = canClientAccountAccess('pay', $currentNivel)
            && !in_array($account['status'], ['pago', 'cancelado'], true)
            && (float)$account['saldo_aberto'] > 0;

          $contaId = (int)$account['id'];
          $vendaId = (int)($account['venda_id'] ?? 0);
          $saldoNumber = number_format((float)$account['saldo_aberto'], 2, '.', '');
        ?>
        <article class="account-card">
          <div class="account-card-header">
            <div class="account-card-meta">
              <h3><?= e((string)$account['cliente_nome']) ?></h3>
              <p><?= e((string)($account['cliente_telefone'] ?: 'Sem telefone')) ?> · <?= e((string)($account['cliente_documento'] ?: 'Sem CPF/CNPJ')) ?></p>
              <p class="account-reference">Conta #<?= $contaId ?><?= $vendaId > 0 ? ' · Venda #' . $vendaId : '' ?> · Vencimento <?= e(accountDate($account['vencimento'])) ?></p>
            </div>

            <span class="badge <?= e(accountStatusClass($visualStatus)) ?>">
              <?= e(accountStatusLabel($visualStatus)) ?>
            </span>
          </div>

          <div class="account-values">
            <div><span>Valor original</span><strong><?= e(accountMoney($account['valor_original'])) ?></strong></div>
            <div><span>Valor pago</span><strong><?= e(accountMoney($account['valor_pago'])) ?></strong></div>
            <div><span>Saldo aberto</span><strong><?= e(accountMoney($account['saldo_aberto'])) ?></strong></div>
          </div>

          <div class="account-card-actions no-print">
            <?php if ($vendaId > 0): ?>
              <a class="secondary-btn" href="venda-detalhes.php?id=<?= $vendaId ?>">Ver venda</a>
            <?php endif; ?>

            <?php if ($canReceive): ?>
              <button
                type="button"
                class="secondary-btn open-payment-modal"
                data-conta-id="<?= $contaId ?>"
                data-cliente="<?= e((string)$account['cliente_nome']) ?>"
                data-venda-id="<?= $vendaId ?>"
                data-saldo="<?= e($saldoNumber) ?>"
                data-saldo-formatado="<?= e(accountMoney($account['saldo_aberto'])) ?>"
              >Registrar pagamento</button>

              <?php if (canClientAccountAccess('settle', $currentNivel)): ?>
                <button
                  type="button"
                  class="primary-btn open-settle-modal"
                  data-conta-id="<?= $contaId ?>"
                  data-cliente="<?= e((string)$account['cliente_nome']) ?>"
                  data-venda-id="<?= $vendaId ?>"
                  data-saldo="<?= e($saldoNumber) ?>"
                  data-saldo-formatado="<?= e(accountMoney($account['saldo_aberto'])) ?>"
                >Quitar conta</button>
              <?php endif; ?>
            <?php elseif (in_array($account['status'], ['pago'], true) || (float)$account['saldo_aberto'] <= 0): ?>
              <button type="button" class="secondary-btn" disabled>Conta paga</button>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </div>
</section>

<div class="account-modal" id="paymentModal" hidden>
  <div class="account-modal-backdrop" data-close-modal></div>

  <div class="account-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
    <div class="account-modal-header">
      <div>
        <p class="micro-label dark-text">Recebimento</p>
        <h2 id="paymentModalTitle">Registrar pagamento</h2>
      </div>

      <button type="button" class="icon-btn light" data-close-modal aria-label="Fechar">×</button>
    </div>

    <div class="account-modal-body">
      <div class="modal-account-summary">
        <div><span>Cliente</span><strong id="paymentCliente">-</strong></div>
        <div><span>Conta / Venda</span><strong id="paymentContaInfo">-</strong></div>
        <div><span>Saldo em aberto</span><strong id="paymentSaldo">R$ 0,00</strong></div>
      </div>

      <form method="post" id="paymentForm" action="<?= e($actionUrl) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="pay">
        <input type="hidden" name="conta_id" id="paymentContaId" value="">

        <div class="account-modal-grid">
          <label class="field">
            <span>Valor pago</span>
            <input type="text" name="valor_pago" id="paymentValor" inputmode="decimal" placeholder="0,00" required>
          </label>

          <label class="field">
            <span>Forma de pagamento</span>
            <select name="forma_pagamento" required>
              <option value="pix">PIX</option>
              <option value="dinheiro">Dinheiro</option>
              <option value="credito">Crédito</option>
              <option value="debito">Débito</option>
              <option value="transferencia">Transferência</option>
              <option value="outro">Outro</option>
            </select>
          </label>
        </div>

        <label class="field">
          <span>Observação</span>
          <input type="text" name="observacao" maxlength="255" placeholder="Opcional">
        </label>

        <div class="account-modal-actions no-print">
          <button type="button" class="secondary-btn" data-close-modal>Cancelar</button>
          <button type="submit" class="primary-btn">Salvar pagamento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="account-modal" id="settleModal" hidden>
  <div class="account-modal-backdrop" data-close-modal></div>

  <div class="account-modal-dialog small" role="dialog" aria-modal="true" aria-labelledby="settleModalTitle">
    <div class="account-modal-header">
      <div>
        <p class="micro-label dark-text">Quitação</p>
        <h2 id="settleModalTitle">Quitar conta</h2>
      </div>

      <button type="button" class="icon-btn light" data-close-modal aria-label="Fechar">×</button>
    </div>

    <div class="account-modal-body">
      <div class="modal-account-summary">
        <div><span>Cliente</span><strong id="settleCliente">-</strong></div>
        <div><span>Conta / Venda</span><strong id="settleContaInfo">-</strong></div>
        <div><span>Valor a quitar</span><strong id="settleSaldo">R$ 0,00</strong></div>
      </div>

      <form method="post" id="settleForm" action="<?= e($actionUrl) ?>" onsubmit="return confirm('Confirmar quitação desta conta?');">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="settle">
        <input type="hidden" name="conta_id" id="settleContaId" value="">

        <label class="field">
          <span>Forma de pagamento</span>
          <select name="forma_pagamento" required>
            <option value="pix">PIX</option>
            <option value="dinheiro">Dinheiro</option>
            <option value="credito">Crédito</option>
            <option value="debito">Débito</option>
            <option value="transferencia">Transferência</option>
            <option value="outro">Outro</option>
          </select>
        </label>

        <div class="account-modal-actions no-print">
          <button type="button" class="secondary-btn" data-close-modal>Cancelar</button>
          <button type="submit" class="primary-btn">Confirmar quitação</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($receipt): ?>
  <div class="account-modal" id="receiptModal" hidden>
    <div class="account-modal-backdrop" data-close-modal></div>

    <div class="account-modal-dialog small" role="dialog" aria-modal="true" aria-labelledby="receiptModalTitle">
      <div class="account-modal-header no-print">
        <div>
          <p class="micro-label dark-text">Comprovante</p>
          <h2 id="receiptModalTitle">Recibo de pagamento</h2>
        </div>

        <button type="button" class="icon-btn light" data-close-modal aria-label="Fechar">×</button>
      </div>

      <div class="account-modal-body">
        <div class="receipt-print-area" id="receiptPrintArea">
          <h2>Recibo de Pagamento</h2>

          <p><strong>Cliente:</strong> <?= e((string)$receipt['cliente']) ?></p>
          <p><strong>Conta:</strong> #<?= (int)$receipt['conta_id'] ?><?= !empty($receipt['venda_id']) ? ' · Venda #' . (int)$receipt['venda_id'] : '' ?></p>
          <p><strong>Data:</strong> <?= e(accountDateTime($receipt['data'] ?? '')) ?></p>
          <p><strong>Operador:</strong> <?= e((string)$receipt['operador']) ?></p>

          <div class="receipt-line">
            <span>Valor pago</span>
            <strong><?= e(accountMoney($receipt['valor_pago'] ?? 0)) ?></strong>
          </div>

          <div class="receipt-line">
            <span>Forma</span>
            <strong><?= e(accountPaymentLabel((string)($receipt['forma_pagamento'] ?? ''))) ?></strong>
          </div>

          <div class="receipt-line">
            <span>Saldo restante</span>
            <strong><?= e(accountMoney($receipt['saldo_restante'] ?? 0)) ?></strong>
          </div>

          <p style="text-align:center;margin-top:12px;">Obrigado pela preferência.</p>
        </div>

        <div class="account-modal-actions no-print">
          <button type="button" class="secondary-btn" data-close-modal>Fechar</button>
          <button type="button" class="primary-btn" onclick="window.print()">Imprimir recibo</button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const closeModal = (modal) => {
        if (!modal) return;
        modal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('modal-open');
    };

    const openModal = (modal) => {
        if (!modal) return;
        modal.removeAttribute('hidden');
        document.body.classList.add('modal-open');
    };

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', function () {
            closeModal(this.closest('.account-modal'));
        });
    });

    const paymentModal = document.getElementById('paymentModal');
    document.querySelectorAll('.open-payment-modal').forEach((button) => {
        button.addEventListener('click', function () {
            document.getElementById('paymentContaId').value = this.dataset.contaId || '';
            document.getElementById('paymentCliente').textContent = this.dataset.cliente || '-';
            document.getElementById('paymentContaInfo').textContent = 'Conta #' + (this.dataset.contaId || '-') + ((this.dataset.vendaId && this.dataset.vendaId !== '0') ? ' · Venda #' + this.dataset.vendaId : '');
            document.getElementById('paymentSaldo').textContent = this.dataset.saldoFormatado || 'R$ 0,00';
            document.getElementById('paymentValor').value = '';
            openModal(paymentModal);
            setTimeout(() => document.getElementById('paymentValor')?.focus(), 100);
        });
    });

    const settleModal = document.getElementById('settleModal');
    document.querySelectorAll('.open-settle-modal').forEach((button) => {
        button.addEventListener('click', function () {
            document.getElementById('settleContaId').value = this.dataset.contaId || '';
            document.getElementById('settleCliente').textContent = this.dataset.cliente || '-';
            document.getElementById('settleContaInfo').textContent = 'Conta #' + (this.dataset.contaId || '-') + ((this.dataset.vendaId && this.dataset.vendaId !== '0') ? ' · Venda #' + this.dataset.vendaId : '');
            document.getElementById('settleSaldo').textContent = this.dataset.saldoFormatado || 'R$ 0,00';
            openModal(settleModal);
        });
    });

    const receiptButton = document.getElementById('openReceiptModalBtn');
    const receiptModal = document.getElementById('receiptModal');
    if (receiptButton && receiptModal) {
        receiptButton.addEventListener('click', function () {
            openModal(receiptModal);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.account-modal').forEach((modal) => {
                if (!modal.hasAttribute('hidden')) {
                    closeModal(modal);
                }
            });
        }
    });
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>