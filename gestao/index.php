<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

use App\Security\Auth;
use App\Services\DashboardService;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);

$dashboardService = new DashboardService();

function dashboardMoney(mixed $value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function dashboardNumber(mixed $value): string
{
    return number_format((float)$value, 0, ',', '.');
}

function dashboardDate(mixed $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Sem data';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y', $timestamp) : 'Sem data';
}

function dashboardDateTime(mixed $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Sem data';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Sem data';
}

function dashboardSaleStatusLabel(string $status): string
{
    return [
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
        'pendente' => 'Pendente',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function dashboardSaleStatusClass(string $status): string
{
    return match ($status) {
        'finalizada' => 'text-green',
        'cancelada' => 'text-red',
        default => 'text-orange',
    };
}

function dashboardSafePercent(float $value, float $base): float
{
    if ($base <= 0) {
        return 0.0;
    }

    return round(($value / $base) * 100, 1);
}

$dashboard = [
    'today' => [
        'sales_count' => 0,
        'total_sales' => 0,
        'estimated_profit' => 0,
    ],
    'month' => [
        'sales_count' => 0,
        'total_sales' => 0,
        'estimated_profit' => 0,
    ],
    'clientAccounts' => [
        'total_open' => 0,
        'total_overdue' => 0,
        'open_count' => 0,
        'overdue_count' => 0,
        'clients_with_debt' => 0,
    ],
    'paymentMethods' => [],
    'salesEvolution' => [],
    'latestSales' => [],
    'topProducts' => [],
    'lowStock' => [],
    'expiringProducts' => [],
    'expiredProducts' => [],
    'settings' => [
        'alertDays' => 7,
    ],
];

$loadError = null;

try {
    $dashboard = array_replace_recursive($dashboard, $dashboardService->summary($empresaId));
} catch (Throwable $e) {
    log_app_exception($e);
    $loadError = 'Não foi possível carregar todos os indicadores do dashboard agora.';
}

$today = $dashboard['today'];
$month = $dashboard['month'];
$clientAccounts = $dashboard['clientAccounts'];
$paymentMethods = $dashboard['paymentMethods'];
$salesEvolution = $dashboard['salesEvolution'];
$latestSales = $dashboard['latestSales'];
$topProducts = $dashboard['topProducts'];
$lowStock = $dashboard['lowStock'];
$expiringProducts = $dashboard['expiringProducts'];
$expiredProducts = $dashboard['expiredProducts'];

$totalVendidoHoje = (float)($today['total_sales'] ?? 0);
$qtdVendasHoje = (int)($today['sales_count'] ?? 0);
$lucroHoje = (float)($today['estimated_profit'] ?? 0);
$ticketMedioHoje = $qtdVendasHoje > 0 ? $totalVendidoHoje / $qtdVendasHoje : 0;

$totalVendidoMes = (float)($month['total_sales'] ?? 0);
$qtdVendasMes = (int)($month['sales_count'] ?? 0);
$lucroMes = (float)($month['estimated_profit'] ?? 0);

$totalContasAbertas = (float)($clientAccounts['total_open'] ?? 0);
$totalContasVencidas = (float)($clientAccounts['total_overdue'] ?? 0);
$qtdContasVencidas = (int)($clientAccounts['overdue_count'] ?? 0);

$maxEvolutionValue = 0.0;
foreach ($salesEvolution as $evolutionRow) {
    $maxEvolutionValue = max($maxEvolutionValue, (float)($evolutionRow['total_sales'] ?? 0));
}

$pageId = 'dashboard';
$pageTitle = 'Dashboard';
$activeMenu = 'home';
$prefix = '';

require_once __DIR__ . '/pages/layout/header.php';
?>

<style>
  .dashboard-alert {
    margin: 14px 0;
    padding: 13px 15px;
    border-radius: 16px;
    border: 1px solid rgba(230,83,103,.25);
    color: var(--red);
    background: rgba(230,83,103,.10);
    font-size: 13px;
    font-weight: 800;
  }

  .dashboard-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 10px;
  }

  .dashboard-hero-meta span {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    color: rgba(255,255,255,.92);
    background: rgba(255,255,255,.15);
    font-size: 11px;
    font-weight: 850;
  }

  .dashboard-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 10px;
  }

  .dashboard-kpi-card {
    padding: 15px;
    border-radius: 18px;
    border: 1px solid var(--line);
    background: #fff;
    box-shadow: 0 8px 22px rgba(29,55,95,.055);
  }

  .dashboard-kpi-card span {
    display: block;
    color: var(--muted);
    font-size: 11px;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: .02em;
  }

  .dashboard-kpi-card strong {
    display: block;
    margin-top: 6px;
    color: var(--ink);
    font-size: 22px;
    line-height: 1.1;
  }

  .dashboard-kpi-card small {
    display: block;
    margin-top: 5px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 750;
  }

  .dashboard-kpi-card.danger {
    border-color: rgba(230,83,103,.25);
    background: rgba(230,83,103,.05);
  }

  .dashboard-kpi-card.warning {
    border-color: rgba(245,158,11,.25);
    background: rgba(245,158,11,.06);
  }

  .dashboard-kpi-card.success {
    border-color: rgba(37,196,132,.25);
    background: rgba(37,196,132,.06);
  }

  .dashboard-grid-2 {
    display: grid;
    gap: 12px;
  }

  .dashboard-mini-chart {
    display: grid;
    grid-template-columns: repeat(7, minmax(0,1fr));
    gap: 7px;
    align-items: end;
    min-height: 150px;
    padding: 14px;
  }

  .dashboard-bar {
    display: grid;
    align-items: end;
    gap: 7px;
    min-width: 0;
  }

  .dashboard-bar-track {
    height: 96px;
    display: flex;
    align-items: end;
    justify-content: center;
    border-radius: 999px;
    background: #F2F6FC;
    overflow: hidden;
  }

  .dashboard-bar-fill {
    width: 100%;
    min-height: 5px;
    border-radius: 999px 999px 0 0;
    background: var(--blue);
  }

  .dashboard-bar small {
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
    text-align: center;
    white-space: nowrap;
  }

  .dashboard-empty {
    padding: 16px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
    text-align: center;
  }

  .dashboard-row-click {
    cursor: pointer;
  }

  .dashboard-row-click:hover {
    background: #F8FBFF;
  }

  .dashboard-alert-list .row-item {
    align-items: flex-start;
  }

  .dashboard-alert-list .row-value strong {
    white-space: nowrap;
  }

  .dashboard-shortcuts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 10px;
  }

  .dashboard-shortcut {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 64px;
    padding: 12px;
    border-radius: 18px;
    border: 1px solid var(--line);
    color: var(--ink);
    background: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 850;
    box-shadow: 0 8px 22px rgba(29,55,95,.045);
  }

  .dashboard-shortcut svg {
    width: 22px;
    height: 22px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.9;
  }

  .dashboard-shortcut.primary {
    color: #fff;
    background: var(--blue);
    border-color: var(--blue);
  }

  @media (min-width: 760px) {
    .dashboard-kpi-grid {
      grid-template-columns: repeat(4, minmax(0,1fr));
    }

    .dashboard-grid-2 {
      grid-template-columns: repeat(2, minmax(0,1fr));
      align-items: start;
    }

    .dashboard-shortcuts {
      grid-template-columns: repeat(4, minmax(0,1fr));
    }
  }

  @media (max-width: 430px) {
    .dashboard-kpi-grid {
      grid-template-columns: 1fr;
    }

    .dashboard-kpi-card strong {
      font-size: 20px;
    }

    .dashboard-shortcuts {
      grid-template-columns: 1fr;
    }
  }
