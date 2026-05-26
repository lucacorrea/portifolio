<?php
$pageTitle = 'Catálogo';
$activePage = 'catalogo';
require_once __DIR__ . '/includes/products.php';

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'categoria' => trim((string) ($_GET['categoria'] ?? '')),
    'status' => (string) ($_GET['status'] ?? ''),
    'destaque' => isset($_GET['destaque']) && (string) $_GET['destaque'] === '1',
    'limit' => 120,
];
$produtos = product_public_list($filters);
$categorias = product_public_categories();
$statusOptions = [
    '' => 'Todos os status',
    'disponivel' => 'Disponíveis',
    'sob_encomenda' => 'Sob encomenda',
    'sem_estoque' => 'Sem estoque',
];

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header catalog-header">
  <div class="container page-header-grid">
    <div>
      <span class="badge">Catálogo Arte&Flor</span>
      <h1 class="section-title">Escolha flores, presentes e encomendas</h1>
      <p class="section-subtitle">Produtos reais cadastrados no admin, com disponibilidade, estoque e imagens atualizados pelo banco.</p>
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
    <form class="catalog-toolbar catalog-toolbar-public" method="get" action="<?= site_url('catalogo.php') ?>">
      <label class="catalog-search">
        <span class="sr-only">Buscar produto</span>
        <input name="search" type="search" value="<?= e($filters['search']) ?>" placeholder="Buscar por nome ou SKU">
      </label>
      <div class="catalog-filter-grid">
        <label class="form-group">
          <span>Categoria</span>
          <select name="categoria">
            <option value="">Todas</option>
            <?php foreach ($categorias as $categoria): ?>
              <?php $categorySlug = (string) ($categoria['slug'] ?? ''); ?>
              <option value="<?= e($categorySlug) ?>" <?= $filters['categoria'] === $categorySlug ? 'selected' : '' ?>>
                <?= e((string) $categoria['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="form-group">
          <span>Status</span>
          <select name="status">
            <?php foreach ($statusOptions as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="catalog-check">
          <input name="destaque" type="checkbox" value="1" <?= $filters['destaque'] ? 'checked' : '' ?>>
          <span>Somente destaques</span>
        </label>
        <div class="catalog-filter-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Limpar</a>
        </div>
      </div>
    </form>

    <?php if (empty($produtos)): ?>
      <div class="empty-results">
        <strong>Nenhum produto disponível no momento.</strong>
        <p>Altere os filtros ou volte mais tarde. Produtos ativos no admin aparecem aqui automaticamente.</p>
      </div>
    <?php else: ?>
      <div class="grid-3 product-grid-spaced" data-products-grid>
        <?php foreach ($produtos as $produto): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
