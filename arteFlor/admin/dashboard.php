<?php
$adminTitle = 'Dashboard';
$activeAdmin = 'dashboard';
require_once __DIR__ . '/../includes/admin-head.php';
require_once __DIR__ . '/../includes/products.php';
$stats = product_stats();
$produtosRecentes = product_list([]);
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
  <article class="admin-kpi-card"><span>Vendas de hoje</span><strong>R$ 820</strong><small>12 vendas no sistema visual</small></article>
  <article class="admin-kpi-card"><span>Pedidos pendentes</span><strong>7</strong><small>4 em preparo</small></article>
  <article class="admin-kpi-card"><span>Produtos ativos</span><strong><?= $stats['disponiveis'] ?></strong><small>Catálogo publicado</small></article>
  <article class="admin-kpi-card"><span>Estoque baixo</span><strong><?= $stats['estoque_baixo'] ?></strong><small>Reposição sugerida</small></article>
  <article class="admin-kpi-card"><span>Pix pendente</span><strong>3</strong><small>Confirmação manual</small></article>
  <article class="admin-kpi-card"><span>Ticket médio</span><strong>R$ 136</strong><small>Pedidos e PDV</small></article>
</section>

<section class="admin-quick-grid">
  <a class="admin-quick-card" href="<?= site_url('admin/produto-form.php') ?>"><i>Novo</i><div><strong>Novo produto</strong><small>Cadastro visual completo</small></div></a>
  <a class="admin-quick-card" href="<?= site_url('admin/caixa.php') ?>"><i>PDV</i><div><strong>Frente de caixa</strong><small>Venda presencial rápida</small></div></a>
  <a class="admin-quick-card" href="<?= site_url('admin/pedidos.php') ?>"><i>Fila</i><div><strong>Pedidos</strong><small>Status e pagamentos</small></div></a>
  <a class="admin-quick-card" href="<?= site_url('admin/estoque.php') ?>"><i>Estoq.</i><div><strong>Estoque</strong><small>Entradas e saídas</small></div></a>
  <a class="admin-quick-card" href="<?= site_url('admin/relatorios.php') ?>"><i>BI</i><div><strong>Relatórios</strong><small>Indicadores visuais</small></div></a>
  <a class="admin-quick-card" href="<?= site_url('admin/integracoes.php') ?>"><i>API</i><div><strong>Integrações</strong><small>Pix e atendimento</small></div></a>
</section>

<section class="admin-grid-2">
  <article class="admin-panel-card">
    <div class="admin-panel-header">
      <div>
        <span class="badge">Vendas</span>
        <h2>Vendas por categoria</h2>
        <p>Gráfico visual em CSS para apresentação comercial.</p>
      </div>
    </div>
    <div class="admin-chart-bars">
      <div class="admin-chart-row"><span>Buquês</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:82%"></div></div><strong>82%</strong></div>
      <div class="admin-chart-row"><span>Arranjos</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:64%"></div></div><strong>64%</strong></div>
      <div class="admin-chart-row"><span>Presentes</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:48%"></div></div><strong>48%</strong></div>
      <div class="admin-chart-row"><span>Vasos</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:36%"></div></div><strong>36%</strong></div>
    </div>
  </article>

  <article class="admin-panel-card">
    <div class="admin-panel-header">
      <div>
        <span class="badge">Financeiro</span>
        <h2>Resumo do dia</h2>
        <p>Valores fictícios para validar o painel com a cliente.</p>
      </div>
      <a class="btn btn-soft" href="<?= site_url('admin/relatorios.php') ?>">Ver relatórios</a>
    </div>
    <div class="admin-metric-list">
      <div class="admin-metric-row"><span>Pix confirmado</span><strong>R$ 420,00</strong></div>
      <div class="admin-metric-row"><span>Dinheiro</span><strong>R$ 210,00</strong></div>
      <div class="admin-metric-row"><span>Cartão presencial</span><strong>R$ 190,00</strong></div>
      <div class="admin-metric-row total"><span>Total líquido visual</span><strong>R$ 820,00</strong></div>
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
          <?php foreach (array_slice($produtosRecentes, 0, 3) as $produto): ?>
            <tr>
              <td><strong><?= e($produto['nome']) ?></strong><small><?= e($produto['sku']) ?></small></td>
              <td><?= e($produto['categoria_nome'] ?? 'Sem categoria') ?></td>
              <td><span class="<?= $produto['status'] === 'disponivel' ? 'admin-badge-ok' : 'admin-badge-warn' ?>"><?= e(status_label($produto['status'])) ?></span></td>
              <td><?= money_br((float) ($produto['preco_promocional'] ?? 0) > 0 ? (float) $produto['preco_promocional'] : (float) $produto['preco']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($produtosRecentes)): ?>
            <tr><td colspan="4"><strong>Nenhum produto cadastrado</strong><small>Use “Novo produto” para popular o catálogo.</small></td></tr>
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
      <div class="admin-alert-card"><strong>Pix pendente</strong>3 pedidos aguardam confirmação manual no painel.</div>
      <div class="admin-alert-card"><strong>Estoque crítico</strong>Cesta Afeto e Kit Presente Romântico precisam de reposição.</div>
      <div class="admin-alert-card"><strong>Apresentação</strong>Fluxos de checkout e PDV finalizam dentro do sistema visual.</div>
    </div>
  </article>
</section>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
