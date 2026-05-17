<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'pedidos';
$pedidos = load_json('pedidos-demo.json');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pedidos | Arte&Flor</title>
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
        <span class="badge">Pedidos fictícios</span>
        <h1 class="section-title">Pedidos</h1>
      </div>
      <button class="btn btn-soft" type="button" data-demo-action>Atualizar lista</button>
    </div>

    <div class="panel">
      <div class="catalog-toolbar">
        <label class="form-group">
          <span>Buscar pedido</span>
          <input class="form-control" type="search" data-admin-search placeholder="Cliente, status ou código">
        </label>
        <label class="form-group">
          <span>Status</span>
          <select class="form-control" data-demo-action>
            <option>Todos</option>
            <option>Pedido recebido</option>
            <option>Em preparo</option>
            <option>Saiu para entrega</option>
            <option>Finalizado</option>
          </select>
        </label>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Pagamento</th><th>Entrega</th><th>Total</th><th>Ação</th></tr></thead>
          <tbody>
            <?php foreach ($pedidos as $pedido): ?>
              <tr data-admin-row>
                <td>#<?= e($pedido['codigo']) ?></td>
                <td><?= e($pedido['cliente']) ?></td>
                <td><span class="status"><?= e($pedido['status']) ?></span></td>
                <td><?= e($pedido['pagamento']) ?></td>
                <td><?= e($pedido['entrega']) ?></td>
                <td><?= money_br((float) $pedido['total']) ?></td>
                <td><button class="btn btn-soft" type="button" data-demo-action>Ver detalhes</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<div class="toast" data-toast role="status" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