</style>

<header class="blue-area">
  <div class="balance-row">
    <div>
      <p class="micro-label">Vendido hoje</p>
      <h1><?= e(dashboardMoney($totalVendidoHoje)) ?></h1>
      <span class="subtle-light">
        <?= e((string)($user['nome'] ?? $user['usuario'] ?? 'Usuário')) ?> •
        <?= (int)$qtdVendasHoje ?> venda<?= $qtdVendasHoje === 1 ? '' : 's' ?>
      </span>

      <div class="dashboard-hero-meta">
        <span>Ticket <?= e(dashboardMoney($ticketMedioHoje)) ?></span>
        <span>Lucro <?= e(dashboardMoney($lucroHoje)) ?></span>
        <span>Mês <?= e(dashboardMoney($totalVendidoMes)) ?></span>
      </div>
    </div>

    <a class="avatar-btn" href="pages/configuracoes.php" aria-label="Configurações">
      <img src="assets/icons/icon.svg" alt="L&J" />
    </a>
  </div>

  <nav class="quick-menu" aria-label="Ações rápidas">
    <a href="pages/nova-venda.php"><i data-icon="receipt"></i><span>Venda</span></a>
    <a href="pages/produtos.php"><i data-icon="product"></i><span>Produtos</span></a>
    <a href="pages/clientes.php"><i data-icon="user"></i><span>Clientes</span></a>
    <a href="pages/contas-clientes.php"><i data-icon="box"></i><span>Fiado</span></a>
    <a href="pages/relatorios.php"><i data-icon="report"></i><span>Relatórios</span></a>
  </nav>
