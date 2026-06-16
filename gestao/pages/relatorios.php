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

function reportPaymentLabel(string $method): string
{
    return [
        'pix' => 'PIX',
        'dinheiro' => 'Dinheiro',
        'credito' => 'Crédito',
        'debito' => 'Débito',
        'conta_cliente' => 'Conta do cliente',
        'misto' => 'Misto',
    ][$method] ?? 'Não informado';
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
    ][$status] ?? ucfirst($status);
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
$chartData = $report['chartData'] ?? ['payments' => ['labels' => [], 'values' => []], 'products' => ['labels' => [], 'values' => []], 'daily' => ['labels' => [], 'values' => []]];
$totalSales = (float)($summary['total_sales'] ?? 0);
$canProfit = canReportAccess('profit', $currentNivel);
$canOperatorSales = canReportAccess('operator_sales', $currentNivel);
$canClientDebt = canReportAccess('client_debt', $currentNivel);
$canExport = canReportAccess('export', $currentNivel);

$pageId = 'relatorios-server';
$pageTitle = 'Relatórios';
$activeMenu = '';
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .report-alert { margin-bottom: 14px; padding: 13px 15px; color: var(--red); background: rgba(230,83,103,.1); border: 1px solid rgba(230,83,103,.25); border-radius: 14px; font-size: 13px; font-weight: 750; }
  .report-filter-card, .report-card, .report-table-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 8px 22px rgba(29,55,95,.055); }
  .report-filter-card { padding: 14px; }
  .report-filter-grid { display: grid; gap: 10px; }
  .report-periods { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; scrollbar-width: none; }
  .report-periods::-webkit-scrollbar { display: none; }
  .report-periods a { white-space: nowrap; min-height: 38px; display: inline-flex; align-items: center; padding: 0 12px; color: var(--muted); background: #F7FAFE; border: 1px solid var(--line); border-radius: 999px; font-size: 12px; font-weight: 850; text-decoration: none; }
  .report-periods a.active { color: var(--blue); background: var(--blue-soft); border-color: var(--blue-line); }
  .report-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
  .report-summary-grid { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .report-card { padding: 14px; min-width: 0; }
  .report-card span { display: block; color: var(--muted); font-size: 11px; font-weight: 800; }
  .report-card strong { display: block; margin-top: 5px; color: var(--ink); font-size: 21px; letter-spacing: 0; }
  .report-card small { display: block; margin-top: 5px; color: var(--muted); font-size: 11px; font-weight: 700; }
  .report-chart-grid { display: grid; gap: 12px; }
  .report-chart { padding: 14px; min-height: 240px; }
  .report-chart canvas { width: 100% !important; max-height: 260px; }
  .report-table-card { padding: 14px; overflow: hidden; }
  .report-table-card h3 { margin: 0 0 10px; font-size: 17px; letter-spacing: 0; }
  .report-table-scroll { overflow-x: auto; }
  .report-table { width: 100%; min-width: 760px; border-collapse: collapse; font-size: 12px; }
  .report-table th, .report-table td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
  .report-table th { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .02em; }
  .report-table td strong { color: var(--ink); }
  .report-empty { margin: 0; padding: 14px; color: var(--muted); background: #F8FBFF; border: 1px dashed var(--blue-line); border-radius: 12px; font-size: 13px; font-weight: 750; }
  .report-print-note { color: var(--muted); font-size: 11px; font-weight: 700; }
  @media (min-width: 720px) {
    .report-filter-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); align-items: end; }
    .report-filter-grid .wide { grid-column: span 2; }
    .report-summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .report-chart-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 560px) {
    .report-actions .secondary-btn, .report-actions .primary-btn { flex: 1 1 130px; min-height: 42px; }
    .report-summary-grid { grid-template-columns: 1fr; }
    .report-card strong { font-size: 19px; }
  }
  @media print {
    .no-print, .bottom-nav, .app-sidebar, .mobile-topbar { display: none !important; }
    body { background: #fff !important; }
    .content-pad { padding: 0 !important; }
    .report-card, .report-table-card, .report-filter-card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
    .report-chart { min-height: auto; }
  }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Indicadores</p>
      <h1>Relatórios</h1>
    </div>
    <?php if ($canExport): ?>
      <button class="icon-btn light no-print" type="button" onclick="window.print()" aria-label="Imprimir relatório">⎙</button>
    <?php endif; ?>
  </div>
</header>

<section class="content-pad">
  <?php if ($formError !== null): ?>
    <div class="report-alert" role="alert"><?= e($formError) ?></div>
  <?php endif; ?>

  <?php if ($formError === null): ?>
    <form class="report-filter-card no-print" method="get">
      <div class="report-periods" aria-label="Período do relatório">
        <?php foreach (['hoje' => 'Hoje', 'ontem' => 'Ontem', '7dias' => '7 dias', 'mes_atual' => 'Mês atual', 'mes_passado' => 'Mês passado', 'personalizado' => 'Personalizado'] as $period => $label): ?>
          <a class="<?= $filters['periodo'] === $period ? 'active' : '' ?>" href="<?= e(reportUrl(['periodo' => $period])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>

      <input type="hidden" name="periodo" value="<?= e((string)$filters['periodo']) ?>">
      <div class="report-filter-grid section-gap-small">
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
              <option value="<?= e($method) ?>" <?= $filters['forma_pagamento'] === $method ? 'selected' : '' ?>><?= e(reportPaymentLabel($method)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="reportStatus">Status</label>
          <select id="reportStatus" name="status">
            <option value="">Todos</option>
            <?php foreach (['finalizada', 'pendente', 'em_aberto', 'cancelada'] as $status): ?>
              <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(reportStatusLabel($status)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="reportUser">Operador</label>
          <select id="reportUser" name="usuario_id">
            <option value="">Todos</option>
            <?php foreach ($options['users'] as $option): ?>
              <option value="<?= (int)$option['id'] ?>" <?= (int)$filters['usuario_id'] === (int)$option['id'] ? 'selected' : '' ?>><?= e((string)$option['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="reportClient">Cliente</label>
          <select id="reportClient" name="cliente_id">
            <option value="">Todos</option>
            <?php foreach ($options['clients'] as $option): ?>
              <option value="<?= (int)$option['id'] ?>" <?= (int)$filters['cliente_id'] === (int)$option['id'] ? 'selected' : '' ?>><?= e((string)$option['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field wide">
          <label for="reportProduct">Produto</label>
          <select id="reportProduct" name="produto_id">
            <option value="">Todos</option>
            <?php foreach ($options['products'] as $option): ?>
              <option value="<?= (int)$option['id'] ?>" <?= (int)$filters['produto_id'] === (int)$option['id'] ? 'selected' : '' ?>><?= e((string)$option['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="report-actions section-gap-small">
        <a class="secondary-btn" href="relatorios.php">Limpar</a>
        <?php if ($canExport): ?>
          <button class="secondary-btn" type="button" onclick="window.print()">Exportar PDF</button>
          <button class="secondary-btn" type="button" onclick="window.print()">Imprimir</button>
        <?php endif; ?>
        <button class="primary-btn" type="submit">Aplicar filtros</button>
      </div>
    </form>

    <div class="sheet-title section-gap">
      <div>
        <h2>Resumo financeiro</h2>
        <p><?= e(date('d/m/Y', strtotime((string)$filters['inicio']))) ?> até <?= e(date('d/m/Y', strtotime((string)$filters['fim']))) ?></p>
      </div>
    </div>

    <div class="report-summary-grid">
      <article class="report-card"><span>Total vendido</span><strong><?= e(reportMoney($summary['total_sales'] ?? 0)) ?></strong></article>
      <article class="report-card"><span>Quantidade de vendas</span><strong><?= (int)($summary['sales_count'] ?? 0) ?></strong></article>
      <article class="report-card"><span>Ticket médio</span><strong><?= e(reportMoney($summary['average_ticket'] ?? 0)) ?></strong></article>
      <article class="report-card"><span>Total de descontos</span><strong><?= e(reportMoney($summary['total_discount'] ?? 0)) ?></strong></article>
      <?php foreach (['pix', 'dinheiro', 'credito', 'debito', 'conta_cliente'] as $method): ?>
        <?php $value = array_reduce($paymentMethods, fn (float $carry, array $row): float => $carry + ((string)$row['metodo'] === $method ? (float)$row['total_value'] : 0.0), 0.0); ?>
        <article class="report-card"><span><?= e(reportPaymentLabel($method)) ?></span><strong><?= e(reportMoney($value)) ?></strong></article>
      <?php endforeach; ?>
      <article class="report-card"><span>Vendas canceladas</span><strong><?= (int)($summary['canceled_count'] ?? 0) ?></strong></article>
      <?php if ($canProfit): ?>
        <article class="report-card"><span>Custo total</span><strong><?= e(reportMoney($summary['estimated_cost'] ?? 0)) ?></strong><small>Estimado pelo custo atual do produto.</small></article>
        <article class="report-card"><span>Lucro estimado</span><strong><?= e(reportMoney($summary['estimated_profit'] ?? 0)) ?></strong></article>
        <article class="report-card"><span>Margem estimada</span><strong><?= e(reportNumber($summary['estimated_margin'] ?? 0)) ?>%</strong></article>
      <?php endif; ?>
    </div>

    <div class="sheet-title section-gap">
      <div>
        <h2>Gráficos</h2>
        <p>Dados reais do período filtrado</p>
      </div>
    </div>

    <div class="report-chart-grid">
      <article class="report-table-card report-chart"><h3>Formas de pagamento</h3><canvas id="paymentChart"></canvas></article>
      <article class="report-table-card report-chart"><h3>Evolução por dia</h3><canvas id="dailyChart"></canvas></article>
      <article class="report-table-card report-chart"><h3>Produtos mais vendidos</h3><canvas id="productChart"></canvas></article>
    </div>

    <div class="report-table-card section-gap">
      <h3>Vendas por forma de pagamento</h3>
      <?php if (!$paymentMethods): ?>
        <p class="report-empty">Nenhum pagamento registrado no período.</p>
      <?php else: ?>
        <div class="report-table-scroll">
          <table class="report-table">
            <thead><tr><th>Forma</th><th>Vendas</th><th>Total</th><th>Percentual</th></tr></thead>
            <tbody>
              <?php foreach ($paymentMethods as $row): ?>
                <?php $percent = $totalSales > 0 ? ((float)$row['total_value'] / $totalSales) * 100 : 0; ?>
                <tr>
                  <td><strong><?= e(reportPaymentLabel((string)$row['metodo'])) ?></strong></td>
                  <td><?= (int)$row['total_count'] ?></td>
                  <td><?= e(reportMoney($row['total_value'])) ?></td>
                  <td><?= e(reportNumber($percent)) ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="report-table-card section-gap">
      <h3>Produtos mais vendidos</h3>
      <?php if (!$productsSold): ?>
        <p class="report-empty">Nenhum produto vendido no período.</p>
      <?php else: ?>
        <div class="report-table-scroll">
          <table class="report-table">
            <thead><tr><th>Produto</th><th>Quantidade</th><th>Total vendido</th><th>Percentual</th></tr></thead>
            <tbody>
              <?php foreach ($productsSold as $row): ?>
                <?php $percent = $totalSales > 0 ? ((float)$row['total_value'] / $totalSales) * 100 : 0; ?>
                <tr>
                  <td><strong><?= e((string)$row['product_name']) ?></strong></td>
                  <td><?= e(reportNumber($row['quantity_sold'], 3)) ?></td>
                  <td><?= e(reportMoney($row['total_value'])) ?></td>
                  <td><?= e(reportNumber($percent)) ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($canOperatorSales): ?>
      <div class="report-table-card section-gap">
        <h3>Vendas por operador</h3>
        <?php if (!$operatorSales): ?>
          <p class="report-empty">Nenhuma venda por operador no período.</p>
        <?php else: ?>
          <div class="report-table-scroll">
            <table class="report-table">
              <thead><tr><th>Operador</th><th>Vendas</th><th>Total</th><th>Ticket médio</th></tr></thead>
              <tbody>
                <?php foreach ($operatorSales as $row): ?>
                  <tr>
                    <td><strong><?= e((string)$row['operator_name']) ?></strong></td>
                    <td><?= (int)$row['sales_count'] ?></td>
                    <td><?= e(reportMoney($row['total_sales'])) ?></td>
                    <td><?= e(reportMoney($row['average_ticket'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($canClientDebt): ?>
      <div class="report-summary-grid section-gap">
        <article class="report-card"><span>Fiado em aberto</span><strong><?= e(reportMoney($clientDebtSummary['total_open'] ?? 0)) ?></strong></article>
        <article class="report-card"><span>Fiado pago</span><strong><?= e(reportMoney($clientDebtSummary['total_paid'] ?? 0)) ?></strong></article>
        <article class="report-card"><span>Fiado vencido</span><strong><?= e(reportMoney($clientDebtSummary['total_overdue'] ?? 0)) ?></strong></article>
      </div>

      <div class="report-table-card section-gap">
        <h3>Contas de clientes</h3>
        <?php if (!$clientDebtRows): ?>
          <p class="report-empty">Nenhuma conta de cliente no período.</p>
        <?php else: ?>
          <div class="report-table-scroll">
            <table class="report-table">
              <thead><tr><th>Cliente</th><th>Saldo aberto</th><th>Valor pago</th><th>Vencimento</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($clientDebtRows as $row): ?>
                  <tr>
                    <td><strong><?= e((string)$row['customer_name']) ?></strong></td>
                    <td><?= e(reportMoney($row['saldo_aberto'])) ?></td>
                    <td><?= e(reportMoney($row['valor_pago'])) ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string)$row['vencimento']))) ?></td>
                    <td><?= e(reportStatusLabel((string)$row['status'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="report-table-card section-gap">
      <h3>Lista de vendas</h3>
      <?php if (!$sales): ?>
        <p class="report-empty">Nenhuma venda encontrada no período.</p>
      <?php else: ?>
        <div class="report-table-scroll">
          <table class="report-table">
            <thead><tr><th>Data/hora</th><th>Nº venda</th><th>Cliente</th><th>Operador</th><th>Pagamento</th><th>Subtotal</th><th>Desconto</th><th>Total</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
              <?php foreach ($sales as $sale): ?>
                <tr>
                  <td><?= e(date('d/m/Y H:i', strtotime((string)$sale['criado_em']))) ?></td>
                  <td><strong><?= e((string)$sale['numero_venda']) ?></strong></td>
                  <td><?= e((string)$sale['customer_name']) ?></td>
                  <td><?= e((string)$sale['operator_name']) ?></td>
                  <td><?= e(reportPaymentLabel((string)$sale['payment_method'])) ?></td>
                  <td><?= e(reportMoney($sale['subtotal'])) ?></td>
                  <td><?= e(reportMoney($sale['desconto'])) ?></td>
                  <td><strong><?= e(reportMoney($sale['total'])) ?></strong></td>
                  <td><?= e(reportStatusLabel((string)$sale['status'])) ?></td>
                  <td><a href="venda-detalhes.php?id=<?= (int)$sale['id'] ?>">Ver detalhes</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <p class="report-print-note section-gap-small">Relatório gerado em <?= e(date('d/m/Y H:i')) ?>.</p>
    </div>
  <?php endif; ?>
</section>

<?php if ($formError === null): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>
  <script>
    const reportChartData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    (() => {
      if (!window.Chart) return;
      const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
      const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => money.format(Number(ctx.raw || 0)) } } },
        scales: { y: { beginAtZero: true } },
      };

      const paymentCanvas = document.getElementById('paymentChart');
      if (paymentCanvas) {
        new Chart(paymentCanvas, {
          type: 'doughnut',
          data: { labels: reportChartData.payments.labels, datasets: [{ data: reportChartData.payments.values, backgroundColor: ['#2563EB', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#64748B'] }] },
          options: { responsive: true, maintainAspectRatio: false },
        });
      }

      const dailyCanvas = document.getElementById('dailyChart');
      if (dailyCanvas) {
        new Chart(dailyCanvas, {
          type: 'bar',
          data: { labels: reportChartData.daily.labels, datasets: [{ data: reportChartData.daily.values, backgroundColor: '#2563EB', borderRadius: 8 }] },
          options: chartOptions,
        });
      }

      const productCanvas = document.getElementById('productChart');
      if (productCanvas) {
        new Chart(productCanvas, {
          type: 'bar',
          data: { labels: reportChartData.products.labels, datasets: [{ data: reportChartData.products.values, backgroundColor: '#10B981', borderRadius: 8 }] },
          options: { ...chartOptions, indexAxis: 'y', plugins: { legend: { display: false } } },
        });
      }
    })();
  </script>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
