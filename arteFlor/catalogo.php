<?php
$pageTitle = 'Catálogo';
$activePage = 'catalogo';
$pageScripts = ['js/catalogo.js'];
require_once __DIR__ . '/includes/header.php';
$produtos = load_json('produtos.json');
$categorias = array_values(array_unique(array_map(fn($p) => $p['categoria'], $produtos)));
sort($categorias);
?>
<section class="page-header catalog-header">
  <div class="container page-header-grid">
    <div>
      <span class="badge">Catálogo Arte&Flor</span>
      <h1 class="section-title">Escolha flores, presentes e encomendas</h1>
      <p class="section-subtitle">Use filtros, veja detalhes e finalize a compra dentro do sistema demonstrativo.</p>
    </div>
    <div class="catalog-mini-summary">
      <strong data-cart-count>0</strong>
      <span>itens no carrinho</span>
      <a class="btn btn-soft" href="<?= site_url('carrinho.php') ?>">Ver carrinho</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="catalog-toolbar">
      <div class="filters" role="list" aria-label="Filtros de categoria">
        <button class="filter-pill active" type="button" data-filter="todos">Todos</button>
        <?php foreach ($categorias as $categoria): ?>
          <button class="filter-pill" type="button" data-filter="<?= e($categoria) ?>"><?= e($categoria) ?></button>
        <?php endforeach; ?>
      </div>
      <label class="catalog-search">
        <span class="sr-only">Buscar produto</span>
        <input type="search" data-search placeholder="Buscar por nome, tag ou categoria">
      </label>
    </div>

    <div class="grid-3 product-grid-spaced" data-products-grid>
      <?php foreach ($produtos as $produto): ?>
        <?php
          $searchText = strtolower($produto['nome'] . ' ' . $produto['categoria'] . ' ' . implode(' ', $produto['tags'] ?? []));
        ?>
        <div data-product-item data-category="<?= e($produto['categoria']) ?>" data-name="<?= e($searchText) ?>">
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="empty-results card" data-empty-products hidden>
      <strong>Nenhum produto encontrado.</strong>
      <p>Altere o termo de busca ou escolha outra categoria para continuar navegando.</p>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
