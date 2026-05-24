<?php
$adminTitle = 'Relatórios';
$activeAdmin = 'relatorios';
require_once __DIR__ . '/../includes/dashboard.php';

$periodParam = $_GET['periodo'] ?? '30';
$period = is_string($periodParam) ? $periodParam : '30';
$days = match ($period) {
    'hoje' => 0,
    '7' => 7,
    'mes' => 30,
    default => 30,
};

$periodWhere = $days === 0
    ? 'DATE(p.criado_em) = CURRENT_DATE'
    : 'p.criado_em >= (CURRENT_DATE - INTERVAL ' . (int) $days . ' DAY)';
$itemsPeriodWhere = $days === 0
    ? 'DATE(p.criado_em) = CURRENT_DATE'
    : 'p.criado_em >= (CURRENT_DATE - INTERVAL ' . (int) $days . ' DAY)';

$summary = db()->query(
    'SELECT
        COUNT(*) AS pedidos,
        COALESCE(SUM(CASE WHEN p.status <> "cancelado" THEN p.total ELSE 0 END), 0) AS receita,
        COALESCE(SUM(CASE WHEN p.status <> "cancelado" THEN p.desconto_total ELSE 0 END), 0) AS descontos,
        COALESCE(AVG(CASE WHEN p.status <> "cancelado" THEN p.total ELSE NULL END), 0) AS ticket_medio
     FROM pedidos p
     WHERE ' . $periodWhere
)->fetch() ?: [];

$payments = db()->query(
    'SELECT p.forma_pagamento, COUNT(*) AS pedidos, COALESCE(SUM(p.total), 0) AS total
     FROM pedidos p
     WHERE p.status <> "cancelado" AND ' . $periodWhere . '
     GROUP BY p.forma_pagamento
     ORDER BY total DESC'
)->fetchAll();

$statusCounts = db()->query(
    'SELECT p.status, COUNT(*) AS total
     FROM pedidos p
     WHERE ' . $periodWhere . '
     GROUP BY p.status
     ORDER BY total DESC'
)->fetchAll();

$topProducts = db()->query(
    'SELECT pi.produto_nome, SUM(pi.quantidade) AS quantidade, COALESCE(SUM(pi.total_linha), 0) AS total
     FROM pedido_itens pi
     INNER JOIN pedidos p ON p.id = pi.pedido_id
     WHERE p.status <> "cancelado" AND ' . $itemsPeriodWhere . '
     GROUP BY pi.produto_nome
     ORDER BY quantidade DESC, total DESC
     LIMIT 5'
)->fetchAll();

$categorySales = dashboard_category_sales($days === 0 ? 1 : $days);
$productStats = product_stats();
$maxPaymentTotal = max(1, ...array_map(static fn (array $row): float => (float) $row['total'], $payments ?: [['total' => 1]]));

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Gestão</span>
    <h1>Relatórios</h1>
    <p>Indicadores reais de vendas, pagamentos, categorias, pedidos, estoque e ticket médio.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/pedidos.php') ?>">Ver pedidos</a>
    <a class="btn btn-primary" href="<?= site_url('admin/dashboard.php') ?>">Dashboard</a>
  </div>
</section>

<form class="admin-command-bar" method="get" action="<?= site_url('admin/relatorios.php') ?>">
  <label class="admin-field">
    <span>Período</span>
    <select name="periodo">
      <option value="30" <?= $period === '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
      <option value="hoje" <?= $period === 'hoje' ? 'selected' : '' ?>>Hoje</option>
      <option value="7" <?= $period === '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
      <option value="mes" <?= $period === 'mes' ? 'selected' : '' ?>>Este mês operacional</option>
    </select>
  </label>
  <button class="btn btn-soft" type="submit">Aplicar</button>
