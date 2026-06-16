<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Repositories\SaleRepository;
use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);

$saleRepository = new SaleRepository();

function historyMoney(mixed $value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function historyDate(mixed $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Sem data';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y', $timestamp) : 'Sem data';
}

function historyDateTime(mixed $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Sem data';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Sem data';
}

function historyStatusLabel(string $status): string
{
    return [
        'todos' => 'Todos',
        'finalizada' => 'Finalizada',
        'pendente' => 'Pendente',
        'cancelada' => 'Cancelada',
        'em_aberto' => 'Em aberto',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function historyStatusClass(string $status): string
{
    return match ($status) {
        'finalizada' => 'green',
        'cancelada' => 'red',
        'pendente' => 'orange',
        'em_aberto' => 'blue',
        default => 'muted',
    };
}

function historyPaymentLabel(string $payment): string
{
    return [
        'todos' => 'Todos',
        'pix' => 'PIX',
        'dinheiro' => 'Dinheiro',
        'cartao' => 'Cartão',
        'credito' => 'Crédito',
        'debito' => 'Débito',
        'cartao_credito' => 'Cartão de crédito',
        'cartao_debito' => 'Cartão de débito',
        'conta_cliente' => 'Fiado',
        'misto' => 'Misto',
        'outro' => 'Outro',
        '' => 'Não informado',
    ][$payment] ?? ucfirst(str_replace('_', ' ', $payment));
}

function historyPeriodLabel(string $period): string
{
    return [
        'hoje' => 'Hoje',
        'ontem' => 'Ontem',
        'semana' => 'Semana',
        'mes' => 'Mês',
        'todos' => 'Todos',
        'personalizado' => 'Personalizado',
    ][$period] ?? 'Hoje';
}

function historyFilterUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 'todos') {
            unset($params[$key]);
        }
    }

    return 'historico-vendas.php' . ($params ? '?' . http_build_query($params) : '');
}

$allowedPeriods = ['hoje', 'ontem', 'semana', 'mes', 'todos', 'personalizado'];
$allowedStatus = ['todos', 'finalizada', 'pendente', 'cancelada', 'em_aberto'];
$allowedPayments = ['todos', 'pix', 'dinheiro', 'cartao', 'credito', 'debito', 'cartao_credito', 'cartao_debito', 'conta_cliente', 'misto', 'outro'];

$periodo = strtolower(trim((string)($_GET['periodo'] ?? 'hoje')));
$periodo = in_array($periodo, $allowedPeriods, true) ? $periodo : 'hoje';

$status = strtolower(trim((string)($_GET['status'] ?? 'todos')));
$status = in_array($status, $allowedStatus, true) ? $status : 'todos';

$pagamento = strtolower(trim((string)($_GET['pagamento'] ?? 'todos')));
$pagamento = in_array($pagamento, $allowedPayments, true) ? $pagamento : 'todos';

$filters = [
    'periodo' => $periodo,
    'status' => $status,
    'pagamento' => $pagamento,
    'q' => trim((string)($_GET['q'] ?? '')),
    'inicio' => trim((string)($_GET['inicio'] ?? '')),
    'fim' => trim((string)($_GET['fim'] ?? '')),
    'limit' => 120,
    'offset' => 0,
];

$sales = [];
$summary = [
    'sales_count' => 0,
    'total_sales' => 0,
    'average_ticket' => 0,
    'finalized_count' => 0,
    'canceled_count' => 0,
    'pending_count' => 0,
    'canceled_total' => 0,
];

$loadError = null;

try {
    $summary = $saleRepository->historySummary($empresaId, $filters);
    $sales = $saleRepository->history($empresaId, $filters);
} catch (Throwable $e) {
    log_app_exception($e);
    $loadError = 'Não foi possível carregar o histórico de vendas agora.';
}

$totalSales = (float)($summary['total_sales'] ?? 0);
$salesCount = (int)($summary['sales_count'] ?? 0);
$averageTicket = (float)($summary['average_ticket'] ?? 0);
$finalizedCount = (int)($summary['finalized_count'] ?? 0);
$canceledCount = (int)($summary['canceled_count'] ?? 0);
$pendingCount = (int)($summary['pending_count'] ?? 0);
$canceledTotal = (float)($summary['canceled_total'] ?? 0);

