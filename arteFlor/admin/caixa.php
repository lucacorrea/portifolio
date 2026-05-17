<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'caixa';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Caixa | Arte&Flor</title>
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
        <span class="badge">Financeiro visual</span>
        <h1 class="section-title">Caixa</h1>
      </div>
    </div>

    <section class="grid-3">
      <article class="card kpi"><span>Recebimentos</span><strong class="finance-positive">R$ 820</strong></article>
      <article class="card kpi"><span>Despesas</span><strong class="finance-negative">R$ 140</strong></article>
      <article class="card kpi"><span>Saldo visual</span><strong>R$ 680</strong></article>
    </section>

    <section class="admin-panel grid-2">
      <form class="card form-grid" data-demo-form>
        <label class="form-group">
          <span>Tipo</span>
          <select><option>Recebimento</option><option>Despesa</option></select>
        </label>
        <label class="form-group">
          <span>Valor</span>
          <input type="number" step="0.01">
        </label>
        <label class="form-group">
          <span>Forma de pagamento</span>
          <select><option>Pix</option><option>Dinheiro</option><option>Cartão na entrega</option><option>Presencial</option></select>
        </label>
        <label class="form-group">
          <span>Data</span>
          <input type="date">
        </label>
        <label class="form-group full">
          <span>Descrição</span>
          <textarea></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Registrar demonstração</button>
      </form>

      <div class="panel">
        <h2>Histórico fictício</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Descrição</th><th>Forma</th><th>Valor</th></tr></thead>
            <tbody>
              <tr><td>Pedido #AF-1025</td><td>Pix</td><td class="finance-positive">R$ 189,90</td></tr>
              <tr><td>Compra de embalagens</td><td>Dinheiro</td><td class="finance-negative">R$ 48,00</td></tr>
              <tr><td>Pedido #AF-1024</td><td>Presencial</td><td class="finance-positive">R$ 119,90</td></tr>
              <tr><td>Reposição de folhagens</td><td>Pix</td><td class="finance-negative">R$ 92,00</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
</div>
<div class="toast" data-toast role="status" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
