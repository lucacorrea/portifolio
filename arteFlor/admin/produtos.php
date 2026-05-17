<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'produtos';
$produtos = load_json('produtos.json');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Produtos | Arte&Flor</title>
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
        <span class="badge">Produtos fictícios</span>
        <h1 class="section-title">Produtos</h1>
      </div>
      <a class="btn btn-primary" href="<?= site_url('admin/produto-form.php') ?>">Novo produto</a>
    </div>

    <div class="panel">
      <div class="catalog-toolbar">
        <label class="form-group">
          <span>Buscar</span>
          <input class="form-control" type="search" data-admin-search placeholder="Nome, categoria ou status">
        </label>
        <label class="form-group">
          <span>Filtro visual</span>
          <select class="form-control" data-demo-action>
            <option>Todos</option>
            <option>Disponível</option>
            <option>Sob encomenda</option>
            <option>Estoque baixo</option>
          </select>
        </label>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Estoque</th><th>Ações</th></tr></thead>
          <tbody>
            <?php foreach ($produtos as $p): ?>
              <tr data-admin-row>
                <td><?= e($p['nome']) ?></td>
                <td><?= e($p['categoria']) ?></td>
                <td><?= product_price($p) > 0 ? money_br(product_price($p)) : 'Consultar' ?></td>
                <td><span class="status"><?= e($p['status']) ?></span></td>
                <td><?= (int) $p['estoque'] ?></td>
                <td>
                  <div class="admin-actions">
                    <button class="btn btn-soft" type="button" data-demo-action>Editar</button>
                    <button class="btn btn-outline" type="button" data-demo-action>Visualizar</button>
                    <button class="btn btn-danger" type="button" data-demo-action>Remover</button>
                  </div>
                </td>
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
