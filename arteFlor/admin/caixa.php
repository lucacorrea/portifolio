<?php
$adminTitle = 'Frente de caixa';
$activeAdmin = 'caixa';

require_once __DIR__ . '/../includes/admin-head.php';

$produtos = load_json('produtos.json');

$pdvProdutos = array_map(fn($p) => [
    'id' => (string) ($p['id'] ?? ''),
    'sku' => $p['sku'] ?? '',
    'nome' => $p['nome'] ?? '',
    'categoria' => $p['categoria'] ?? '',
    'preco' => effective_price($p),
    'imagem' => first_image($p),
], $produtos);

$categorias = array_values(array_unique(array_map(fn($p) => $p['categoria'], $produtos)));
sort($categorias);
?>

<style>
/* =========================================================
   ARTE&FLOR ADMIN — PDV FULLSCREEN ORGANIZADO
   Caixa em tela cheia, objetivo e com visualização de produto
========================================================= */

.admin-shell:has(.pdv-fullscreen-page) {
  grid-template-columns: 1fr !important;
  min-height: 100vh;
  background: #eef2ed;
}

.admin-shell:has(.pdv-fullscreen-page) .admin-sidebar {
  display: none !important;
}

.admin-main:has(.pdv-fullscreen-page) {
  height: 100vh;
  overflow: hidden;
  padding: 10px !important;
  background: #eef2ed;
}

.pdv-fullscreen-page {
  height: calc(100vh - 20px);
  display: grid;
  grid-template-rows: 62px minmax(0, 1fr);
  gap: 10px;
  color: #203f30;
}

/* ---------- Topo ---------- */

.pdv-topbar {
  display: grid;
  grid-template-columns: 250px minmax(260px, 1fr) auto;
  gap: 10px;
  min-height: 62px;
}

.pdv-brand-box,
.pdv-status-box,
.pdv-top-actions {
  border-radius: 12px;
  background: #fffdf8;
  border: 1px solid rgba(32, 63, 48, .12);
  box-shadow: 0 8px 20px rgba(45, 55, 48, .05);
}

.pdv-brand-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
}

.pdv-brand-mark {
  width: 40px;
  height: 40px;
  min-width: 40px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: #dfeadd;
  border: 1px solid rgba(32, 63, 48, .12);
  color: #203f30;
  font-weight: 950;
}

.pdv-brand-box strong {
  display: block;
  color: #203f30;
  font-size: .98rem;
  font-weight: 950;
  line-height: 1.1;
}

.pdv-brand-box span {
  display: block;
  margin-top: 2px;
  color: #647067;
  font-size: .76rem;
  font-weight: 750;
}

.pdv-status-box {
  display: grid;
  place-items: center;
  background: #203f30;
  color: #fff;
  text-align: center;
  padding: 8px 16px;
}

.pdv-status-box small {
  display: block;
  color: rgba(255,255,255,.72);
  font-size: .66rem;
  font-weight: 900;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.pdv-status-box strong {
  display: block;
  color: #fff;
  font-size: clamp(1.35rem, 3vw, 2.15rem);
  font-weight: 950;
  letter-spacing: .04em;
  line-height: 1;
}

.pdv-top-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px;
}

.pdv-fullscreen-page .btn {
  min-height: 38px;
  padding: 9px 12px;
  border-radius: 9px;
  font-size: .82rem;
  font-weight: 850;
  box-shadow: none;
  white-space: nowrap;
}

.pdv-fullscreen-page .btn:hover {
  transform: none;
}

.pdv-fullscreen-page .btn-primary {
  background: #203f30;
  border-color: #203f30;
  color: #fff;
}

.pdv-fullscreen-page .btn-primary:hover {
  background: #173327;
}

.pdv-fullscreen-page .btn-soft,
.pdv-fullscreen-page .btn-outline {
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .14);
  color: #203f30;
}

.pdv-fullscreen-page .btn-soft:hover,
.pdv-fullscreen-page .btn-outline:hover {
  background: #edf3e9;
}

/* ---------- Estrutura principal ---------- */

.pdv-workspace {
  min-height: 0;
  display: grid;
  grid-template-columns: 300px minmax(430px, 1fr) 330px;
  gap: 10px;
}

