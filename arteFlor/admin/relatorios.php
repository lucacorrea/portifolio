<?php
$adminTitle = 'Relatórios';
$activeAdmin = 'relatorios';
require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Gestão</span>
    <h1>Relatórios</h1>
    <p>Indicadores visuais de vendas, pagamentos, categorias, pedidos, estoque e ticket médio.</p>
  </div>
  <div class="admin-hero-actions">
    <button class="btn btn-soft" type="button">Exportar visual</button>
    <button class="btn btn-primary" type="button">Gerar relatório</button>
  </div>
</section>

<section class="admin-command-bar">
  <label class="admin-field"><span>Período</span><select><option>Este mês</option><option>Hoje</option><option>Últimos 7 dias</option><option>Personalizado</option></select></label>
  <label class="admin-field"><span>Origem</span><select><option>Todas</option><option>Catálogo</option><option>PDV</option><option>Atendimento</option></select></label>
  <label class="admin-field"><span>Pagamento</span><select><option>Todos</option><option>Pix</option><option>Dinheiro</option><option>Cartão</option></select></label>
  <label class="admin-field"><span>Categoria</span><select><option>Todas</option><option>Buquês</option><option>Arranjos</option><option>Presentes</option></select></label>
  <button class="btn btn-soft" type="button">Aplicar</button>
</section>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Vendas</span><strong>R$ 4.280</strong><small>Faturamento visual</small></article>
  <article class="admin-kpi-card"><span>Pedidos</span><strong>38</strong><small>Período atual</small></article>
  <article class="admin-kpi-card"><span>Ticket médio</span><strong>R$ 112</strong><small>Por pedido</small></article>
  <article class="admin-kpi-card"><span>Estoque baixo</span><strong>3</strong><small>Precisa reposição</small></article>
</section>

<section class="admin-grid-2">
  <article class="admin-panel-card">
    <div class="admin-panel-header"><div><span class="badge">Pagamentos</span><h2>Vendas por forma</h2></div></div>
    <div class="admin-chart-bars">
      <div class="admin-chart-row"><span>Pix</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:72%"></div></div><strong>R$ 2.100</strong></div>
      <div class="admin-chart-row"><span>Cartão</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:54%"></div></div><strong>R$ 1.240</strong></div>
      <div class="admin-chart-row"><span>Dinheiro</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:42%"></div></div><strong>R$ 940</strong></div>
    </div>
  </article>

  <article class="admin-panel-card">
    <div class="admin-panel-header"><div><span class="badge">Categorias</span><h2>Vendas por categoria</h2></div></div>
    <div class="admin-chart-bars">
      <div class="admin-chart-row"><span>Buquês</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:86%"></div></div><strong>86%</strong></div>
      <div class="admin-chart-row"><span>Arranjos</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:58%"></div></div><strong>58%</strong></div>
      <div class="admin-chart-row"><span>Presentes</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:44%"></div></div><strong>44%</strong></div>
    </div>
  </article>
</section>

<section class="admin-grid-3">
  <article class="admin-panel-card"><span class="badge">Produtos</span><h2>Mais vendidos</h2><div class="admin-metric-list"><div class="admin-metric-row"><span>Buquê de Rosas</span><strong>18</strong></div><div class="admin-metric-row"><span>Buquê Pastel</span><strong>12</strong></div><div class="admin-metric-row"><span>Orquídea</span><strong>9</strong></div></div></article>
  <article class="admin-panel-card"><span class="badge">Pedidos</span><h2>Por status</h2><div class="admin-metric-list"><div class="admin-metric-row"><span>Recebidos</span><strong>7</strong></div><div class="admin-metric-row"><span>Em preparo</span><strong>4</strong></div><div class="admin-metric-row"><span>Finalizados</span><strong>18</strong></div></div></article>
  <article class="admin-panel-card"><span class="badge">Financeiro</span><h2>Resumo</h2><div class="admin-metric-list"><div class="admin-metric-row"><span>Receita</span><strong>R$ 4.280</strong></div><div class="admin-metric-row"><span>Descontos</span><strong>R$ 180</strong></div><div class="admin-metric-row total"><span>Saldo visual</span><strong>R$ 4.100</strong></div></div></article>
</section>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
