<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'dashboard';
$produtos = load_json('produtos.json');
$pedidos = load_json('pedidos-demo.json');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | Arte&Flor</title>
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
        <span class="badge">MVP demonstrativo</span>
        <h1 class="section-title">Dashboard</h1>
      </div>
      <a class="btn btn-primary" href="<?= site_url('admin/produto-form.php') ?>">Novo produto</a>
    </div>

    <section class="grid-3">
      <article class="card kpi"><span>Vendas do dia</span><strong>R$ 820</strong></article>
      <article class="card kpi"><span>Pedidos pendentes</span><strong>7</strong></article>
      <article class="card kpi"><span>Produtos cadastrados</span><strong><?= count($produtos) ?></strong></article>
      <article class="card kpi"><span>Estoque baixo</span><strong>3</strong></article>
      <article class="card kpi"><span>Recebimentos</span><strong>R$ 1.240</strong></article>
      <article class="card kpi"><span>Despesas</span><strong>R$ 310</strong></article>
    </section>

    <section class="admin-panel">
      <div class="panel">
        <div class="admin-header">
          <h2>Movimento da semana</h2>
          <span class="muted">Gráfico visual fictício</span>
        </div>
        <div class="fake-chart" aria-label="Gráfico fictício de vendas">
          <span style="--bar: 52%">Seg</span>
          <span style="--bar: 64%">Ter</span>
          <span style="--bar: 46%">Qua</span>
          <span style="--bar: 72%">Qui</span>
          <span style="--bar: 88%">Sex</span>
          <span style="--bar: 68%">Sáb</span>
          <span style="--bar: 40%">Dom</span>
        </div>
      </div>

      <div class="grid-2">
        <section class="panel">
          <div class="admin-header">
            <h2>Últimos pedidos</h2>
            <a class="btn btn-soft" href="<?= site_url('admin/pedidos.php') ?>">Ver todos</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Valor</th></tr></thead>
              <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                  <tr>
                    <td>#<?= e($pedido['codigo']) ?></td>
                    <td><?= e($pedido['cliente']) ?></td>
                    <td><span class="status"><?= e($pedido['status']) ?></span></td>
                    <td><?= money_br((float) $pedido['total']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel">
          <div class="admin-header">
            <h2>Mais vendidos</h2>
            <span class="muted">Dados fictícios</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Produto</th><th>Vendas</th><th>Receita</th></tr></thead>
              <tbody>
                <tr><td>Buquê de Rosas Vermelhas</td><td>12</td><td>R$ 1.558,80</td></tr>
                <tr><td>Arranjo Floral Premium</td><td>8</td><td>R$ 1.519,20</td></tr>
                <tr><td>Mini Buquê Delicado</td><td>7</td><td>R$ 419,30</td></tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </section>
  </main>
</div>
<div class="toast" data-toast role="status" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
