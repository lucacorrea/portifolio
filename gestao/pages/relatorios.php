<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;
use App\Services\ReportService;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);
$currentNivel = (string)($user['nivel'] ?? '');

function canReportAccess(string $action, string $nivel): bool
{
    $permissions = [
        'view' => ['admin', 'gerente', 'operador', 'leitor'],
        'export' => ['admin', 'gerente', 'operador', 'leitor'],
        'profit' => ['admin', 'gerente'],
        'operator_sales' => ['admin', 'gerente', 'leitor'],
        'client_debt' => ['admin', 'gerente', 'operador', 'leitor'],
    ];

    return in_array($nivel, $permissions[$action] ?? [], true);
}

function requireReportAccess(string $action, string $nivel): void
{
    if (!canReportAccess($action, $nivel)) {
        throw new RuntimeException('Você não tem permissão para acessar este relatório.');
    }
}

function reportMoney(mixed $value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function reportNumber(mixed $value, int $decimals = 2): string
{
    return number_format((float)$value, $decimals, ',', '.');
}

function reportDate(mixed $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Sem data';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y', $timestamp) : 'Sem data';
}

function reportDateTime(mixed $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Sem data';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Sem data';
}

function reportPaymentLabel(string $method): string
{
    return [
        'pix' => 'PIX',
        'dinheiro' => 'Dinheiro',
        'credito' => 'Crédito',
        'debito' => 'Débito',
        'cartao_credito' => 'Cartão de crédito',
        'cartao_debito' => 'Cartão de débito',
        'conta_cliente' => 'Conta do cliente',
        'misto' => 'Misto',
        'outro' => 'Outro',
        '' => 'Todas',
    ][$method] ?? ucfirst(str_replace('_', ' ', $method));
}