$pageId = 'historico-vendas';
$pageTitle = 'Histórico de Vendas';
$activeMenu = 'historico';

require_once __DIR__ . '/layout/header.php';
?>

<style>
  .sales-history-page {
    display: grid;
    gap: 16px;
    padding-bottom: 118px;
  }

  .sales-alert {
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid rgba(230,83,103,.25);
    color: var(--red);
    background: rgba(230,83,103,.10);
    font-size: 13px;
    font-weight: 850;
  }

  .sales-hero-card,
  .sales-filter-panel,
  .sales-summary-card,
  .sales-table-card,
  .sales-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 24px;
    box-shadow: 0 10px 28px rgba(29,55,95,.055);
  }

  .sales-hero-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    padding: 18px;
  }

  .sales-hero-card h2 {
    margin: 0;
    color: var(--ink);
    font-size: 23px;
    line-height: 1.08;
  }

  .sales-hero-card p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
  }

  .sales-hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .sales-hero-actions .primary-btn,
  .sales-hero-actions .secondary-btn {
    min-height: 44px;
    padding: 0 16px;
    text-decoration: none;
    white-space: nowrap;
  }

  .sales-summary-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
  }

  .sales-summary-card {
    position: relative;
    min-height: 104px;
    padding: 16px;
    overflow: hidden;
  }

  .sales-summary-card::after {
    content: "";
    position: absolute;
    right: -28px;
    top: -28px;
    width: 82px;
    height: 82px;
    border-radius: 999px;
    background: rgba(22,87,167,.08);
  }

  .sales-summary-card.success::after {
    background: rgba(37,196,132,.12);
  }

  .sales-summary-card.warning::after {
    background: rgba(245,158,11,.13);
  }

  .sales-summary-card.danger::after {
    background: rgba(230,83,103,.13);
  }

  .sales-summary-card span {
    display: block;
    color: var(--muted);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
  }

  .sales-summary-card strong {
    display: block;
    margin-top: 8px;
    color: var(--ink);
    font-size: 22px;
    line-height: 1.08;
    font-weight: 950;
    word-break: break-word;
  }

  .sales-summary-card small {
    display: block;
    margin-top: 6px;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.25;
    font-weight: 750;
  }

  .sales-filter-panel {
    overflow: hidden;
  }

  .sales-filter-header {
    padding: 16px 18px;
    border-bottom: 1px solid var(--line);
  }

  .sales-filter-header h3 {
    margin: 0;
    color: var(--ink);
    font-size: 18px;
  }

  .sales-filter-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .sales-filter-body {
    display: grid;
    gap: 14px;
    padding: 16px 18px 18px;
  }

  .sales-pills {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
  }

  .sales-pills::-webkit-scrollbar {
    display: none;
  }

  .sales-pills a {
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

  .sales-pills a.active {
    color: var(--blue);
    background: var(--blue-soft);
    border-color: var(--blue-line);
  }

  .sales-filter-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(150px, .75fr) minmax(150px, .75fr) auto;
    gap: 12px;
    align-items: end;
  }

  .sales-filter-grid .secondary-btn {
    min-height: 54px;
    min-width: 130px;
  }

  .sales-section-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
  }

  .sales-section-header h2 {
    margin: 0;
    color: var(--ink);
    font-size: 22px;
  }

  .sales-section-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .sales-desktop-table {
    display: block;
  }

  .sales-mobile-list {
    display: none;
  }

  .sales-table-card {
    overflow: hidden;
  }

  .sales-table-wrap {
    width: 100%;
    overflow-x: auto;
  }

  .sales-table {
    width: 100%;
    min-width: 1020px;
    border-collapse: collapse;
  }

  .sales-table th {
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

  .sales-table td {
    padding: 15px 16px;
    border-bottom: 1px solid var(--line);
    color: var(--ink);
    font-size: 13px;
    font-weight: 750;
    vertical-align: middle;
  }

  .sales-table tr:last-child td {
    border-bottom: 0;
  }

  .sales-table tr:hover td {
    background: #F8FBFF;
  }

  .sales-main-cell {
    min-width: 210px;
  }

  .sales-main-cell strong {
    display: block;
    color: var(--blue);
    font-size: 14px;
    font-weight: 950;
  }

  .sales-main-cell span,
  .sales-muted {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .sales-customer-cell {
    min-width: 220px;
  }

  .sales-customer-cell strong {
    display: block;
    color: var(--ink);
    font-size: 14px;
    font-weight: 950;
  }

  .sales-customer-cell span {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
  }

  .sales-money-cell {
    white-space: nowrap;
    font-weight: 950;
  }

  .sales-actions-cell {
    width: 170px;
  }

  .sales-inline-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .sales-inline-actions .secondary-btn,
  .sales-inline-actions .primary-btn {
    min-height: 38px;
    min-width: 82px;
    padding: 0 12px;
    border: 0;
    cursor: pointer;
    text-decoration: none;
    font-size: 12px;
  }

  .sales-card {
    padding: 16px;
  }

  .sales-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
  }

  .sales-card-title {
    min-width: 0;
  }

  .sales-card-title h3 {
    margin: 0;
    color: var(--ink);
    font-size: 18px;
    line-height: 1.15;
  }

  .sales-card-title p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.4;
    font-weight: 750;
  }

  .sales-values {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
  }

  .sales-values div {
    padding: 12px;
    border-radius: 16px;
    border: 1px solid var(--line);
    background: #F8FBFF;
    min-width: 0;
  }

  .sales-values span {
    display: block;
    color: var(--muted);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .025em;
  }

  .sales-values strong {
    display: block;
    margin-top: 5px;
    color: var(--ink);
    font-size: 15px;
    font-weight: 950;
  }

  .sales-card-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
  }

  .sales-card-actions .primary-btn,
  .sales-card-actions .secondary-btn {
    width: 100%;
    min-height: 40px;
    padding: 8px 10px;
    border: 0;
    border-radius: 14px;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    font-size: 12px;
  }

  .badge.muted {
    color: var(--muted);
    background: #F3F5F8;
    border-color: var(--line);
  }

  .sales-empty {
    padding: 24px;
    color: var(--muted);
    text-align: center;
    font-size: 13px;
    font-weight: 800;
  }

  @media (max-width: 1180px) {
    .sales-summary-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .sales-filter-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sales-filter-grid .filter-button {
      grid-column: span 2;
    }
  }

  @media (max-width: 860px) {
    .sales-hero-card {
      align-items: flex-start;
      flex-direction: column;
    }

    .sales-hero-actions {
      width: 100%;
      justify-content: stretch;
    }

    .sales-hero-actions .primary-btn,
    .sales-hero-actions .secondary-btn {
      flex: 1 1 0;
      text-align: center;
    }

    .sales-desktop-table {
      display: none;
    }

    .sales-mobile-list {
      display: grid;
      gap: 14px;
    }
  }

  @media (max-width: 760px) {
    .sales-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .sales-summary-card {
      min-height: 92px;
      padding: 14px;
      border-radius: 20px;
    }

    .sales-summary-card strong {
      font-size: 18px;
      line-height: 1.1;
    }

    .sales-summary-card small {
      font-size: 11px;
      line-height: 1.3;
    }

    .sales-filter-grid {
      grid-template-columns: 1fr;
    }

    .sales-filter-grid .filter-button {
      grid-column: auto;
    }

    .sales-values {
      grid-template-columns: 1fr;
    }

    .sales-card-header {
      flex-direction: column;
    }

    .content-pad {
      padding-bottom: 130px;
    }
  }

  @media (max-width: 430px) {
    .sales-history-page {
      gap: 14px;
      padding-bottom: 135px;
    }

    .sales-hero-card,
    .sales-filter-header,
    .sales-filter-body {
      padding: 15px;
    }

    .sales-hero-card h2 {
      font-size: 20px;
    }

    .sales-summary-grid {
      gap: 8px;
    }

    .sales-summary-card {
      min-height: 84px;
      padding: 12px;
      border-radius: 18px;
    }

    .sales-summary-card span {
      font-size: 9px;
    }

    .sales-summary-card strong {
      font-size: 16px;
    }

    .sales-summary-card small {
      font-size: 10px;
    }

    .sales-card {
      padding: 15px;
    }
  }

  @media (max-width: 340px) {
    .sales-summary-grid,
    .sales-card-actions {
      grid-template-columns: 1fr;
    }
  }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Histórico</p>
      <h1>Vendas</h1>
    </div>


