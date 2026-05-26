<?php
$adminTitle = 'Dashboard';
$activeAdmin = 'dashboard';
require_once __DIR__ . '/../includes/dashboard.php';
require_once __DIR__ . '/../includes/whatsapp.php';
$adminUser = require_admin();

$productStats = product_stats();
$todaySummary = dashboard_today_summary();
$paymentSummary = dashboard_payment_summary_today();
$categorySales = dashboard_category_sales(30);
$recentOrders = dashboard_recent_orders(5);
$lowStockProducts = dashboard_low_stock_products(5);
$alerts = dashboard_alerts($todaySummary, $lowStockProducts, $productStats);
$whatsappErrors = whatsapp_error_count();
if ($whatsappErrors > 0) {
    $alerts[] = [
        'class' => 'admin-alert-danger',
        'title' => 'WhatsApp',
        'text' => $whatsappErrors . ' notificação(ões) com erro precisam de revisão em integrações.',
    ];
}

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Visão geral</span>
    <h1>Dashboard Arte&Flor</h1>
    <p>Painel executivo para acompanhar vendas, pedidos, estoque, Pix manual e frente de caixa.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/caixa.php') ?>">Frente de caixa</a>
    <a class="btn btn-primary" href="<?= site_url('admin/produto-form.php') ?>">Novo produto</a>
  </div>
</section>

<section class="admin-kpi-grid six">
  <article class="admin-kpi-card"><span>Vendas de hoje</span><strong><?= money_br($todaySummary['vendas_hoje']) ?></strong><small><?= $todaySummary['pedidos_hoje'] ?> pedido(s) no banco</small></article>
  <article class="admin-kpi-card"><span>Pedidos pendentes</span><strong><?= $todaySummary['pedidos_pendentes'] ?></strong><small><?= $todaySummary['em_preparo'] ?> em preparo</small></article>
  <article class="admin-kpi-card"><span>Produtos ativos</span><strong><?= $productStats['disponiveis'] ?></strong><small><?= $productStats['total'] ?> produto(s) cadastrados</small></article>
  <article class="admin-kpi-card"><span>Estoque baixo</span><strong><?= $productStats['estoque_baixo'] ?></strong><small><?= $productStats['sem_estoque'] ?> sem estoque</small></article>
  <article class="admin-kpi-card"><span>Pix pendente</span><strong><?= $todaySummary['pix_pendente'] ?></strong><small>Confirmação manual</small></article>
  <article class="admin-kpi-card"><span>Ticket médio</span><strong><?= money_br($todaySummary['ticket_medio']) ?></strong><small>Pedidos de hoje</small></article>
</section>



<section class="admin-grid-2">
  <article class="admin-panel-card">
    <div class="admin-panel-header">
      <div>
        <span class="badge">Vendas</span>
        <h2>Vendas por categoria</h2>
        <p>Baseado nos itens de pedidos dos últimos 30 dias.</p>
      </div>
    </div>
    <div class="admin-chart-bars">
      <?php foreach ($categorySales as $sale): ?>
        <div class="admin-chart-row">
          <span><?= e($sale['categoria']) ?></span>
          <div class="admin-chart-track"><div class="admin-chart-fill" style="width:<?= (int) $sale['percentual'] ?>%"></div></div>
          <strong><?= money_br($sale['total']) ?></strong>
        </div>
      <?php endforeach; ?>
      <?php if (empty($categorySales)): ?>
        <div class="admin-empty-row">
          <strong>Sem vendas por categoria</strong>
          <span>Os dados aparecem quando pedidos com itens forem gravados.</span>
        </div>
      <?php endif; ?>
    </div>
  </article>

  <article class="admin-panel-card">
    <div class="admin-panel-header">
      <div>
        <span class="badge">Financeiro</span>
        <h2>Resumo do dia</h2>
        <p>Totais agrupados por forma de pagamento dos pedidos de hoje.</p>
      </div>
      <a class="btn btn-soft" href="<?= site_url('admin/relatorios.php') ?>">Ver relatórios</a>
    </div>
    <div class="admin-metric-list">
      <?php foreach ($paymentSummary as $payment): ?>
        <div class="admin-metric-row">
          <span><?= e($payment['label']) ?></span>
          <strong><?= money_br($payment['total_valor']) ?></strong>
        </div>
      <?php endforeach; ?>
      <div class="admin-metric-row total"><span>Total vendido hoje</span><strong><?= money_br($todaySummary['vendas_hoje']) ?></strong></div>
    </div>
  </article>
</section>

<section class="admin-grid-2">
  <article class="admin-panel-card">
    <div class="admin-panel-header">
      <div>
        <span class="badge">Pedidos recentes</span>
        <h2>Fila de atendimento</h2>
      </div>
      <a class="btn btn-soft" href="<?= site_url('admin/pedidos.php') ?>">Todos</a>
    </div>
    <div class="admin-data-table compact">
      <table>
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($recentOrders as $pedido): ?>
            <tr>
              <td><strong><?= e($pedido['codigo']) ?></strong><small><?= e(dashboard_origin_label((string) $pedido['origem'])) ?></small></td>
              <td><?= e($pedido['cliente_nome']) ?></td>
              <td><span class="<?= dashboard_order_badge_class((string) $pedido['status']) ?>"><?= e(dashboard_order_status_label((string) $pedido['status'])) ?></span></td>
              <td><?= money_br((float) $pedido['total']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($recentOrders)): ?>
            <tr><td colspan="4"><strong>Nenhum pedido cadastrado</strong><small>Os pedidos aparecerão aqui assim que forem gravados no banco.</small></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>

  <article class="admin-panel-card">
    <div class="admin-panel-header">
      <div>
        <span class="badge badge-rose">Alertas</span>
        <h2>Atenção operacional</h2>
      </div>
    </div>
    <div class="admin-metric-list">
      <?php foreach ($alerts as $alert): ?>
        <div class="admin-alert-card <?= e($alert['class']) ?>">
          <strong><?= e($alert['title']) ?></strong><?= e($alert['text']) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($lowStockProducts)): ?>
      <div class="critical-products-list">
        <div class="critical-products-title">
          <strong>Produtos críticos</strong>
          <small>Até 5 itens com estoque zerado ou abaixo do mínimo.</small>
        </div>
        <?php foreach ($lowStockProducts as $criticalProduct): ?>
          <?php $inventoryStatus = product_inventory_status($criticalProduct); ?>
          <a class="critical-product-row <?= e(product_inventory_row_class($inventoryStatus)) ?>" href="<?= site_url('admin/produto-form.php?id=' . (int) $criticalProduct['id']) ?>">
            <span>
              <strong><?= e($criticalProduct['nome']) ?></strong>
              <small><?= e($criticalProduct['sku'] ?: 'Sem SKU') ?></small>
            </span>
            <span>
              Estoque: <?= (int) ($criticalProduct['estoque'] ?? 0) ?> un.
              <small>Mínimo: <?= (int) ($criticalProduct['estoque_minimo'] ?? 0) ?> un.</small>
            </span>
            <span class="<?= e(product_inventory_badge_class($inventoryStatus)) ?>"><?= e(product_inventory_label($inventoryStatus)) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
</section>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