function reportStatusLabel(string $status): string
{
    return [
        'finalizada' => 'Finalizada',
        'pendente' => 'Pendente',
        'cancelada' => 'Cancelada',
        'em_aberto' => 'Em aberto',
        'pago' => 'Pago',
        'parcial' => 'Parcial',
        'atrasado' => 'Atrasado',
        'cancelado' => 'Cancelado',
        '' => 'Todos',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function reportStatusClass(string $status): string
{
    return match ($status) {
        'finalizada', 'pago' => 'green',
        'cancelada', 'cancelado', 'atrasado' => 'red',
        'pendente', 'parcial' => 'orange',
        'em_aberto' => 'blue',
        default => 'muted',
    };
}

function reportUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return 'relatorios.php' . ($params ? '?' . http_build_query($params) : '');
}

$reportService = new ReportService();
$report = null;
$formError = null;

try {
    requireReportAccess('view', $currentNivel);
    $report = $reportService->build($empresaId, $_GET);
} catch (InvalidArgumentException | RuntimeException $e) {
    $formError = $e->getMessage();
} catch (Throwable $e) {
    log_app_exception($e);
    $formError = 'Não foi possível carregar os relatórios agora.';
}

$filters = $report['filters'] ?? [
    'periodo' => 'hoje',
    'inicio' => date('Y-m-d'),
    'fim' => date('Y-m-d'),
    'forma_pagamento' => '',
    'status' => '',
    'usuario_id' => 0,
    'cliente_id' => 0,
    'produto_id' => 0,
];

$summary = $report['summary'] ?? [];
$paymentMethods = $report['paymentMethods'] ?? [];
$productsSold = $report['productsSold'] ?? [];
$operatorSales = $report['operatorSales'] ?? [];
$dailySales = $report['dailySales'] ?? [];
$clientDebtSummary = $report['clientDebtSummary'] ?? [];
$clientDebtRows = $report['clientDebtRows'] ?? [];
$sales = $report['sales'] ?? [];
$options = $report['options'] ?? ['users' => [], 'clients' => [], 'products' => []];

$chartData = $report['chartData'] ?? [
    'payments' => ['labels' => [], 'values' => []],
    'products' => ['labels' => [], 'values' => []],
    'daily' => ['labels' => [], 'values' => []],
];

$totalSales = (float)($summary['total_sales'] ?? 0);
$totalDiscount = (float)($summary['total_discount'] ?? 0);
$salesCount = (int)($summary['sales_count'] ?? 0);
$averageTicket = (float)($summary['average_ticket'] ?? 0);
$canceledCount = (int)($summary['canceled_count'] ?? 0);
$estimatedCost = (float)($summary['estimated_cost'] ?? 0);
$estimatedProfit = (float)($summary['estimated_profit'] ?? 0);
$estimatedMargin = (float)($summary['estimated_margin'] ?? 0);

$canProfit = canReportAccess('profit', $currentNivel);
$canOperatorSales = canReportAccess('operator_sales', $currentNivel);
$canClientDebt = canReportAccess('client_debt', $currentNivel);
$canExport = canReportAccess('export', $currentNivel);

$paymentTotals = [];
foreach ($paymentMethods as $row) {
    $paymentTotals[(string)($row['metodo'] ?? '')] = (float)($row['total_value'] ?? 0);
}

$pageId = 'relatorios-server';
$pageTitle = 'Relatórios';
$activeMenu = '';

require_once __DIR__ . '/layout/header.php';
?>

<style>
  .report-page {
    display: grid;
    gap: 18px;
    padding-bottom: 118px;
  }

  .report-alert {
    padding: 14px 16px;
    color: var(--red);
    background: rgba(230,83,103,.10);
    border: 1px solid rgba(230,83,103,.25);
    border-radius: 18px;
    font-size: 13px;
    font-weight: 850;
  }

  .report-hero,
  .report-filter-card,
  .report-card,
  .report-chart-card,
  .report-table-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 24px;
    box-shadow: 0 10px 28px rgba(29,55,95,.055);
  }

  .report-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 20px;
  }

  .report-hero h2 {
    margin: 0;
    color: var(--ink);
    font-size: 24px;
    line-height: 1.08;
  }

  .report-hero p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
    line-height: 1.4;
  }

  .report-hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .report-hero-actions .primary-btn,
  .report-hero-actions .secondary-btn {
    min-height: 42px;
    padding: 0 16px;
    text-decoration: none;
    white-space: nowrap;
  }

  .report-filter-card {
    overflow: hidden;
  }

  .report-filter-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 17px 18px;
    border-bottom: 1px solid var(--line);
  }

  .report-filter-header h3 {
    margin: 0;
    color: var(--ink);
    font-size: 18px;
  }

  .report-filter-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .report-filter-body {
    display: grid;
    gap: 15px;
    padding: 17px 18px 18px;
  }

  .report-periods {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
  }

  .report-periods::-webkit-scrollbar {
    display: none;
  }

  .report-periods a {
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

  .report-periods a.active {
    color: var(--blue);
    background: var(--blue-soft);
    border-color: var(--blue-line);
  }

  .report-filter-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    align-items: end;
  }

  .report-filter-grid .wide {
    grid-column: span 2;
  }

  .report-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 2px;
  }

  .report-actions .primary-btn,
  .report-actions .secondary-btn {
    min-height: 42px;
    min-width: 124px;
    padding: 0 16px;
    border: 0;
    cursor: pointer;
    text-decoration: none;
  }

  .report-section-title {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
  }

  .report-section-title h2 {
    margin: 0;
    color: var(--ink);
    font-size: 23px;
    line-height: 1.1;
  }

  .report-section-title p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .report-summary-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .report-card {
    position: relative;
    min-height: 104px;
    padding: 16px;
    min-width: 0;
    overflow: hidden;
  }

  .report-card::after {
    content: "";
    position: absolute;
    right: -28px;
    top: -28px;
    width: 82px;
    height: 82px;
    border-radius: 999px;
    background: rgba(22,87,167,.08);
  }

  .report-card.success::after {
    background: rgba(37,196,132,.12);
  }

  .report-card.warning::after {
    background: rgba(245,158,11,.13);
  }

  .report-card.danger::after {
    background: rgba(230,83,103,.13);
  }

  .report-card span {
    display: block;
    color: var(--muted);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
  }

  .report-card strong {
    display: block;
    margin-top: 8px;
    color: var(--ink);
    font-size: 22px;
    line-height: 1.08;
    font-weight: 950;
    letter-spacing: 0;
    word-break: break-word;
  }

  .report-card small {
    display: block;
    margin-top: 6px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 750;
    line-height: 1.25;
  }

  .report-chart-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
  }

  .report-chart-card {
    padding: 16px;
    min-height: 292px;
    overflow: hidden;
  }

  .report-chart-card h3 {
    margin: 0;
    color: var(--ink);
    font-size: 17px;
  }

  .report-chart-card p {
    margin: 4px 0 12px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 750;
  }

  .report-chart-box {
    height: 225px;
    position: relative;
  }

  .report-chart-box canvas {
    width: 100% !important;
    height: 100% !important;
  }

  .report-table-card {
    padding: 16px;
    overflow: hidden;
  }

  .report-table-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .report-table-head h3 {
    margin: 0;
    color: var(--ink);
    font-size: 18px;
  }

  .report-table-head p {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .report-table-scroll {
    overflow-x: auto;
    border-radius: 18px;
    border: 1px solid var(--line);
  }

  .report-table {
    width: 100%;
    min-width: 780px;
    border-collapse: collapse;
    font-size: 12px;
    background: #fff;
  }

  .report-table th {
    padding: 13px 14px;
    color: var(--muted);
    background: #F8FBFF;
    border-bottom: 1px solid var(--line);
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .03em;
    white-space: nowrap;
  }

  .report-table td {
    padding: 13px 14px;
    border-bottom: 1px solid var(--line);
    text-align: left;
    vertical-align: middle;
    color: var(--ink);
    font-weight: 750;
  }

  .report-table tr:last-child td {
    border-bottom: 0;
  }

  .report-table tbody tr:hover td {
    background: #F8FBFF;
  }

  .report-table td strong {
    color: var(--ink);
    font-weight: 950;
  }

  .report-table .money {
    white-space: nowrap;
    font-weight: 950;
  }

  .report-table .muted {
    color: var(--muted);
    font-size: 11px;
    font-weight: 750;
  }

  .report-table a {
    color: var(--blue);
    font-weight: 850;
    text-decoration: none;
  }

  .report-empty {
    margin: 0;
    padding: 16px;
    color: var(--muted);
    background: #F8FBFF;
    border: 1px dashed var(--blue-line);
    border-radius: 16px;
    font-size: 13px;
    font-weight: 800;
    text-align: center;
  }

  .report-print-note {
    color: var(--muted);
    font-size: 11px;
    font-weight: 750;
    margin: 12px 0 0;
  }

  .report-mobile-sales {
    display: none;
  }

  .report-sale-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 20px;
    box-shadow: 0 8px 22px rgba(29,55,95,.045);
    padding: 15px;
  }

  .report-sale-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }

  .report-sale-card h4 {
    margin: 0;
    color: var(--ink);
    font-size: 16px;
    line-height: 1.2;
  }

  .report-sale-card p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.35;
    font-weight: 750;
  }

  .report-sale-values {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: 12px;
  }

  .report-sale-values div {
    padding: 10px;
    border-radius: 14px;
    border: 1px solid var(--line);
    background: #F8FBFF;
  }

  .report-sale-values span {
    display: block;
    color: var(--muted);
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
  }

  .report-sale-values strong {
    display: block;
    margin-top: 5px;
    color: var(--ink);
    font-size: 13px;
    font-weight: 950;
  }

  .report-sale-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-top: 12px;
  }

  .report-sale-actions .secondary-btn,
  .report-sale-actions .primary-btn {
    width: 100%;
    min-height: 40px;
    padding: 8px 10px;
    border-radius: 14px;
    text-align: center;
    text-decoration: none;
    font-size: 12px;
  }

  .badge.muted {
    color: var(--muted);
    background: #F3F5F8;
    border-color: var(--line);
  }

  @media (max-width: 1240px) {
    .report-chart-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .report-chart-grid .report-chart-card:last-child {
      grid-column: 1 / -1;
    }
  }

  @media (max-width: 980px) {
    .report-summary-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .report-filter-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .report-filter-grid .wide {
      grid-column: span 2;
    }
  }

  @media (max-width: 760px) {
    .report-page {
      gap: 15px;
      padding-bottom: 135px;
    }

    .report-hero {
      align-items: flex-start;
      flex-direction: column;
      padding: 16px;
    }

    .report-hero h2 {
      font-size: 21px;
    }

    .report-hero-actions {
      width: 100%;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .report-hero-actions .primary-btn,
    .report-hero-actions .secondary-btn {
      width: 100%;
      min-width: 0;
      min-height: 40px;
      font-size: 12px;
      padding: 8px 10px;
    }

    .report-filter-header,
    .report-filter-body,
    .report-table-card,
    .report-chart-card {
      padding: 15px;
    }

    .report-filter-grid {
      grid-template-columns: 1fr;
    }

    .report-filter-grid .wide {
      grid-column: auto;
    }

    .report-actions {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }

    .report-actions .primary-btn,
    .report-actions .secondary-btn {
      width: 100%;
      min-width: 0;
      min-height: 40px;
      font-size: 12px;
      padding: 8px 10px;
    }

    .report-actions .primary-btn {
      grid-column: 1 / -1;
    }

    .report-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .report-card {
      min-height: 92px;
      padding: 14px;
      border-radius: 20px;
    }

    .report-card span {
      font-size: 9px;
    }

    .report-card strong {
      font-size: 18px;
      line-height: 1.1;
    }

    .report-card small {
      font-size: 10px;
      line-height: 1.25;
    }

    .report-chart-grid {
      grid-template-columns: 1fr;
    }

    .report-chart-grid .report-chart-card:last-child {
      grid-column: auto;
    }

    .report-chart-card {
      min-height: 260px;
    }

    .report-chart-box {
      height: 200px;
    }

    .report-section-title {
      align-items: flex-start;
      flex-direction: column;
    }

    .report-table-head {
      flex-direction: column;
    }

    .report-mobile-sales {
      display: grid;
      gap: 12px;
    }

    .report-sales-table-desktop {
      display: none;
    }

    .content-pad {
      padding-bottom: 130px;
    }
  }

  @media (max-width: 430px) {
    .report-page {
      gap: 14px;
    }

    .report-summary-grid {
      gap: 8px;
    }

    .report-card {
      min-height: 84px;
      padding: 12px;
      border-radius: 18px;
    }

    .report-card strong {
      font-size: 16px;
    }

    .report-sale-values {
      grid-template-columns: 1fr;
    }

    .report-sale-actions {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 340px) {
    .report-summary-grid,
    .report-actions,
    .report-hero-actions {
      grid-template-columns: 1fr;
    }
  }

  @media print {
    .no-print,
    .bottom-nav,
    .app-sidebar,
    .mobile-topbar {
      display: none !important;
    }

    body {
      background: #fff !important;
    }

    .content-pad {
      padding: 0 !important;
    }

    .report-page {
      padding-bottom: 0 !important;
      gap: 12px !important;
    }

    .report-hero,
    .report-card,
    .report-table-card,
    .report-filter-card,
    .report-chart-card {
      box-shadow: none !important;
      border: 1px solid #ddd !important;
      break-inside: avoid;
    }

    .report-filter-card {
      display: none !important;
    }

    .report-chart-card {
      min-height: auto;
    }

    .report-chart-box {
      height: 210px;
    }
  }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Indicadores</p>
      <h1>Relatórios</h1>
    </div>

```
<?php if ($canExport): ?>
  <button class="icon-btn light no-print" type="button" onclick="window.print()" aria-label="Imprimir relatório">⎙</button>
<?php endif; ?>
```

  </div>
</header>

<section class="content-pad">
  <div class="report-page">
    <?php if ($formError !== null): ?>
      <div class="report-alert" role="alert"><?= e($formError) ?></div>
    <?php endif; ?>

```
<?php if ($formError === null): ?>
  <section class="report-hero">
    <div>
      <h2>Relatório financeiro</h2>
      <p>
        Período:
        <?= e(reportDate($filters['inicio'] ?? '')) ?>
        até
        <?= e(reportDate($filters['fim'] ?? '')) ?>
      </p>
    </div>

    <div class="report-hero-actions no-print">
      <a class="secondary-btn" href="historico-vendas.php">Histórico</a>
      <a class="primary-btn" href="nova-venda.php">Nova venda</a>
    </div>
  </section>

  <form class="report-filter-card no-print" method="get" id="reportFilterForm">
    <div class="report-filter-header">
      <div>
        <h3>Filtros</h3>
        <p>Refine por período, forma de pagamento, status, operador, cliente ou produto.</p>
      </div>
    </div>

    <div class="report-filter-body">
      <div class="report-periods" aria-label="Período do relatório">
        <?php foreach (['hoje' => 'Hoje', 'ontem' => 'Ontem', '7dias' => '7 dias', 'mes_atual' => 'Mês atual', 'mes_passado' => 'Mês passado', 'personalizado' => 'Personalizado'] as $period => $label): ?>
          <a class="<?= $filters['periodo'] === $period ? 'active' : '' ?>" href="<?= e(reportUrl(['periodo' => $period])) ?>">
            <?= e($label) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <input type="hidden" name="periodo" id="reportPeriodInput" value="<?= e((string)$filters['periodo']) ?>">

      <div class="report-filter-grid">
        <div class="field">
          <label for="reportStart">Início</label>
          <input id="reportStart" name="inicio" type="date" value="<?= e((string)$filters['inicio']) ?>">
        </div>

        <div class="field">
          <label for="reportEnd">Fim</label>
          <input id="reportEnd" name="fim" type="date" value="<?= e((string)$filters['fim']) ?>">
        </div>

        <div class="field">
          <label for="reportPayment">Forma de pagamento</label>
          <select id="reportPayment" name="forma_pagamento">
            <option value="">Todas</option>
            <?php foreach (['pix', 'dinheiro', 'credito', 'debito', 'conta_cliente', 'misto'] as $method): ?>
              <option value="<?= e($method) ?>" <?= $filters['forma_pagamento'] === $method ? 'selected' : '' ?>>
                <?= e(reportPaymentLabel($method)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="reportStatus">Status</label>
          <select id="reportStatus" name="status">
            <option value="">Todos</option>
            <?php foreach (['finalizada', 'pendente', 'em_aberto', 'cancelada'] as $status): ?>
              <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                <?= e(reportStatusLabel($status)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="reportUser">Operador</label>
          <select id="reportUser" name="usuario_id">
            <option value="">Todos</option>
            <?php foreach ($options['users'] as $option): ?>
              <option value="<?= (int)$option['id'] ?>" <?= (int)$filters['usuario_id'] === (int)$option['id'] ? 'selected' : '' ?>>
                <?= e((string)$option['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="reportClient">Cliente</label>
          <select id="reportClient" name="cliente_id">
            <option value="">Todos</option>
            <?php foreach ($options['clients'] as $option): ?>
              <option value="<?= (int)$option['id'] ?>" <?= (int)$filters['cliente_id'] === (int)$option['id'] ? 'selected' : '' ?>>
                <?= e((string)$option['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field wide">
          <label for="reportProduct">Produto</label>
          <select id="reportProduct" name="produto_id">
            <option value="">Todos</option>
            <?php foreach ($options['products'] as $option): ?>
              <option value="<?= (int)$option['id'] ?>" <?= (int)$filters['produto_id'] === (int)$option['id'] ? 'selected' : '' ?>>
                <?= e((string)$option['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="report-actions">
        <a class="secondary-btn" href="relatorios.php">Limpar</a>

        <?php if ($canExport): ?>
          <button class="secondary-btn" type="button" onclick="window.print()">Exportar PDF</button>
          <button class="secondary-btn" type="button" onclick="window.print()">Imprimir</button>
        <?php endif; ?>

        <button class="primary-btn" type="submit">Aplicar filtros</button>
      </div>
    </div>
  </form>

  <section class="report-section-title">
    <div>
      <h2>Resumo financeiro</h2>
      <p><?= e(reportDate($filters['inicio'] ?? '')) ?> até <?= e(reportDate($filters['fim'] ?? '')) ?></p>
    </div>
  </section>

  <section class="report-summary-grid">
    <article class="report-card success">
      <span>Total vendido</span>
      <strong><?= e(reportMoney($totalSales)) ?></strong>
      <small>Vendas não canceladas</small>
    </article>

    <article class="report-card">
      <span>Quantidade de vendas</span>
      <strong><?= (int)$salesCount ?></strong>
      <small>Registros no período</small>
    </article>

    <article class="report-card">
      <span>Ticket médio</span>
      <strong><?= e(reportMoney($averageTicket)) ?></strong>
      <small>Média por venda</small>
    </article>

    <article class="report-card warning">
      <span>Total de descontos</span>
      <strong><?= e(reportMoney($totalDiscount)) ?></strong>
      <small>Descontos aplicados</small>
    </article>

    <?php foreach (['pix', 'dinheiro', 'credito', 'debito', 'conta_cliente'] as $method): ?>
      <article class="report-card">
        <span><?= e(reportPaymentLabel($method)) ?></span>
        <strong><?= e(reportMoney($paymentTotals[$method] ?? 0)) ?></strong>
        <small>Recebido por <?= e(reportPaymentLabel($method)) ?></small>
      </article>
    <?php endforeach; ?>

    <article class="report-card <?= $canceledCount > 0 ? 'danger' : '' ?>">
      <span>Vendas canceladas</span>
      <strong><?= (int)$canceledCount ?></strong>
      <small>Total de cancelamentos</small>
    </article>

    <?php if ($canProfit): ?>
      <article class="report-card">
        <span>Custo total</span>
        <strong><?= e(reportMoney($estimatedCost)) ?></strong>
        <small>Estimado pelo custo atual do produto</small>
      </article>

      <article class="report-card success">
        <span>Lucro estimado</span>
        <strong><?= e(reportMoney($estimatedProfit)) ?></strong>
        <small>Venda menos custo estimado</small>
      </article>

      <article class="report-card">
        <span>Margem estimada</span>
        <strong><?= e(reportNumber($estimatedMargin)) ?>%</strong>
        <small>Margem sobre o total vendido</small>
      </article>
    <?php endif; ?>
  </section>

  <section class="report-section-title">
    <div>
      <h2>Gráficos</h2>
      <p>Visualização dos dados reais do período filtrado</p>
    </div>
  </section>

  <section class="report-chart-grid">
    <article class="report-chart-card">
      <h3>Formas de pagamento</h3>
      <p>Distribuição por forma de recebimento</p>
      <div class="report-chart-box">
        <canvas id="paymentChart"></canvas>
      </div>
    </article>

    <article class="report-chart-card">
      <h3>Evolução por dia</h3>
      <p>Total vendido por dia no período</p>
      <div class="report-chart-box">
        <canvas id="dailyChart"></canvas>
      </div>
    </article>

    <article class="report-chart-card">
      <h3>Produtos mais vendidos</h3>
      <p>Ranking por quantidade vendida</p>
      <div class="report-chart-box">
        <canvas id="productChart"></canvas>
      </div>
    </article>
  </section>

  <section class="report-table-card">
    <div class="report-table-head">
      <div>
        <h3>Vendas por forma de pagamento</h3>
        <p>Totais agrupados por método de recebimento.</p>
      </div>
    </div>

    <?php if (!$paymentMethods): ?>
      <p class="report-empty">Nenhum pagamento registrado no período.</p>
    <?php else: ?>
      <div class="report-table-scroll">
        <table class="report-table">
          <thead>
            <tr>
              <th>Forma</th>
              <th>Vendas</th>
              <th>Total</th>
              <th>Percentual</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($paymentMethods as $row): ?>
              <?php $percent = $totalSales > 0 ? ((float)$row['total_value'] / $totalSales) * 100 : 0; ?>
              <tr>
                <td><strong><?= e(reportPaymentLabel((string)$row['metodo'])) ?></strong></td>
                <td><?= (int)$row['total_count'] ?></td>
                <td class="money"><?= e(reportMoney($row['total_value'])) ?></td>
                <td><?= e(reportNumber($percent)) ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="report-table-card">
    <div class="report-table-head">
      <div>
        <h3>Produtos mais vendidos</h3>
        <p>Produtos com maior saída no período.</p>
      </div>
    </div>

    <?php if (!$productsSold): ?>
      <p class="report-empty">Nenhum produto vendido no período.</p>
    <?php else: ?>
      <div class="report-table-scroll">
        <table class="report-table">
          <thead>
            <tr>
              <th>Produto</th>
              <th>Quantidade</th>
              <th>Total vendido</th>
              <th>Percentual</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($productsSold as $row): ?>
              <?php $percent = $totalSales > 0 ? ((float)$row['total_value'] / $totalSales) * 100 : 0; ?>
              <tr>
                <td><strong><?= e((string)$row['product_name']) ?></strong></td>
                <td><?= e(reportNumber($row['quantity_sold'], 3)) ?></td>
                <td class="money"><?= e(reportMoney($row['total_value'])) ?></td>
                <td><?= e(reportNumber($percent)) ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($canOperatorSales): ?>
    <section class="report-table-card">
      <div class="report-table-head">
        <div>
          <h3>Vendas por operador</h3>
          <p>Desempenho dos usuários no período filtrado.</p>
        </div>
      </div>

      <?php if (!$operatorSales): ?>
        <p class="report-empty">Nenhuma venda por operador no período.</p>
      <?php else: ?>
        <div class="report-table-scroll">
          <table class="report-table">
            <thead>
              <tr>
                <th>Operador</th>
                <th>Vendas</th>
                <th>Total</th>
                <th>Ticket médio</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($operatorSales as $row): ?>
                <tr>
                  <td><strong><?= e((string)$row['operator_name']) ?></strong></td>
                  <td><?= (int)$row['sales_count'] ?></td>
                  <td class="money"><?= e(reportMoney($row['total_sales'])) ?></td>
                  <td class="money"><?= e(reportMoney($row['average_ticket'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($canClientDebt): ?>
    <section class="report-summary-grid">
      <article class="report-card warning">
        <span>Fiado em aberto</span>
        <strong><?= e(reportMoney($clientDebtSummary['total_open'] ?? 0)) ?></strong>
        <small>Saldo pendente de clientes</small>
      </article>

      <article class="report-card success">
        <span>Fiado pago</span>
        <strong><?= e(reportMoney($clientDebtSummary['total_paid'] ?? 0)) ?></strong>
        <small>Valores recebidos</small>
      </article>

      <article class="report-card <?= ((float)($clientDebtSummary['total_overdue'] ?? 0)) > 0 ? 'danger' : '' ?>">
        <span>Fiado vencido</span>
        <strong><?= e(reportMoney($clientDebtSummary['total_overdue'] ?? 0)) ?></strong>
        <small>Contas vencidas</small>
      </article>
    </section>

    <section class="report-table-card">
      <div class="report-table-head">
        <div>
          <h3>Contas de clientes</h3>
          <p>Contas de fiado relacionadas ao período.</p>
        </div>
      </div>

      <?php if (!$clientDebtRows): ?>
        <p class="report-empty">Nenhuma conta de cliente no período.</p>
      <?php else: ?>
        <div class="report-table-scroll">
          <table class="report-table">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Saldo aberto</th>
                <th>Valor pago</th>
                <th>Vencimento</th>
                <th>Status</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($clientDebtRows as $row): ?>
                <tr>
                  <td><strong><?= e((string)$row['customer_name']) ?></strong></td>
                  <td class="money"><?= e(reportMoney($row['saldo_aberto'])) ?></td>
                  <td class="money"><?= e(reportMoney($row['valor_pago'])) ?></td>
                  <td><?= e(reportDate($row['vencimento'] ?? '')) ?></td>
                  <td>
                    <span class="badge <?= e(reportStatusClass((string)$row['status'])) ?>">
                      <?= e(reportStatusLabel((string)$row['status'])) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="report-table-card report-sales-table-desktop">
    <div class="report-table-head">
      <div>
        <h3>Lista de vendas</h3>
        <p>Vendas detalhadas do período selecionado.</p>
      </div>
    </div>

    <?php if (!$sales): ?>
      <p class="report-empty">Nenhuma venda encontrada no período.</p>
    <?php else: ?>
      <div class="report-table-scroll">
        <table class="report-table">
          <thead>
            <tr>
              <th>Data/hora</th>
              <th>Nº venda</th>
              <th>Cliente</th>
              <th>Operador</th>
              <th>Pagamento</th>
              <th>Subtotal</th>
              <th>Desconto</th>
              <th>Total</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($sales as $sale): ?>
              <tr>
                <td><?= e(reportDateTime($sale['criado_em'] ?? '')) ?></td>
                <td><strong><?= e((string)$sale['numero_venda']) ?></strong></td>
                <td><?= e((string)$sale['customer_name']) ?></td>
                <td><?= e((string)$sale['operator_name']) ?></td>
                <td><?= e(reportPaymentLabel((string)$sale['payment_method'])) ?></td>
                <td class="money"><?= e(reportMoney($sale['subtotal'])) ?></td>
                <td class="money"><?= e(reportMoney($sale['desconto'])) ?></td>
                <td class="money"><strong><?= e(reportMoney($sale['total'])) ?></strong></td>
                <td>
                  <span class="badge <?= e(reportStatusClass((string)$sale['status'])) ?>">
                    <?= e(reportStatusLabel((string)$sale['status'])) ?>
                  </span>
                </td>
                <td><a href="venda-detalhes.php?id=<?= (int)$sale['id'] ?>">Ver detalhes</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <p class="report-print-note">Relatório gerado em <?= e(date('d/m/Y H:i')) ?>.</p>
  </section>

  <section class="report-mobile-sales">
    <?php if (!$sales): ?>
      <article class="report-sale-card">
        <p class="report-empty">Nenhuma venda encontrada no período.</p>
      </article>
    <?php else: ?>
      <?php foreach ($sales as $sale): ?>
        <article class="report-sale-card">
          <div class="report-sale-card-header">
            <div>
              <h4>Venda #<?= e((string)$sale['numero_venda']) ?></h4>
              <p><?= e(reportDateTime($sale['criado_em'] ?? '')) ?> · <?= e((string)$sale['operator_name']) ?></p>
              <p><?= e((string)$sale['customer_name']) ?></p>
            </div>

            <span class="badge <?= e(reportStatusClass((string)$sale['status'])) ?>">
              <?= e(reportStatusLabel((string)$sale['status'])) ?>
            </span>
          </div>

          <div class="report-sale-values">
            <div>
              <span>Total</span>
              <strong><?= e(reportMoney($sale['total'])) ?></strong>
            </div>

            <div>
              <span>Pagamento</span>
              <strong><?= e(reportPaymentLabel((string)$sale['payment_method'])) ?></strong>
            </div>

            <div>
              <span>Desconto</span>
              <strong><?= e(reportMoney($sale['desconto'])) ?></strong>
            </div>
          </div>

          <div class="report-sale-actions no-print">
            <a class="secondary-btn" href="venda-detalhes.php?id=<?= (int)$sale['id'] ?>">Detalhes</a>
            <a class="primary-btn" href="venda-detalhes.php?id=<?= (int)$sale['id'] ?>#comprovante">Comprovante</a>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>
```

  </div>
</section>

<?php if ($formError === null): ?>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>

  <script>
    const reportChartData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    (() => {
      const periodInput = document.getElementById('reportPeriodInput');
      const startInput = document.getElementById('reportStart');
      const endInput = document.getElementById('reportEnd');

      [startInput, endInput].forEach((input) => {
        if (!input || !periodInput) return;
        input.addEventListener('change', () => {
          periodInput.value = 'personalizado';
        });
      });

      if (!window.Chart) return;

      const money = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
      });

      const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              boxWidth: 14,
              font: {
                size: 11,
                weight: '700'
              }
            }
          },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const value = Number(ctx.raw || 0);
                return money.format(value);
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              font: {
                size: 11
              }
            }
          },
          x: {
            ticks: {
              font: {
                size: 11
              }
            }
          }
        }
      };

      const paymentCanvas = document.getElementById('paymentChart');
      if (paymentCanvas) {
        new Chart(paymentCanvas, {
          type: 'doughnut',
          data: {
            labels: reportChartData.payments.labels,
            datasets: [{
              data: reportChartData.payments.values,
              backgroundColor: ['#2563EB', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#64748B'],
              borderWidth: 0
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  boxWidth: 14,
                  font: {
                    size: 11,
                    weight: '700'
                  }
                }
              },
              tooltip: {
                callbacks: {
                  label: (ctx) => {
                    const value = Number(ctx.raw || 0);
                    return money.format(value);
                  }
                }
              }
            }
          }
        });
      }

      const dailyCanvas = document.getElementById('dailyChart');
      if (dailyCanvas) {
        new Chart(dailyCanvas, {
          type: 'bar',
          data: {
            labels: reportChartData.daily.labels,
            datasets: [{
              data: reportChartData.daily.values,
              backgroundColor: '#2563EB',
              borderRadius: 8
            }]
          },
          options: baseOptions
        });
      }

      const productCanvas = document.getElementById('productChart');
      if (productCanvas) {
        new Chart(productCanvas, {
          type: 'bar',
          data: {
            labels: reportChartData.products.labels,
            datasets: [{
              data: reportChartData.products.values,
              backgroundColor: '#10B981',
              borderRadius: 8
            }]
          },
          options: {
            ...baseOptions,
            indexAxis: 'y',
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                callbacks: {
                  label: (ctx) => String(ctx.raw || 0)
                }
              }
            }
          }
        });
      }
    })();
  </script>

<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