<a class="round-btn no-print" href="nova-venda.php" aria-label="Nova venda">+</a>


  </div>
</header>

<section class="content-pad">
  <div class="sales-history-page">
    <?php if ($loadError !== null): ?>
      <div class="sales-alert" role="alert"><?= e($loadError) ?></div>
    <?php endif; ?>


<section class="sales-hero-card">
  <div>
    <h2>Histórico de vendas</h2>
    <p>Consulte vendas, pagamentos, operadores, status e abra os detalhes para reimprimir ou conferir comprovantes.</p>
  </div>

  <div class="sales-hero-actions no-print">
    <a class="secondary-btn" href="relatorios.php">Relatórios</a>
    <a class="primary-btn" href="nova-venda.php">Nova venda</a>
  </div>
</section>

<section class="sales-summary-grid">
  <article class="sales-summary-card success">
    <span>Total vendido</span>
    <strong><?= e(historyMoney($totalSales)) ?></strong>
    <small>Sem vendas canceladas</small>
  </article>

  <article class="sales-summary-card">
    <span>Quantidade</span>
    <strong><?= (int)$salesCount ?></strong>
    <small>Registro<?= $salesCount === 1 ? '' : 's' ?> encontrado<?= $salesCount === 1 ? '' : 's' ?></small>
  </article>

  <article class="sales-summary-card">
    <span>Ticket médio</span>
    <strong><?= e(historyMoney($averageTicket)) ?></strong>
    <small>Média do filtro atual</small>
  </article>

  <article class="sales-summary-card success">
    <span>Finalizadas</span>
    <strong><?= (int)$finalizedCount ?></strong>
    <small>Vendas concluídas</small>
  </article>

  <article class="sales-summary-card warning">
    <span>Pendentes</span>
    <strong><?= (int)$pendingCount ?></strong>
    <small>Exigem atenção</small>
  </article>

  <article class="sales-summary-card <?= $canceledCount > 0 ? 'danger' : '' ?>">
    <span>Canceladas</span>
    <strong><?= (int)$canceledCount ?></strong>
    <small><?= e(historyMoney($canceledTotal)) ?> cancelado</small>
  </article>