</header>

<section class="white-sheet">
  <?php if ($loadError !== null): ?>
    <div class="dashboard-alert" role="alert"><?= e($loadError) ?></div>
  <?php endif; ?>

  <div class="sheet-title">
    <div>
      <h2>Resumo geral</h2>
      <p>Indicadores reais do caixa, estoque e fiado</p>
    </div>
  </div>

  <div class="dashboard-kpi-grid">
    <article class="dashboard-kpi-card success">
      <span>Vendas hoje</span>
      <strong><?= e(dashboardMoney($totalVendidoHoje)) ?></strong>
      <small><?= (int)$qtdVendasHoje ?> venda<?= $qtdVendasHoje === 1 ? '' : 's' ?> · Ticket <?= e(dashboardMoney($ticketMedioHoje)) ?></small>
    </article>

    <article class="dashboard-kpi-card">
      <span>Lucro estimado hoje</span>
      <strong><?= e(dashboardMoney($lucroHoje)) ?></strong>
      <small><?= e((string)dashboardSafePercent($lucroHoje, $totalVendidoHoje)) ?>% sobre vendido</small>
    </article>

    <article class="dashboard-kpi-card">
      <span>Vendas do mês</span>
      <strong><?= e(dashboardMoney($totalVendidoMes)) ?></strong>
      <small><?= (int)$qtdVendasMes ?> venda<?= $qtdVendasMes === 1 ? '' : 's' ?> · Lucro <?= e(dashboardMoney($lucroMes)) ?></small>
    </article>

    <article class="dashboard-kpi-card <?= $totalContasVencidas > 0 ? 'danger' : 'warning' ?>">
      <span>Fiado em aberto</span>
      <strong><?= e(dashboardMoney($totalContasAbertas)) ?></strong>
      <small><?= (int)($clientAccounts['open_count'] ?? 0) ?> conta<?= (int)($clientAccounts['open_count'] ?? 0) === 1 ? '' : 's' ?> · <?= (int)($clientAccounts['clients_with_debt'] ?? 0) ?> cliente<?= (int)($clientAccounts['clients_with_debt'] ?? 0) === 1 ? '' : 's' ?></small>
    </article>
  </div>

  <div class="dashboard-shortcuts section-gap">
    <a class="dashboard-shortcut primary" href="pages/nova-venda.php">
      <svg viewBox="0 0 24 24"><path d="M6 5h12v14H6z"/><path d="M9 9h6"/><path d="M9 13h4"/></svg>
      Nova venda
    </a>

    <a class="dashboard-shortcut" href="pages/produtos.php">
      <svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>
      Produtos
    </a>

    <a class="dashboard-shortcut" href="pages/clientes.php">
      <svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-8 0"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
      Clientes
    </a>

    <a class="dashboard-shortcut" href="pages/contas-clientes.php">
      <svg viewBox="0 0 24 24"><path d="M6 7h12v10H6z"/><path d="M8 11h8"/><path d="M8 14h5"/></svg>
      Contas / Fiado
    </a>
  </div>

  <div class="dashboard-grid-2 section-gap">
    <section>
      <div class="sheet-title">
        <div>
          <h2>Alertas financeiros</h2>
          <p>Contas vencidas e valores em aberto</p>
        </div>
        <a class="small-link" href="pages/contas-clientes.php">Ver</a>
      </div>

      <article class="summary-card">
        <div class="summary-line">
          <span>Contas vencidas</span>
          <strong class="<?= $qtdContasVencidas > 0 ? 'text-red' : 'text-green' ?>">
            <?= e(dashboardMoney($totalContasVencidas)) ?>
          </strong>
        </div>
        <div class="summary-line">
          <span>Quantidade vencida</span>
          <strong><?= (int)$qtdContasVencidas ?></strong>
        </div>
        <div class="summary-line">
          <span>Clientes com dívida</span>
          <strong><?= (int)($clientAccounts['clients_with_debt'] ?? 0) ?></strong>
        </div>
      </article>
    </section>

    <section>
      <div class="sheet-title">
        <div>
          <h2>Últimos 7 dias</h2>
          <p>Evolução das vendas</p>
        </div>
      </div>

      <div class="list-card">
        <?php if (empty($salesEvolution)): ?>
          <p class="dashboard-empty">Nenhuma venda finalizada nos últimos dias.</p>
        <?php else: ?>
          <div class="dashboard-mini-chart" aria-label="Evolução de vendas dos últimos dias">
            <?php foreach ($salesEvolution as $row): ?>
              <?php
                $date = (string)($row['sale_date'] ?? '');
                $value = (float)($row['total_sales'] ?? 0);
                $height = $maxEvolutionValue > 0 ? max(5, (int)round(($value / $maxEvolutionValue) * 96)) : 5;
              ?>
              <div class="dashboard-bar" title="<?= e(dashboardDate($date)) ?> · <?= e(dashboardMoney($value)) ?>">
                <div class="dashboard-bar-track">
                  <div class="dashboard-bar-fill" style="height: <?= (int)$height ?>px"></div>
                </div>
                <small><?= e($date ? date('d/m', strtotime($date)) : '--') ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="dashboard-grid-2 section-gap">
    <section>
      <div class="sheet-title">
        <div>
          <h2>Estoque baixo</h2>
          <p>Produtos no mínimo ou abaixo</p>
        </div>
        <a class="small-link" href="pages/produtos.php?filtro=estoque_baixo">Ver</a>
      </div>

      <div class="list-card dashboard-alert-list">
        <?php if (empty($lowStock)): ?>
          <p class="dashboard-empty">Nenhum produto com estoque baixo.</p>
        <?php else: ?>
          <?php foreach ($lowStock as $product): ?>
            <div class="row-item dashboard-row-click" onclick="location.href='pages/produto-form.php?id=<?= (int)$product['id'] ?>'">
              <div class="row-icon">
                <svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>
              </div>
              <div class="row-content">
                <h4><?= e((string)$product['nome']) ?></h4>
                <p>Mínimo <?= e(dashboardNumber($product['estoque_minimo'] ?? 0)) ?> · Atual <?= e(dashboardNumber($product['quantidade'] ?? 0)) ?></p>
              </div>
              <div class="row-value">
                <strong class="text-orange"><?= e(dashboardNumber($product['quantidade'] ?? 0)) ?> un.</strong>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section>
      <div class="sheet-title">
        <div>
          <h2>Validade</h2>
          <p>Vencidos e próximos do vencimento</p>
        </div>
        <a class="small-link" href="pages/produtos.php?filtro=perto_validade">Ver</a>
      </div>

      <div class="list-card dashboard-alert-list">
        <?php if (empty($expiredProducts) && empty($expiringProducts)): ?>
          <p class="dashboard-empty">Nenhum alerta de validade.</p>
        <?php else: ?>
          <?php foreach ($expiredProducts as $product): ?>
            <div class="row-item dashboard-row-click" onclick="location.href='pages/produto-form.php?id=<?= (int)$product['id'] ?>'">
              <div class="row-icon">
                <svg viewBox="0 0 24 24"><path d="M12 3l9 18H3z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
              </div>
              <div class="row-content">
                <h4><?= e((string)$product['nome']) ?></h4>
                <p>Lote <?= e((string)($product['lote'] ?? '-')) ?> · Venceu em <?= e(dashboardDate($product['validade'] ?? '')) ?></p>
              </div>
              <div class="row-value">
                <strong class="text-red">Vencido</strong>
              </div>
            </div>
          <?php endforeach; ?>

          <?php foreach ($expiringProducts as $product): ?>
            <div class="row-item dashboard-row-click" onclick="location.href='pages/produto-form.php?id=<?= (int)$product['id'] ?>'">
              <div class="row-icon">
                <svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>
              </div>
              <div class="row-content">
                <h4><?= e((string)$product['nome']) ?></h4>
                <p>Lote <?= e((string)($product['lote'] ?? '-')) ?> · Validade <?= e(dashboardDate($product['validade'] ?? '')) ?></p>
              </div>
              <div class="row-value">
                <strong class="text-orange"><?= e(dashboardNumber($product['quantidade'] ?? 0)) ?> un.</strong>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="dashboard-grid-2 section-gap">
    <section>
      <div class="sheet-title">
        <div>
          <h2>Últimas vendas</h2>
          <p>Movimentações recentes</p>
        </div>
        <a class="small-link" href="pages/relatorios.php">Ver tudo</a>
      </div>

      <div class="list-card">
        <?php if (empty($latestSales)): ?>
          <p class="dashboard-empty">Nenhuma venda registrada.</p>
        <?php else: ?>
          <?php foreach ($latestSales as $sale): ?>
            <div class="row-item dashboard-row-click" onclick="location.href='pages/venda-detalhes.php?id=<?= (int)$sale['id'] ?>'">
              <div class="row-icon">
                <svg viewBox="0 0 24 24"><path d="M7 4h10v16l-2-1-2 1-2-1-2 1-2-1z"/><path d="M9 8h6"/><path d="M9 12h5"/></svg>
              </div>
              <div class="row-content">
                <h4>Venda #<?= e(str_pad((string)($sale['numero_venda'] ?? $sale['id']), 4, '0', STR_PAD_LEFT)) ?></h4>
                <p><?= e((string)($sale['vendedor'] ?? 'Operador')) ?> · <?= e(dashboardDateTime($sale['criado_em'] ?? '')) ?></p>
                <p class="<?= e(dashboardSaleStatusClass((string)($sale['status'] ?? ''))) ?>">
                  <?= e(dashboardSaleStatusLabel((string)($sale['status'] ?? ''))) ?>
                </p>
              </div>
              <div class="row-value">
                <strong class="text-green"><?= e(dashboardMoney($sale['total'] ?? 0)) ?></strong>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section>
      <div class="sheet-title">
        <div>
          <h2>Mais vendidos hoje</h2>
          <p>Produtos em destaque</p>
        </div>
      </div>

      <div class="list-card">
        <?php if (empty($topProducts)): ?>
          <p class="dashboard-empty">Nenhum produto vendido hoje.</p>
        <?php else: ?>
          <?php foreach ($topProducts as $product): ?>
            <div class="row-item dashboard-row-click" onclick="location.href='pages/produto-form.php?id=<?= (int)$product['id'] ?>'">
              <div class="row-icon">
                <svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>
              </div>
              <div class="row-content">
                <h4><?= e((string)$product['nome']) ?></h4>
                <p>Faturamento <?= e(dashboardMoney($product['total_faturado'] ?? 0)) ?></p>
              </div>
              <div class="row-value">
                <strong class="text-blue"><?= e(dashboardNumber($product['total_vendido'] ?? 0)) ?> un.</strong>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <?php if (!empty($paymentMethods)): ?>
    <div class="sheet-title section-gap">
      <div>
        <h2>Formas de pagamento hoje</h2>
        <p>Composição dos recebimentos</p>
      </div>
    </div>

    <div class="list-card">
      <?php foreach ($paymentMethods as $payment): ?>
        <div class="summary-line">
          <span><?= e(ucfirst((string)($payment['metodo'] ?? 'Não informado'))) ?> · <?= (int)($payment['total_count'] ?? 0) ?> lançamento<?= (int)($payment['total_count'] ?? 0) === 1 ? '' : 's' ?></span>
          <strong><?= e(dashboardMoney($payment['total_value'] ?? 0)) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/pages/layout/footer.php'; ?>