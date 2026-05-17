<?php
$pageTitle = 'Catálogo';
$activePage = 'catalogo';
require_once __DIR__ . '/includes/header.php';
$produtos = load_json('produtos.json');
$categorias = array_values(array_unique(array_map(fn($p) => $p['categoria'], $produtos)));
?>
<section class="page-header">
  <div class="container">
    <span class="badge">Catálogo Arte&Flor</span>
    <h1 class="section-title">Escolha seu presente</h1>
    <p class="section-subtitle">Filtre por categoria, consulte detalhes e finalize pelo WhatsApp ou carrinho.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="filters">
      <button class="filter-pill" data-filter="todos">Todos</button>
      <?php foreach ($categorias as $categoria): ?>
        <button class="filter-pill" data-filter="<?= e($categoria) ?>"><?= e($categoria) ?></button>
      <?php endforeach; ?>
      <input class="filter-pill" type="search" data-search placeholder="Buscar produto...">
    </div>
    <div class="grid-3" data-products-grid>
      <?php foreach ($produtos as $produto): ?>
        <div data-product-item data-category="<?= e($produto['categoria']) ?>" data-name="<?= e(strtolower($produto['nome'])) ?>">
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<script src="<?= asset('js/catalogo.js') ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
