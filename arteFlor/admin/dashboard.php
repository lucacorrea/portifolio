<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'dashboard';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | Arte&Flor</title>
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
        <span class="badge">Visão geral</span>
        <h1>Dashboard Arte&Flor</h1>
        <p>Painel executivo para acompanhar vendas, pedidos, estoque, caixa e integrações do catálogo.</p>
      </div>
      <div class="admin-hero-actions">
        <a class="btn btn-soft" href="caixa.php">Abrir frente de caixa</a>
        <a class="btn btn-primary" href="produto-form.php">Novo produto</a>
      </div>
    </section>

    <section class="admin-quick-grid">
      <a class="admin-quick-card" href="produtos.php"><i>🌸</i><div><strong>Produtos</strong><small>Listagem, edição e status</small></div></a>
      <a class="admin-quick-card" href="produto-form.php"><i>➕</i><div><strong>Cadastrar</strong><small>Novo produto no catálogo</small></div></a>
      <a class="admin-quick-card" href="caixa.php"><i>🧾</i><div><strong>PDV</strong><small>Venda presencial rápida</small></div></a>
      <a class="admin-quick-card" href="pedidos.php"><i>📦</i><div><strong>Pedidos</strong><small>Status e entregas</small></div></a>
      <a class="admin-quick-card" href="clientes.php"><i>👥</i><div><strong>Clientes</strong><small>Histórico e contato</small></div></a>
      <a class="admin-quick-card" href="integracoes.php"><i>🔌</i><div><strong>Integrações</strong><small>Pix e WhatsApp</small></div></a>
    </section>

    <section class="admin-kpi-grid">
      <article class="admin-kpi-card"><span>Vendas de hoje</span><strong>R$ 820</strong><small>+18% vs. ontem</small></article>
      <article class="admin-kpi-card"><span>Pedidos pendentes</span><strong>7</strong><small>3 aguardando Pix</small></article>
      <article class="admin-kpi-card"><span>Produtos ativos</span><strong>12</strong><small>3 sob encomenda</small></article>
      <article class="admin-kpi-card"><span>Estoque crítico</span><strong>3</strong><small>Repor esta semana</small></article>
    </section>

    <section class="admin-grid-2">
      <article class="admin-panel-card">
        <div class="admin-panel-header">
          <div>
            <span class="badge">Vendas</span>
            <h2>Resumo comercial</h2>
            <p>Indicadores fictícios para demonstrar a operação da loja.</p>
          </div>
          <a class="btn btn-soft" href="relatorios.php">Ver relatórios</a>
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
            <span class="badge">Operação</span>
            <h2>Status do dia</h2>
            <p>Resumo operacional para tomada de decisão rápida.</p>
          </div>
        </div>
        <div class="admin-metric-list">
          <div class="admin-metric-row"><span>Pix recebido</span><strong>R$ 420,00</strong></div>
          <div class="admin-metric-row"><span>Presencial / balcão</span><strong>R$ 260,00</strong></div>
          <div class="admin-metric-row"><span>Despesas do dia</span><strong>R$ 140,00</strong></div>
          <div class="admin-metric-row"><span>Saldo visual</span><strong>R$ 680,00</strong></div>
        </div>
      </article>
    </section>

    <section class="admin-grid-2" style="margin-top:24px">
      <article class="admin-panel-card">
        <div class="admin-panel-header">
          <div>
            <span class="badge">Pedidos recentes</span>
            <h2>Fila de atendimento</h2>
          </div>
          <a class="btn btn-soft" href="pedidos.php">Todos os pedidos</a>
        </div>
        <div class="admin-data-table">
          <table>
            <thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Total</th></tr></thead>
            <tbody>
              <tr><td><div class="admin-item-title"><strong>#AF-1025</strong><small>Buquê Tons Pastel</small></div></td><td>Maria Clara</td><td><span class="admin-badge-warn">Em preparo</span></td><td>R$ 119,90</td></tr>
              <tr><td><div class="admin-item-title"><strong>#AF-1024</strong><small>Arranjo Premium</small></div></td><td>Ana Beatriz</td><td><span class="admin-badge-info">Aguardando Pix</span></td><td>R$ 189,90</td></tr>
              <tr><td><div class="admin-item-title"><strong>#AF-1021</strong><small>Mini Buquê</small></div></td><td>João</td><td><span class="admin-badge-ok">Finalizado</span></td><td>R$ 59,90</td></tr>
            </tbody>
          </table>
        </div>
      </article>

      <article class="admin-panel-card">
        <div class="admin-panel-header">
          <div>
            <span class="badge">Atenção</span>
            <h2>Alertas importantes</h2>
          </div>
        </div>
        <div class="admin-metric-list">
          <div class="admin-alert-card"><strong>Estoque baixo</strong>Vaso de Violeta, Orquídea e Cesta com Flores precisam de revisão.</div>
          <div class="admin-alert-card"><strong>Pix pendente</strong>3 pedidos ainda aguardam confirmação manual de pagamento.</div>
          <div class="admin-alert-card"><strong>Conteúdo</strong>Blog pode receber posts sobre cuidados com flores e datas especiais.</div>
        </div>
      </article>
    </section>
  </main>
</div>
</body>
</html>
