<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'produto-form';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Novo produto | Arte&Flor</title>
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
        <span class="badge">Cadastro visual</span>
        <h1 class="section-title">Produto</h1>
      </div>
      <a class="btn btn-soft" href="<?= site_url('admin/produtos.php') ?>">Voltar</a>
    </div>

    <form class="card form-grid" data-demo-form>
      <label class="form-group">
        <span>Nome</span>
        <input required placeholder="Ex: Buquê Tons Pastel">
      </label>
      <label class="form-group">
        <span>Categoria</span>
        <select>
          <option>Buquês</option>
          <option>Arranjos</option>
          <option>Vasos</option>
          <option>Presentes</option>
          <option>Plantas</option>
          <option>Datas especiais</option>
        </select>
      </label>
      <label class="form-group">
        <span>Preço</span>
        <input type="number" step="0.01" placeholder="0,00">
      </label>
      <label class="form-group">
        <span>Preço promocional</span>
        <input type="number" step="0.01" placeholder="Opcional">
      </label>
      <label class="form-group">
        <span>Estoque</span>
        <input type="number" min="0">
      </label>
      <label class="form-group">
        <span>Status</span>
        <select>
          <option>Disponível</option>
          <option>Sob encomenda</option>
          <option>Inativo</option>
        </select>
      </label>
      <label class="form-group full">
        <span>Descrição curta</span>
        <input placeholder="Resumo para cards do catálogo">
      </label>
      <label class="form-group full">
        <span>Descrição completa</span>
        <textarea placeholder="Detalhes, composição, prazo, cuidados e observações"></textarea>
      </label>
      <label class="form-group full">
        <span>Múltiplas imagens</span>
        <input type="file" multiple accept="image/*">
      </label>
      <label class="form-group">
        <span>Destaque</span>
        <select><option>Sim</option><option>Não</option></select>
      </label>
      <label class="form-group">
        <span>Sob encomenda</span>
        <select><option>Não</option><option>Sim</option></select>
      </label>
      <button class="btn btn-primary" type="submit">Salvar demonstração</button>
    </form>
  </main>
</div>
<div class="toast" data-toast role="status" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