</form>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Vendas</span><strong><?= money_br((float) ($summary['receita'] ?? 0)) ?></strong><small>Faturamento real</small></article>
  <article class="admin-kpi-card"><span>Pedidos</span><strong><?= (int) ($summary['pedidos'] ?? 0) ?></strong><small>Período selecionado</small></article>
  <article class="admin-kpi-card"><span>Ticket médio</span><strong><?= money_br((float) ($summary['ticket_medio'] ?? 0)) ?></strong><small>Pedidos não cancelados</small></article>
  <article class="admin-kpi-card"><span>Estoque baixo</span><strong><?= (int) $productStats['estoque_baixo'] ?></strong><small>Precisa reposição</small></article>
</section>

<section class="admin-grid-2">
  <article class="admin-panel-card">
    <div class="admin-panel-header"><div><span class="badge">Pagamentos</span><h2>Vendas por forma</h2></div></div>
    <div class="admin-chart-bars">
      <?php foreach ($payments as $payment): ?>
        <?php $percent = (int) round(((float) $payment['total'] / $maxPaymentTotal) * 100); ?>
        <div class="admin-chart-row"><span><?= e(dashboard_payment_label((string) $payment['forma_pagamento'])) ?></span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:<?= $percent ?>%"></div></div><strong><?= money_br((float) $payment['total']) ?></strong></div>
      <?php endforeach; ?>
      <?php if (empty($payments)): ?><div class="admin-empty-row"><strong>Sem vendas</strong><span>Pedidos aparecerão aqui após o checkout.</span></div><?php endif; ?>
    </div>
  </article>

  <article class="admin-panel-card">
    <div class="admin-panel-header"><div><span class="badge">Categorias</span><h2>Vendas por categoria</h2></div></div>
    <div class="admin-chart-bars">
      <?php foreach ($categorySales as $sale): ?>
        <div class="admin-chart-row"><span><?= e((string) $sale['categoria']) ?></span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:<?= (int) $sale['percentual'] ?>%"></div></div><strong><?= money_br((float) $sale['total']) ?></strong></div>
      <?php endforeach; ?>
      <?php if (empty($categorySales)): ?><div class="admin-empty-row"><strong>Sem categorias vendidas</strong><span>Os itens aparecerão quando houver pedidos.</span></div><?php endif; ?>
    </div>
  </article>
</section>

<section class="admin-grid-3">
  <article class="admin-panel-card">
    <span class="badge">Produtos</span>
    <h2>Mais vendidos</h2>
    <div class="admin-metric-list">
      <?php foreach ($topProducts as $product): ?>
        <div class="admin-metric-row"><span><?= e((string) $product['produto_nome']) ?></span><strong><?= (int) $product['quantidade'] ?></strong></div>
      <?php endforeach; ?>
      <?php if (empty($topProducts)): ?><div class="admin-empty-row"><strong>Sem itens vendidos</strong><span>Finalize pedidos para preencher.</span></div><?php endif; ?>
    </div>
  </article>

  <article class="admin-panel-card">
    <span class="badge">Pedidos</span>
    <h2>Por status</h2>
    <div class="admin-metric-list">
      <?php foreach ($statusCounts as $status): ?>
        <div class="admin-metric-row"><span><?= e(dashboard_order_status_label((string) $status['status'])) ?></span><strong><?= (int) $status['total'] ?></strong></div>
      <?php endforeach; ?>
      <?php if (empty($statusCounts)): ?><div class="admin-empty-row"><strong>Sem pedidos</strong><span>Nenhum status no período.</span></div><?php endif; ?>
    </div>
  </article>

  <article class="admin-panel-card">
    <span class="badge">Financeiro</span>
    <h2>Resumo</h2>
    <div class="admin-metric-list">
      <div class="admin-metric-row"><span>Receita</span><strong><?= money_br((float) ($summary['receita'] ?? 0)) ?></strong></div>
      <div class="admin-metric-row"><span>Descontos</span><strong><?= money_br((float) ($summary['descontos'] ?? 0)) ?></strong></div>
      <div class="admin-metric-row total"><span>Saldo</span><strong><?= money_br(max(0, (float) ($summary['receita'] ?? 0) - (float) ($summary['descontos'] ?? 0))) ?></strong></div>
    </div>
  </article>
</section>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
