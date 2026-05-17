<?php
$pageTitle = 'Catálogo';
$activePage = 'catalogo';
$pageScripts = ['js/catalogo.js'];
require_once __DIR__ . '/includes/header.php';

$produtos = load_json('produtos.json');
$categorias = array_values(array_unique(array_map(function ($p) {
    return $p['categoria'];
}, $produtos)));
?>
<section class="page-hero" style="--page-image: url('https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=1600&q=80');">
  <div class="container">
    <span class="badge">Catálogo Arte&Flor</span>
    <h1 class="section-title">Flores, presentes e encomendas</h1>
    <p class="section-subtitle">Busque por produto, filtre por categoria ou disponibilidade e simule a compra com carrinho local.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="catalog-toolbar">
      <div>
        <label class="form-label" for="catalogSearch">Buscar produto</label>
        <input class="search-field" id="catalogSearch" type="search" data-search placeholder="Ex: buquê, vaso, rosas">
      </div>
      <span class="product-count" data-result-count><?= count($produtos) ?> produtos encontrados</span>
    </div>

    <div class="filters" aria-label="Filtros de categoria">
      <button class="filter-pill active" type="button" data-filter-group="category" data-filter-value="todos">Todos</button>
      <?php foreach ($categorias as $categoria): ?>
        <button class="filter-pill" type="button" data-filter-group="category" data-filter-value="<?= e($categoria) ?>"><?= e($categoria) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="filters" aria-label="Filtros de disponibilidade">
      <button class="filter-pill active" type="button" data-filter-group="availability" data-filter-value="todos">Todas as opções</button>
      <button class="filter-pill" type="button" data-filter-group="availability" data-filter-value="disponivel">Disponível</button>
      <button class="filter-pill" type="button" data-filter-group="availability" data-filter-value="sob_encomenda">Sob encomenda</button>
    </div>

    <div class="grid-3" data-products-grid>
      <?php foreach ($produtos as $produto): ?>
        <div
          data-product-item
          data-category="<?= e($produto['categoria']) ?>"
          data-availability="<?= e($produto['disponibilidade'] ?? 'disponivel') ?>"
          data-name="<?= e(strtolower($produto['nome'] . ' ' . $produto['descricao_curta'] . ' ' . $produto['categoria'])) ?>"
        >
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="empty-state" data-empty-products hidden>Nenhum produto encontrado para os filtros selecionados.</div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