.pdv-box {
  min-height: 0;
  overflow: hidden;
  border-radius: 12px;
  background: #fffdf8;
  border: 1px solid rgba(32, 63, 48, .12);
  box-shadow: 0 8px 20px rgba(45, 55, 48, .05);
}

.pdv-box-header {
  min-height: 42px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  background: #dfeadd;
  border-bottom: 1px solid rgba(32, 63, 48, .10);
}

.pdv-box-header h2 {
  margin: 0;
  color: #203f30;
  font-family: var(--fonte-corpo, system-ui);
  font-size: .94rem;
  font-weight: 950;
}

.pdv-box-header span,
.pdv-box-header small {
  color: #647067;
  font-size: .72rem;
  font-weight: 800;
}

.pdv-box-body {
  padding: 10px;
}

/* ---------- Coluna esquerda ---------- */

.pdv-entry-column {
  display: grid;
  grid-template-rows: 42px minmax(0, 1fr);
}

.pdv-entry-body {
  min-height: 0;
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr);
  gap: 10px;
}

/* Produto selecionado */

.pdv-selected-card {
  min-height: 235px;
  display: grid;
  grid-template-rows: 150px auto;
  overflow: hidden;
  border-radius: 11px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .12);
}

.pdv-selected-image {
  position: relative;
  display: grid;
  place-items: center;
  overflow: hidden;
  background: linear-gradient(135deg, #edf3e9, #f8f1e8);
  border-bottom: 1px solid rgba(32, 63, 48, .10);
}

.pdv-selected-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pdv-selected-placeholder {
  display: grid;
  place-items: center;
  gap: 6px;
  color: #647067;
  text-align: center;
}

.pdv-selected-placeholder strong {
  color: #203f30;
  font-size: 2.1rem;
  line-height: 1;
}

.pdv-selected-info {
  display: grid;
  gap: 4px;
  padding: 10px;
}

.pdv-selected-info small {
  color: #647067;
  font-size: .68rem;
  font-weight: 900;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.pdv-selected-info strong {
  color: #203f30;
  font-size: .95rem;
  font-weight: 950;
  line-height: 1.18;
}

.pdv-selected-info span {
  color: #82495c;
  font-size: .9rem;
  font-weight: 950;
}

/* Entrada rápida */

.pdv-fast-form {
  display: grid;
  gap: 9px;
}

.pdv-field {
  display: grid;
  gap: 5px;
}

.pdv-field span {
  color: #203f30;
  font-size: .7rem;
  font-weight: 950;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.pdv-field input,
.pdv-field select {
  width: 100%;
  min-height: 38px;
  padding: 0 10px;
  border-radius: 9px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .14);
  color: #203f30;
  font-size: .9rem;
  font-weight: 800;
  outline: none;
}

.pdv-field input::placeholder {
  color: #8b948d;
  font-weight: 650;
}

.pdv-field input:focus,
.pdv-field select:focus {
  border-color: #4f8062;
  box-shadow: 0 0 0 3px rgba(79, 128, 98, .12);
}

.pdv-code-field input {
  min-height: 48px;
  font-size: 1rem;
  font-weight: 900;
}

.pdv-qty-add {
  display: grid;
  grid-template-columns: 76px 1fr;
  gap: 8px;
  align-items: end;
}

/* Pagamento compacto */

.pdv-payment-compact {
  align-self: end;
  display: grid;
  gap: 8px;
  padding: 10px;
  border-radius: 11px;
  background: #f8f1e8;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-payment-compact-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.pdv-payment-compact-title strong {
  color: #203f30;
  font-size: .84rem;
  font-weight: 950;
}

.pdv-payment-compact-title span {
  color: #647067;
  font-size: .72rem;
  font-weight: 800;
}

.pdv-payment-compact .pdv-field input,
.pdv-payment-compact .pdv-field select {
  min-height: 36px;
}

/* ---------- Centro: venda ---------- */

.pdv-sale-column {
  display: grid;
  grid-template-rows: 42px minmax(0, 1fr) 184px;
}

.pdv-sale-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pdv-badge {
  min-height: 24px;
  padding: 4px 8px;
  border-radius: 7px;
  background: #fff;
  color: #203f30;
  border: 1px solid rgba(32, 63, 48, .12);
  font-size: .66rem;
  font-weight: 950;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.pdv-current-items {
  min-height: 0;
  height: 100%;
  overflow-y: auto;
  display: grid;
  align-content: start;
  gap: 7px;
  padding: 10px;
  background: #fff;
}

.pdv-current-items:empty::before {
  content: "Venda sem itens. Leia o código, pesquise pelo nome ou selecione um produto rápido.";
  display: grid;
  place-items: center;
  min-height: 100%;
  padding: 22px;
  border-radius: 10px;
  background: #fbf8f2;
  border: 1px dashed rgba(32, 63, 48, .18);
  color: #647067;
  text-align: center;
  font-size: .92rem;
  font-weight: 800;
  line-height: 1.45;
}

/* Itens adicionados pelo JS */
.pdv-sale-item {
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
  padding: 8px;
  border-radius: 9px;
  background: #fffdf8;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-sale-item img,
.pdv-sale-item > span {
  width: 56px;
  height: 56px;
  border-radius: 7px;
  object-fit: cover;
  background: #edf3e9;
}

.pdv-sale-item strong {
  color: #203f30;
  font-size: .88rem;
  font-weight: 950;
  line-height: 1.15;
}

.pdv-sale-item small,
.pdv-sale-item span {
  color: #647067;
  font-size: .72rem;
  font-weight: 750;
}

.pdv-sale-item button {
  min-height: 30px;
  padding: 6px 9px;
  border-radius: 7px;
  background: #f5e8e8;
  border: 1px solid rgba(139, 63, 77, .14);
  color: #8b3f4d;
  font-size: .72rem;
  font-weight: 850;
}

/* Totais */

.pdv-total-board {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  padding: 10px;
  background: #f8f1e8;
  border-top: 1px solid rgba(32, 63, 48, .10);
}

.pdv-total-line {
  min-height: 46px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 11px;
  border-radius: 9px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-total-line span {
  color: #647067;
  font-size: .74rem;
  font-weight: 900;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.pdv-total-line strong {
  color: #203f30;
  font-size: 1.05rem;
  font-weight: 950;
  white-space: nowrap;
}

.pdv-total-line.main {
  grid-column: 1 / -1;
  min-height: 64px;
  background: #203f30;
  border-color: #203f30;
}

.pdv-total-line.main span {
  color: rgba(255,255,255,.72);
}

.pdv-total-line.main strong {
  color: #fff;
  font-size: clamp(2rem, 4vw, 2.85rem);
  line-height: 1;
}

.pdv-discount-input {
  width: 110px;
  min-height: 34px;
  border-radius: 7px;
  border: 1px solid rgba(32, 63, 48, .14);
  background: #fff;
  color: #203f30;
  text-align: right;
  padding: 0 8px;
  font-weight: 900;
  outline: none;
}

.pdv-actions-row {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: 1fr 1fr 1.35fr;
  gap: 8px;
}

/* ---------- Direita: produtos rápidos ---------- */

.pdv-products-column {
  display: grid;
  grid-template-rows: 42px auto minmax(0, 1fr);
}

.pdv-category-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 10px;
  background: #f8f1e8;
  border-bottom: 1px solid rgba(32, 63, 48, .10);
}

.filter-pill {
  min-height: 30px;
  padding: 6px 8px;
  border-radius: 7px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .12);
  color: #4d5a52;
  font-size: .7rem;
  font-weight: 850;
  box-shadow: none;
}

.filter-pill:hover {
  transform: none;
  background: #edf3e9;
}

.filter-pill.active {
  background: #203f30;
  border-color: #203f30;
  color: #fff;
}

.pdv-product-grid {
  min-height: 0;
  overflow-y: auto;
  display: grid;
  align-content: start;
  gap: 7px;
  padding: 10px;
}

/* Cards gerados pelo JS */
.pdv-product-card {
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr);
  gap: 8px;
  align-items: center;
  padding: 7px;
  border-radius: 9px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .11);
  text-align: left;
  cursor: pointer;
}

.pdv-product-card:hover {
  transform: none;
  background: #fffdf8;
  border-color: rgba(32, 63, 48, .22);
}

.pdv-product-card img {
  width: 52px;
  height: 52px;
  object-fit: cover;
  border-radius: 7px;
  background: #edf3e9;
}

.pdv-product-card strong {
  color: #203f30;
  font-size: .8rem;
  font-weight: 950;
  line-height: 1.15;
}

.pdv-product-card span,
.pdv-product-card small {
  color: #647067;
  font-size: .68rem;
  font-weight: 750;
}

.pdv-product-card .price,
.pdv-product-card [class*="price"] {
  color: #82495c;
  font-size: .82rem;
  font-weight: 950;
}

/* Histórico oculto para não quebrar JS */
.pdv-hidden-history {
  display: none !important;
}

/* Scroll */
.pdv-current-items::-webkit-scrollbar,
.pdv-product-grid::-webkit-scrollbar {
  width: 7px;
}

.pdv-current-items::-webkit-scrollbar-thumb,
.pdv-product-grid::-webkit-scrollbar-thumb {
  background: rgba(32, 63, 48, .18);
  border-radius: 10px;
}

/* ---------- Notebook ---------- */

@media (max-width: 1366px) {
  .pdv-workspace {
    grid-template-columns: 285px minmax(400px, 1fr) 305px;
  }

  .pdv-topbar {
    grid-template-columns: 235px minmax(260px, 1fr) auto;
  }

  .pdv-status-box strong {
    font-size: 1.8rem;
  }

  .pdv-selected-card {
    min-height: 220px;
    grid-template-rows: 135px auto;
  }

  .pdv-total-line.main strong {
    font-size: 2.35rem;
  }
}

@media (max-width: 1180px) {
  .admin-main:has(.pdv-fullscreen-page) {
    overflow-y: auto;
  }

  .pdv-fullscreen-page {
    height: auto;
    min-height: calc(100vh - 20px);
    grid-template-rows: auto auto;
  }

  .pdv-topbar,
  .pdv-workspace {
    grid-template-columns: 1fr;
  }

  .pdv-entry-column,
  .pdv-sale-column,
  .pdv-products-column {
    grid-template-rows: auto;
  }

  .pdv-current-items {
    min-height: 300px;
    max-height: 420px;
  }

  .pdv-selected-card {
    grid-template-rows: 220px auto;
  }
}
</style>

<div class="pdv-fullscreen-page">
  <section class="pdv-topbar">
    <div class="pdv-brand-box">
      <span class="pdv-brand-mark">A&F</span>
      <div>
        <strong>Arte&Flor PDV</strong>
        <span>Venda presencial</span>
      </div>
    </div>

    <div class="pdv-status-box">
      <div>
        <small>Status operacional</small>
        <strong>CAIXA ABERTO</strong>
      </div>
    </div>

    <div class="pdv-top-actions">
      <a class="btn btn-soft" href="<?= site_url('admin/dashboard.php') ?>">Dashboard</a>
      <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar</button>
      <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar</button>
    </div>
  </section>

  <script type="application/json" id="pdvProducts"><?= json_encode($pdvProdutos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <section class="pdv-workspace">
    <!-- Produto selecionado + entrada -->
    <aside class="pdv-box pdv-entry-column">
      <div class="pdv-box-header">
        <div>
          <h2>Produto selecionado</h2>
          <span>Imagem e dados do item</span>
        </div>
      </div>

      <div class="pdv-box-body pdv-entry-body">
        <div class="pdv-selected-card" data-pdv-selected-preview>
          <div class="pdv-selected-image" data-pdv-selected-image>
            <div class="pdv-selected-placeholder">
              <strong>🛒</strong>
              <span>Nenhum produto selecionado</span>
            </div>
          </div>

          <div class="pdv-selected-info">
            <small>Último item adicionado</small>
            <strong data-pdv-selected-name>Aguardando produto</strong>
            <span data-pdv-selected-price>R$ 0,00</span>
          </div>
        </div>

        <div class="pdv-fast-form">
          <label class="pdv-field pdv-code-field">
            <span>Código / SKU / Nome</span>
            <input type="search" data-pdv-search placeholder="Leia ou digite o produto">
          </label>

          <div class="pdv-qty-add">
            <label class="pdv-field">
              <span>Qtd.</span>
              <input type="number" min="1" value="1" data-pdv-qty>
            </label>

            <button class="btn btn-primary" type="button" data-pdv-add-search>Adicionar</button>
          </div>
        </div>

        <div class="pdv-payment-compact">
          <div class="pdv-payment-compact-title">
            <strong>Pagamento</strong>
            <span>Dados mínimos</span>
          </div>

          <label class="pdv-field">
            <span>Cliente</span>
            <input data-pdv-client placeholder="Cliente balcão">
          </label>

          <label class="pdv-field">
            <span>Forma de pagamento</span>
            <select data-pdv-payment>
              <option>Pix</option>
              <option>Dinheiro</option>
              <option>Cartão presencial</option>
              <option>Pagamento na retirada</option>
            </select>
          </label>
        </div>
      </div>
    </aside>

    <!-- Lista da venda -->
    <section class="pdv-box pdv-sale-column">
      <div class="pdv-box-header">
        <div class="pdv-sale-title">
          <span class="pdv-badge">Venda atual</span>
          <div>
            <h2>Lista de produtos</h2>
            <small>Itens registrados no caixa</small>
          </div>
        </div>
        <small>Operador: Admin</small>
      </div>

      <div class="pdv-current-items" data-pdv-current></div>

      <div class="pdv-total-board">
        <div class="pdv-total-line">
          <span>Subtotal</span>
          <strong data-pdv-subtotal>R$ 0,00</strong>
        </div>

        <div class="pdv-total-line">
          <span>Desconto</span>
          <input class="pdv-discount-input" type="number" min="0" step="0.01" value="0" data-pdv-discount>
        </div>

        <div class="pdv-total-line main">
          <span>Total da venda</span>
          <strong data-pdv-total>R$ 0,00</strong>
        </div>

        <div class="pdv-actions-row">
          <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender</button>
          <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar</button>
          <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
        </div>
      </div>
    </section>

    <!-- Produtos rápidos -->
    <aside class="pdv-box pdv-products-column">
      <div class="pdv-box-header">
        <div>
          <h2>Produtos rápidos</h2>
          <span>Selecione para adicionar</span>
        </div>
      </div>

      <div class="pdv-category-pills">
        <button class="filter-pill active" type="button" data-pdv-category="todos">Todos</button>
        <?php foreach ($categorias as $categoria): ?>
          <button class="filter-pill" type="button" data-pdv-category="<?= e($categoria) ?>"><?= e($categoria) ?></button>
        <?php endforeach; ?>
      </div>

      <div class="pdv-product-grid" data-pdv-product-grid></div>
    </aside>
  </section>

  <div class="pdv-hidden-history" data-pdv-history></div>
</div>

<script>
(() => {
  const currentList = document.querySelector('[data-pdv-current]');
  const selectedImage = document.querySelector('[data-pdv-selected-image]');
  const selectedName = document.querySelector('[data-pdv-selected-name]');
  const selectedPrice = document.querySelector('[data-pdv-selected-price]');

  if (!currentList || !selectedImage || !selectedName || !selectedPrice) return;

  function updateSelectedPreview() {
    const items = [...currentList.querySelectorAll('.pdv-sale-item')];
    const lastItem = items[items.length - 1];

    if (!lastItem) {
      selectedImage.innerHTML = `
        <div class="pdv-selected-placeholder">
          <strong>🛒</strong>
          <span>Nenhum produto selecionado</span>
        </div>
      `;
      selectedName.textContent = 'Aguardando produto';
      selectedPrice.textContent = 'R$ 0,00';
      return;
    }

    const image = lastItem.querySelector('img');
    const name = lastItem.querySelector('strong')?.textContent?.trim() || 'Produto selecionado';

    const priceText = [...lastItem.querySelectorAll('span, small, strong')]
      .map((el) => el.textContent.trim())
      .find((text) => text.includes('R$')) || '';

    if (image?.src) {
      selectedImage.innerHTML = `<img src="${image.src}" alt="${name}">`;
    } else {
      selectedImage.innerHTML = `
        <div class="pdv-selected-placeholder">
          <strong>A&F</strong>
          <span>Produto sem imagem</span>
        </div>
      `;
    }

    selectedName.textContent = name;
    selectedPrice.textContent = priceText || 'Item adicionado';
  }

  const observer = new MutationObserver(updateSelectedPreview);

  observer.observe(currentList, {
    childList: true,
    subtree: true
  });

  updateSelectedPreview();
})();
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>