</section>

<form class="sales-filter-panel no-print" method="get" action="historico-vendas.php">
  <div class="sales-filter-header">
    <h3>Filtros</h3>
    <p>Busque por número, cliente, produto, operador, período, status ou forma de pagamento.</p>
  </div>

  <div class="sales-filter-body">
    <div class="sales-pills" aria-label="Período das vendas">
      <?php foreach (['hoje' => 'Hoje', 'ontem' => 'Ontem', 'semana' => 'Semana', 'mes' => 'Mês', 'todos' => 'Todos'] as $key => $label): ?>
        <a class="<?= $periodo === $key ? 'active' : '' ?>" href="<?= e(historyFilterUrl(['periodo' => $key, 'inicio' => '', 'fim' => ''])) ?>">
          <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="sales-pills" aria-label="Status das vendas">
      <?php foreach (['todos' => 'Todos', 'finalizada' => 'Finalizadas', 'pendente' => 'Pendentes', 'cancelada' => 'Canceladas', 'em_aberto' => 'Em aberto'] as $key => $label): ?>
        <a class="<?= $status === $key ? 'active' : '' ?>" href="<?= e(historyFilterUrl(['status' => $key])) ?>">
          <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="sales-pills" aria-label="Forma de pagamento">
      <?php foreach (['todos' => 'Todos', 'pix' => 'PIX', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'conta_cliente' => 'Fiado', 'misto' => 'Misto'] as $key => $label): ?>
        <a class="<?= $pagamento === $key ? 'active' : '' ?>" href="<?= e(historyFilterUrl(['pagamento' => $key])) ?>">
          <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <input type="hidden" name="pagamento" value="<?= e($pagamento) ?>">

    <div class="sales-filter-grid">
      <label class="field">
        <span>Buscar</span>
        <input type="search" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Venda, cliente, produto ou operador">
      </label>

      <label class="field">
        <span>Início</span>
        <input type="date" name="inicio" value="<?= e((string)$filters['inicio']) ?>">
      </label>

      <label class="field">
        <span>Fim</span>
        <input type="date" name="fim" value="<?= e((string)$filters['fim']) ?>">
      </label>

      <button class="secondary-btn filter-button" type="submit">Filtrar</button>
    </div>
  </div>
