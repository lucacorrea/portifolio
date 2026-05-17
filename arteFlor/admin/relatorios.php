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
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/pages.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-header">
      <div>
        <span class="badge">Indicadores visuais</span>
        <h1 class="section-title">Relatórios</h1>
      </div>
      <button class="btn btn-soft" type="button" data-demo-action>Exportar demonstração</button>
    </div>

    <section class="grid-4">
      <article class="card kpi"><span>Vendas no mês</span><strong>R$ 4.280</strong></article>
      <article class="card kpi"><span>Pedidos</span><strong>38</strong></article>
      <article class="card kpi"><span>Ticket médio</span><strong>R$ 112</strong></article>
      <article class="card kpi"><span>Produto destaque</span><strong>Buquê</strong></article>
    </section>

    <section class="admin-panel grid-2">
      <div class="panel">
        <h2>Produtos mais vendidos</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Produto</th><th>Qtd.</th><th>Receita</th></tr></thead>
            <tbody>
              <tr><td>Buquê de Rosas Vermelhas</td><td>12</td><td>R$ 1.558,80</td></tr>
              <tr><td>Arranjo Floral Premium</td><td>8</td><td>R$ 1.519,20</td></tr>
              <tr><td>Buquê Tons Pastel</td><td>6</td><td>R$ 719,40</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <h2>Pedidos por status</h2>
        <div class="fake-chart" aria-label="Gráfico fictício de pedidos por status">
          <span style="--bar: 86%">Receb.</span>
          <span style="--bar: 64%">Preparo</span>
          <span style="--bar: 48%">Entrega</span>
          <span style="--bar: 72%">Final.</span>
        </div>
      </div>

      <div class="panel">
        <h2>Estoque baixo</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Produto</th><th>Estoque</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td>Kit Presente Romântico</td><td>2</td><td><span class="status">Atenção</span></td></tr>
              <tr><td>Arranjo Floral Premium</td><td>3</td><td><span class="status">Atenção</span></td></tr>
              <tr><td>Arranjo Dia das Mães</td><td>0</td><td><span class="status">Sob encomenda</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <h2>Resumo financeiro</h2>
        <div class="summary-line"><span>Recebimentos</span><strong class="finance-positive">R$ 4.280,00</strong></div>
        <div class="summary-line"><span>Despesas</span><strong class="finance-negative">R$ 1.120,00</strong></div>
        <div class="summary-line"><span>Saldo visual</span><strong>R$ 3.160,00</strong></div>
      </div>
    </section>
  </main>
</div>
<div class="toast" data-toast role="status" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
