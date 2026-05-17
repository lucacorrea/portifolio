<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'relatorios';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Relatórios | Arte&Flor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/layout.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/pages.css">
  <link rel="stylesheet" href="../assets/css/admin-premium.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body class="admin-premium-body">
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="admin-main">
    <section class="admin-page-hero">
      <div class="admin-page-title">
        <span class="badge">Gestão</span>
        <h1>Relatórios</h1>
        <p>Indicadores visuais para vendas, pedidos, estoque, produtos mais vendidos e fluxo financeiro.</p>
      </div>
      <div class="admin-hero-actions">
        <button class="btn btn-soft" type="button">Exportar PDF</button>
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
      <article class="admin-kpi-card"><span>Produto destaque</span><strong>Buquê</strong><small>Mais vendido</small></article>
    </section>

    <section class="admin-grid-2">
      <article class="admin-panel-card">
        <div class="admin-panel-header"><div><span class="badge">Produtos</span><h2>Mais vendidos</h2><p>Ranking demonstrativo por categoria.</p></div></div>
        <div class="admin-chart-bars">
          <div class="admin-chart-row"><span>Buquê de Rosas</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:86%"></div></div><strong>86%</strong></div>
          <div class="admin-chart-row"><span>Buquê Pastel</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:72%"></div></div><strong>72%</strong></div>
          <div class="admin-chart-row"><span>Arranjo Premium</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:58%"></div></div><strong>58%</strong></div>
          <div class="admin-chart-row"><span>Cesta com Flores</span><div class="admin-chart-track"><div class="admin-chart-fill" style="width:44%"></div></div><strong>44%</strong></div>
        </div>
      </article>

      <article class="admin-panel-card">
        <div class="admin-panel-header"><div><span class="badge">Financeiro</span><h2>Resumo por forma</h2><p>Visão visual dos recebimentos.</p></div></div>
        <div class="admin-metric-list">
          <div class="admin-metric-row"><span>Pix</span><strong>R$ 2.100</strong></div>
          <div class="admin-metric-row"><span>Presencial</span><strong>R$ 1.240</strong></div>
          <div class="admin-metric-row"><span>Dinheiro</span><strong>R$ 940</strong></div>
          <div class="admin-metric-row"><span>Despesas</span><strong>R$ 380</strong></div>
        </div>
      </article>
    </section>

    <section class="admin-grid-3" style="margin-top:24px">
      <article class="admin-panel-card"><div class="admin-panel-header"><div><span class="badge">Pedidos</span><h2>Status</h2></div></div><div class="admin-metric-list"><div class="admin-metric-row"><span>Recebidos</span><strong>7</strong></div><div class="admin-metric-row"><span>Em preparo</span><strong>4</strong></div><div class="admin-metric-row"><span>Finalizados</span><strong>18</strong></div></div></article>
      <article class="admin-panel-card"><div class="admin-panel-header"><div><span class="badge">Estoque</span><h2>Atenção</h2></div></div><div class="admin-metric-list"><div class="admin-metric-row"><span>Estoque baixo</span><strong>3</strong></div><div class="admin-metric-row"><span>Sem estoque</span><strong>1</strong></div><div class="admin-metric-row"><span>Reposições</span><strong>8</strong></div></div></article>
      <article class="admin-panel-card"><div class="admin-panel-header"><div><span class="badge">Clientes</span><h2>Relacionamento</h2></div></div><div class="admin-metric-list"><div class="admin-metric-row"><span>Novos</span><strong>14</strong></div><div class="admin-metric-row"><span>Recorrentes</span><strong>42</strong></div><div class="admin-metric-row"><span>Ticket médio</span><strong>R$ 112</strong></div></div></article>
    </section>
  </main>
</div>
</body>
</html>