</form>

<section>
  <div class="sales-section-header">
    <div>
      <h2>Vendas encontradas</h2>
      <p><?= count($sales) ?> registro<?= count($sales) === 1 ? '' : 's' ?> exibido<?= count($sales) === 1 ? '' : 's' ?> · <?= e(historyPeriodLabel($periodo)) ?></p>
    </div>
  </div>
</section>

<section class="sales-desktop-table">
  <div class="sales-table-card">
    <?php if (!$sales && $loadError === null): ?>
      <div class="sales-empty">Nenhuma venda encontrada para o filtro atual.</div>
    <?php else: ?>
      <div class="sales-table-wrap">
        <table class="sales-table">
          <thead>
            <tr>
              <th>Venda</th>
              <th>Cliente</th>
              <th>Operador</th>
              <th>Pagamento</th>
              <th>Status</th>
              <th>Itens</th>
              <th>Total</th>
              <th>Ações</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($sales as $sale): ?>
              <tr>
                <td class="sales-main-cell">
                  <strong>#<?= e(str_pad((string)($sale['numero_venda'] ?: $sale['id']), 4, '0', STR_PAD_LEFT)) ?></strong>
                  <span><?= e(historyDateTime($sale['criado_em'])) ?></span>
                </td>

                <td class="sales-customer-cell">
                  <strong><?= e((string)$sale['cliente_nome']) ?></strong>
                  <span><?= e((string)($sale['cliente_telefone'] ?: 'Sem telefone')) ?></span>
                </td>

                <td>
                  <?= e((string)$sale['operador_nome']) ?>
                  <span class="sales-muted"><?= e((string)($sale['operador_email'] ?: '')) ?></span>
                </td>

                <td>
                  <?= e((string)$sale['forma_pagamento_label']) ?>
                  <span class="sales-muted">Pago <?= e(historyMoney($sale['valor_pago'])) ?></span>
                </td>

                <td>
                  <span class="badge <?= e(historyStatusClass((string)$sale['status'])) ?>">
                    <?= e((string)$sale['status_label']) ?>
                  </span>
                </td>

                <td><?= (int)$sale['itens_count'] ?></td>

                <td class="sales-money-cell"><?= e(historyMoney($sale['total'])) ?></td>

                <td class="sales-actions-cell no-print">
                  <div class="sales-inline-actions">
                    <a class="secondary-btn" href="venda-detalhes.php?id=<?= (int)$sale['id'] ?>">Detalhes</a>
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

<section class="sales-mobile-list">
  <?php if (!$sales && $loadError === null): ?>
    <article class="sales-card sales-empty">Nenhuma venda encontrada para o filtro atual.</article>
  <?php endif; ?>

  <?php foreach ($sales as $sale): ?>
    <article class="sales-card">
      <div class="sales-card-header">
        <div class="sales-card-title">
          <h3>Venda #<?= e(str_pad((string)($sale['numero_venda'] ?: $sale['id']), 4, '0', STR_PAD_LEFT)) ?></h3>
          <p><?= e(historyDateTime($sale['criado_em'])) ?> · <?= e((string)$sale['operador_nome']) ?></p>
          <p><?= e((string)$sale['cliente_nome']) ?> · <?= e((string)($sale['cliente_telefone'] ?: 'Sem telefone')) ?></p>
        </div>

        <span class="badge <?= e(historyStatusClass((string)$sale['status'])) ?>">
          <?= e((string)$sale['status_label']) ?>
        </span>
      </div>

      <div class="sales-values">
        <div>
          <span>Total</span>
          <strong><?= e(historyMoney($sale['total'])) ?></strong>
        </div>

        <div>
          <span>Pagamento</span>
          <strong><?= e((string)$sale['forma_pagamento_label']) ?></strong>
        </div>

        <div>
          <span>Itens</span>
          <strong><?= (int)$sale['itens_count'] ?></strong>
        </div>
      </div>

      <div class="sales-card-actions no-print">
        <a class="secondary-btn" href="venda-detalhes.php?id=<?= (int)$sale['id'] ?>">Ver detalhes</a>
        <a class="primary-btn" href="venda-detalhes.php?id=<?= (int)$sale['id'] ?>#comprovante">Comprovante</a>
      </div>
    </article>
  <?php endforeach; ?>
</section>


  </div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
