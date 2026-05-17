<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'estoque';
$movimentos = load_json('estoque-demo.json');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Estoque | Arte&Flor</title>
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
        <span class="badge">Controle visual</span>
        <h1 class="section-title">Estoque</h1>
      </div>
    </div>

    <section class="grid-4">
      <article class="card kpi"><span>Entradas</span><strong>28</strong></article>
      <article class="card kpi"><span>Saídas</span><strong>17</strong></article>
      <article class="card kpi"><span>Ajustes</span><strong>4</strong></article>
      <article class="card kpi"><span>Perdas</span><strong>3</strong></article>
    </section>

    <section class="admin-panel">
      <form class="card form-grid" data-demo-form>
        <label class="form-group">
          <span>Produto</span>
          <input placeholder="Buquê, vaso, arranjo...">
        </label>
        <label class="form-group">
          <span>Movimentação</span>
          <select><option>Entrada</option><option>Saída</option><option>Ajuste</option><option>Perda</option></select>
        </label>
        <label class="form-group">
          <span>Quantidade</span>
          <input type="number" min="1">
        </label>
        <label class="form-group">
          <span>Responsável</span>
          <input>
        </label>
        <label class="form-group full">
          <span>Motivo</span>
          <textarea></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Registrar demonstração</button>
      </form>

      <div class="panel">
        <h2>Histórico fictício</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Produto</th><th>Tipo</th><th>Qtd.</th><th>Responsável</th><th>Data</th></tr></thead>
            <tbody>
              <?php foreach ($movimentos as $movimento): ?>
                <tr><td><?= e($movimento['produto']) ?></td><td><?= e($movimento['tipo']) ?></td><td><?= (int) $movimento['quantidade'] ?></td><td><?= e($movimento['responsavel']) ?></td><td><?= e($movimento['data']) ?></td></tr>
              <?php endforeach; ?>
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
