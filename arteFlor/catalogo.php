<?php
$pageTitle = 'Catálogo';
$activePage = 'catalogo';
$pageScripts = ['js/catalogo.js'];
require_once __DIR__ . '/includes/header.php';
$produtos = load_json('produtos.json');
$categorias = array_values(array_unique(array_map(fn($p) => $p['categoria'], $produtos)));
sort($categorias);
?>
<style>
/* =========================================================
   CATÁLOGO — ORGANIZAÇÃO DA BUSCA E FILTROS
   CSS interno escopado para catalogo.php
========================================================= */

.catalog-header {
  background: linear-gradient(135deg, #edf3e9 0%, #fbf4ec 100%);
  border-bottom: 1px solid rgba(47, 72, 58, .12);
}

.catalog-header .page-header-grid {
  align-items: center;
}

.catalog-header .badge {
  border-radius: 10px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  color: #244836;
  box-shadow: none;
}

.catalog-header .section-title {
  max-width: 720px;
}

.catalog-mini-summary {
  min-width: 240px;
  padding: 22px;
  border-radius: 16px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: 0 12px 28px rgba(45, 55, 48, .07);
}

.catalog-mini-summary strong {
  color: #244836;
}

.catalog-mini-summary .btn {
  border-radius: 10px;
}

/* ---------- Barra principal ---------- */

.catalog-toolbar {
  position: static;
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(280px, 380px);
  gap: 18px;
  align-items: stretch;
  margin-bottom: 34px;
  padding: 18px;
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: 0 14px 34px rgba(45, 55, 48, .07);
  backdrop-filter: none;
}

.catalog-toolbar::before {
  content: "Filtrar catálogo";
  grid-column: 1 / -1;
  display: block;
  margin-bottom: -2px;
  color: #244836;
  font-size: .78rem;
  font-weight: 900;
  letter-spacing: .08em;
  text-transform: uppercase;
}

/* ---------- Filtros ---------- */

.catalog-toolbar .filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  box-shadow: none;
  backdrop-filter: none;
}

.filter-pill {
  min-height: 42px;
  padding: 9px 14px;
  border-radius: 10px;
  background: #f7f1e8;
  border: 1px solid rgba(47, 72, 58, .11);
  color: #4f5b53;
  font-size: .88rem;
  font-weight: 850;
  line-height: 1;
  box-shadow: none;
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    color 160ms ease,
    box-shadow 160ms ease;
}

.filter-pill:hover {
  transform: none;
  background: #eef4ea;
  border-color: rgba(47, 72, 58, .18);
  color: #244836;
  box-shadow: none;
}

.filter-pill.active {
  background: #244836;
  border-color: #244836;
  color: #fff;
  box-shadow: none;
}

/* ---------- Busca ---------- */

.catalog-search {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 0;
}

.catalog-search::before {
  content: "Buscar";
  position: absolute;
  top: -9px;
  left: 13px;
  z-index: 2;
  padding: 0 6px;
  background: #fffdf8;
  color: #244836;
  font-size: .68rem;
  font-weight: 900;
  letter-spacing: .07em;
  text-transform: uppercase;
}

.catalog-search::after {
  content: "⌕";
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #6f7b72;
  font-size: 1.05rem;
  font-weight: 900;
  pointer-events: none;
}

.catalog-search input {
  width: 100%;
  min-height: 46px;
  padding: 0 44px 0 14px;
  border-radius: 10px;
  background: #ffffff;
  border: 1px solid rgba(47, 72, 58, .14);
  color: #2f3631;
  font-size: .95rem;
  font-weight: 700;
  outline: none;
  box-shadow: none;
  transition:
    border-color 160ms ease,
    box-shadow 160ms ease,
    background-color 160ms ease;
}

.catalog-search input::placeholder {
  color: #8a928c;
  font-weight: 650;
}

.catalog-search input:focus {
  background: #fff;
  border-color: #4f8062;
  box-shadow: 0 0 0 3px rgba(79, 128, 98, .12);
}

/* ---------- Grid de produtos abaixo da barra ---------- */

.product-grid-spaced {
  margin-top: 0;
}

/* ---------- Estado vazio ---------- */

.empty-results {
  border-radius: 16px;
  background: #fffdf8;
  border: 1px dashed rgba(47, 72, 58, .22);
  box-shadow: none;
}

/* ---------- Responsivo ---------- */

@media (max-width: 920px) {
  .catalog-toolbar {
    grid-template-columns: 1fr;
  }

  .catalog-search {
    order: -1;
  }
}

@media (max-width: 640px) {
  .catalog-toolbar {
    padding: 14px;
    border-radius: 14px;
  }

  .catalog-toolbar .filters {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .filter-pill {
    width: 100%;
    justify-content: center;
    text-align: center;
  }
}

@media (max-width: 420px) {
  .catalog-toolbar .filters {
    grid-template-columns: 1fr;
  }
}
</style>
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

    
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
