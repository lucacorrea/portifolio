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

<script>
document.body.classList.add('pdv-market-reference-mode');
</script>

<style>
/* =========================================================
   ARTE&FLOR — PDV REFERÊNCIA SUPERMERCADO
   Baseado no layout clássico de caixa: produto, código,
   lista de venda, subtotal, recebido e troco.
========================================================= */

body.pdv-market-reference-mode .admin-shell {
  grid-template-columns: 1fr !important;
  min-height: 100vh;
  background: #e9efe8;
}

body.pdv-market-reference-mode .admin-sidebar {
  display: none !important;
}

body.pdv-market-reference-mode .admin-main {
  height: 100vh;
  overflow: hidden;
  padding: 8px !important;
  background: #e9efe8;
}

.pdv-terminal {
  height: calc(100vh - 16px);
  display: grid;
  grid-template-rows: 64px minmax(0, 1fr);
  gap: 8px;
  color: #173b2a;
  font-family: var(--fonte-corpo, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
}

/* ---------- TOP BAR ---------- */

.pdv-terminal-top {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr) 260px;
  gap: 8px;
}

.pdv-brand-terminal,
.pdv-terminal-status,
.pdv-terminal-actions {
  min-height: 64px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid rgba(18, 63, 43, .16);
  box-shadow: 0 8px 18px rgba(20, 45, 30, .08);
}

.pdv-brand-terminal {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #123f2b;
  color: #fff;
}

.pdv-terminal-logo {
  width: 42px;
  height: 42px;
  min-width: 42px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: rgba(255, 255, 255, .12);
  border: 1px solid rgba(255, 255, 255, .22);
  font-weight: 950;
}

.pdv-brand-terminal strong {
  display: block;
  color: #fff;
  font-size: 1rem;
  font-weight: 950;
  line-height: 1.1;
}

.pdv-brand-terminal span {
  display: block;
  margin-top: 2px;
  color: rgba(255,255,255,.72);
  font-size: .75rem;
  font-weight: 750;
}

.pdv-terminal-status {
  display: grid;
  place-items: center;
  background: #fffdf7;
  text-align: center;
}

.pdv-terminal-status small {
  display: block;
  color: #668071;
  font-size: .68rem;
  font-weight: 950;
  letter-spacing: .14em;
  text-transform: uppercase;
}

.pdv-terminal-status strong {
  display: block;
  color: #123f2b;
  font-size: clamp(1.65rem, 3vw, 2.55rem);
  font-weight: 950;
  letter-spacing: .06em;
  line-height: 1;
}

.pdv-terminal-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  padding: 8px;
  background: #fffdf7;
}

.pdv-terminal .btn {
  min-height: 38px;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: .78rem;
  font-weight: 900;
  box-shadow: none;
  white-space: nowrap;
}

.pdv-terminal .btn:hover {
  transform: none;
}

.pdv-terminal .btn-primary {
  background: #123f2b;
  border-color: #123f2b;
  color: #fff;
}

.pdv-terminal .btn-primary:hover {
  background: #0d3020;
}

.pdv-terminal .btn-soft,
.pdv-terminal .btn-outline {
  background: #fff;
  border: 1px solid rgba(18, 63, 43, .16);
  color: #123f2b;
}

.pdv-terminal .btn-soft:hover,
.pdv-terminal .btn-outline:hover {
  background: #e6efe6;
}

.pdv-terminal-actions .btn-primary {
  grid-column: 1 / -1;
}

/* ---------- GRID PRINCIPAL ---------- */

.pdv-terminal-grid {
  min-height: 0;
  display: grid;
  grid-template-columns: 310px minmax(0, 1fr) 330px;
  gap: 8px;
}

.pdv-terminal-panel {
  min-height: 0;
  overflow: hidden;
  border-radius: 8px;
  background: #fffdf7;
  border: 1px solid rgba(18, 63, 43, .16);
  box-shadow: 0 8px 18px rgba(20, 45, 30, .07);
}

.pdv-terminal-panel-head {
  min-height: 34px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 7px 10px;
  background: #123f2b;
  color: #fff;
}

.pdv-terminal-panel-head h2 {
  margin: 0;
  color: #fff;
  font-family: var(--fonte-corpo, system-ui);
  font-size: .84rem;
  font-weight: 950;
  letter-spacing: .02em;
  text-transform: uppercase;
}

.pdv-terminal-panel-head span,
.pdv-terminal-panel-head small {
  color: rgba(255,255,255,.72);
  font-size: .66rem;
  font-weight: 800;
}

.pdv-terminal-body {
  padding: 8px;
}

/* ---------- COLUNA ESQUERDA ---------- */

.pdv-left-terminal {
  display: grid;
  grid-template-rows: 34px minmax(0, 1fr);
}

.pdv-left-body {
  min-height: 0;
  display: grid;
  grid-template-rows: 250px auto auto 1fr;
  gap: 8px;
}

.pdv-product-view {
  min-height: 0;
  display: grid;
  grid-template-rows: 168px auto;
  overflow: hidden;
  border-radius: 6px;
  background: #fff;
  border: 1px solid rgba(18, 63, 43, .14);
}

.pdv-product-image {
  display: grid;
  place-items: center;
  overflow: hidden;
  background: linear-gradient(135deg, #dfeadd, #f6efe4);
  border-bottom: 1px solid rgba(18, 63, 43, .12);
}

.pdv-product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pdv-product-placeholder {
  display: grid;
  place-items: center;
  gap: 5px;
  color: #5d7165;
  text-align: center;
}

.pdv-product-placeholder strong {
  color: #123f2b;
  font-size: 2.25rem;
  line-height: 1;
}

.pdv-product-placeholder span {
  font-size: .78rem;
  font-weight: 850;
}

.pdv-product-info {
  display: grid;
  gap: 3px;
  padding: 9px;
}

.pdv-product-info small {
  color: #6c7b70;
  font-size: .64rem;
  font-weight: 950;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.pdv-product-info strong {
  color: #123f2b;
  font-size: .92rem;
  font-weight: 950;
  line-height: 1.15;
}

.pdv-product-info span {
  color: #81475a;
  font-size: .9rem;
  font-weight: 950;
}

.pdv-code-card,
.pdv-value-card {
  display: grid;
  gap: 5px;
  padding: 8px;
  border-radius: 6px;
  background: #f7f1e8;
  border: 1px solid rgba(18, 63, 43, .12);
}

.pdv-field {
  display: grid;
  gap: 4px;
}

.pdv-field span,
.pdv-value-card span {
  color: #123f2b;
  font-size: .66rem;
  font-weight: 950;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.pdv-field input,
.pdv-field select,
.pdv-money-input {
  width: 100%;
  min-height: 34px;
  padding: 0 9px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid rgba(18, 63, 43, .16);
  color: #123f2b;
  font-size: .86rem;
  font-weight: 850;
  outline: none;
}

.pdv-field input:focus,
.pdv-field select:focus,
.pdv-money-input:focus {
  border-color: #4f8062;
  box-shadow: 0 0 0 3px rgba(79, 128, 98, .14);
}

.pdv-barcode-input input {
  min-height: 48px;
  font-size: 1rem;
  font-weight: 950;
}

.pdv-qty-add-row {
  display: grid;
  grid-template-columns: 74px minmax(0, 1fr);
  gap: 7px;
  align-items: end;
}

.pdv-value-box {
  display: grid;
  grid-template-columns: 1fr;
  gap: 6px;
}

.pdv-terminal-amount {
  min-height: 42px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 0 10px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid rgba(18, 63, 43, .12);
  color: #123f2b;
  font-size: 1.15rem;
  font-weight: 950;
}

.pdv-terminal-amount.total-item {
  color: #81475a;
}

.pdv-payment-mini {
  align-self: end;
  display: grid;
  gap: 7px;
  padding: 8px;
  border-radius: 6px;
  background: #eef4ea;
  border: 1px solid rgba(18, 63, 43, .12);
}

.pdv-payment-mini-title {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.pdv-payment-mini-title strong {
  color: #123f2b;
  font-size: .78rem;
  font-weight: 950;
}

.pdv-payment-mini-title span {
  color: #6c7b70;
  font-size: .66rem;
  font-weight: 800;
}

.pdv-hidden {
  display: none !important;
}

/* ---------- CENTRO / LISTA DE PRODUTOS ---------- */

.pdv-center-terminal {
  display: grid;
  grid-template-rows: 34px minmax(0, 1fr) 238px;
}

.pdv-sale-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pdv-sale-badge {
  min-height: 21px;
  display: inline-flex;
  align-items: center;
  padding: 3px 7px;
  border-radius: 5px;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  color: #fff;
  font-size: .62rem;
  font-weight: 950;
  text-transform: uppercase;
  letter-spacing: .06em;
}

.pdv-products-table {
  min-height: 0;
  display: grid;
  grid-template-rows: 30px minmax(0, 1fr);
  background: #fff;
}

.pdv-table-head {
  display: grid;
  grid-template-columns: 54px 84px minmax(0, 1fr) 70px 82px 78px;
  gap: 7px;
  align-items: center;
  padding: 0 9px;
  background: #f1eadf;
  border-bottom: 1px solid rgba(18, 63, 43, .12);
  color: #123f2b;
  font-size: .64rem;
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
  gap: 5px;
  padding: 7px;
  background: #fff;
}

.pdv-current-items:empty::before {
  content: "Nenhum item lançado. Digite o código, busque pelo nome ou selecione nos produtos rápidos.";
  display: grid;
  place-items: center;
  min-height: 100%;
  padding: 22px;
  border-radius: 6px;
  background: #fbf8f2;
  border: 1px dashed rgba(18, 63, 43, .18);
  color: #5d7165;
  text-align: center;
  font-size: .9rem;
  font-weight: 850;
  line-height: 1.45;
}

/* Itens gerados pelo JS */
.pdv-sale-item {
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  padding: 6px;
  border-radius: 6px;
  background: #fffdf7;
  border: 1px solid rgba(18, 63, 43, .10);
}

.pdv-sale-item:nth-child(even) {
  background: #fbf8f2;
}

.pdv-sale-item img,
.pdv-sale-item > span {
  width: 54px;
  height: 48px;
  border-radius: 5px;
  object-fit: cover;
  background: #dfeadd;
}

.pdv-sale-item strong {
  color: #123f2b;
  font-size: .82rem;
  font-weight: 950;
  line-height: 1.15;
}

.pdv-sale-item small,
.pdv-sale-item span {
  color: #5d7165;
  font-size: .68rem;
  font-weight: 750;
}

.pdv-sale-item button {
  min-height: 28px;
  padding: 5px 8px;
  border-radius: 5px;
  background: #f5e8e8;
  border: 1px solid rgba(139, 63, 77, .14);
  color: #8b3f4d;
  font-size: .68rem;
  font-weight: 850;
}

/* ---------- RODAPÉ: SUBTOTAL / RECEBIDO / TROCO ---------- */

.pdv-close-terminal {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 180px 180px;
  grid-template-rows: 64px 52px 44px 38px;
  gap: 7px;
  padding: 8px;
  background: #dfeadd;
  border-top: 1px solid rgba(18, 63, 43, .14);
}

.pdv-total-display,
.pdv-input-display,
.pdv-change-display {
  display: grid;
  gap: 3px;
  align-content: center;
  padding: 8px 10px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid rgba(18, 63, 43, .12);
}

.pdv-total-display span,
.pdv-input-display span,
.pdv-change-display span {
  color: #123f2b;
  font-size: .68rem;
  font-weight: 950;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.pdv-total-display strong,
.pdv-change-display strong {
  color: #123f2b;
  font-size: 1.05rem;
  font-weight: 950;
}

.pdv-total-display.main {
  grid-column: 1 / 2;
  grid-row: 1 / 3;
  background: #fff;
}

.pdv-total-display.main strong {
  color: #123f2b;
  font-size: clamp(2.6rem, 5vw, 4rem);
  line-height: 1;
  text-align: right;
}

.pdv-input-display.received {
  grid-column: 2 / 3;
  grid-row: 1 / 3;
}

.pdv-change-display {
  grid-column: 3 / 4;
  grid-row: 1 / 3;
}

.pdv-change-display strong {
  color: #81475a;
  font-size: clamp(1.55rem, 3vw, 2.2rem);
  line-height: 1;
}

.pdv-change-display.is-missing strong {
  color: #8b3f4d;
  font-size: 1.35rem;
}

.pdv-money-input {
  min-height: 36px;
  text-align: right;
  font-weight: 950;
}

.pdv-small-total {
  grid-column: 1 / 2;
  grid-row: 3 / 4;
  display: grid;
  grid-template-columns: 1fr 180px;
  gap: 7px;
}

.pdv-small-total .pdv-input-display {
  min-height: 44px;
}

.pdv-quick-cash {
  grid-column: 2 / 4;
  grid-row: 3 / 4;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 6px;
}

.pdv-quick-cash button {
  min-height: 38px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid rgba(18, 63, 43, .14);
  color: #123f2b;
  font-size: .72rem;
  font-weight: 950;
  cursor: pointer;
}

.pdv-quick-cash button:hover {
  background: #eef4ea;
}

.pdv-final-actions {
  grid-column: 1 / -1;
  grid-row: 4 / 5;
  display: grid;
  grid-template-columns: 1fr 1fr 1.5fr;
  gap: 7px;
}

/* ---------- DIREITA: PRODUTOS RÁPIDOS ---------- */

.pdv-right-terminal {
  display: grid;
  grid-template-rows: 34px auto minmax(0, 1fr);
  background: #123f2b;
}

.pdv-right-terminal .pdv-terminal-panel-head {
  background: #123f2b;
  border-bottom-color: rgba(255,255,255,.12);
}

.pdv-right-terminal .pdv-terminal-panel-head h2 {
  color: #fff;
}

.pdv-right-terminal .pdv-terminal-panel-head span {
  color: rgba(255,255,255,.72);
}

.pdv-category-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  padding: 8px;
  background: rgba(255,255,255,.08);
  border-bottom: 1px solid rgba(255,255,255,.12);
}

.filter-pill {
  min-height: 28px;
  padding: 5px 7px;
  border-radius: 5px;
  background: rgba(255,255,255,.92);
  border: 1px solid rgba(255,255,255,.16);
  color: #123f2b;
  font-size: .66rem;
  font-weight: 900;
  box-shadow: none;
}

.filter-pill:hover {
  transform: none;
  background: #eef4ea;
}

.filter-pill.active {
  background: #dfeadd;
  border-color: #dfeadd;
  color: #123f2b;
}

.pdv-product-grid {
  min-height: 0;
  overflow-y: auto;
  display: grid;
  align-content: start;
  gap: 6px;
  padding: 8px;
  background: #123f2b;
}

/* Cards de produto gerados pelo JS */
.pdv-product-card {
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr);
  gap: 7px;
  align-items: center;
  padding: 6px;
  border-radius: 6px;
  background: #fffdf7;
  border: 1px solid rgba(255,255,255,.16);
  text-align: left;
  cursor: pointer;
}

.pdv-product-card:hover {
  transform: none;
  background: #f6efe4;
}

.pdv-product-card img {
  width: 52px;
  height: 52px;
  object-fit: cover;
  border-radius: 5px;
  background: #dfeadd;
}

.pdv-product-card strong {
  color: #123f2b;
  font-size: .76rem;
  font-weight: 950;
  line-height: 1.15;
}

.pdv-product-card span,
.pdv-product-card small {
  color: #5d7165;
  font-size: .64rem;
  font-weight: 750;
}

.pdv-product-card .price,
.pdv-product-card [class*="price"] {
  color: #81475a;
  font-size: .78rem;
  font-weight: 950;
}

.pdv-hidden-history {
  display: none !important;
}

/* ---------- SCROLL ---------- */

.pdv-current-items::-webkit-scrollbar,
.pdv-product-grid::-webkit-scrollbar {
  width: 7px;
}

.pdv-current-items::-webkit-scrollbar-thumb {
  background: rgba(18, 63, 43, .20);
  border-radius: 10px;
}

.pdv-product-grid::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,.24);
  border-radius: 10px;
}

/* ---------- NOTEBOOK ---------- */

@media (max-width: 1366px) {
  .pdv-terminal-grid {
    grid-template-columns: 285px minmax(0, 1fr) 305px;
  }

  .pdv-terminal-top {
    grid-template-columns: 250px minmax(0, 1fr) 240px;
  }

  .pdv-product-view {
    grid-template-rows: 145px auto;
  }

  .pdv-left-body {
    grid-template-rows: 225px auto auto 1fr;
  }

  .pdv-center-terminal {
    grid-template-rows: 34px minmax(0, 1fr) 228px;
  }

  .pdv-total-display.main strong {
    font-size: 3.1rem;
  }

  .pdv-change-display strong {
    font-size: 1.8rem;
  }
}

@media (max-width: 1180px) {
  body.pdv-market-reference-mode .admin-main {
    overflow-y: auto;
  }

  .pdv-terminal {
    height: auto;
    min-height: calc(100vh - 16px);
    grid-template-rows: auto auto;
  }

  .pdv-terminal-top,
  .pdv-terminal-grid {
    grid-template-columns: 1fr;
  }

  .pdv-left-terminal,
  .pdv-center-terminal,
  .pdv-right-terminal {
    grid-template-rows: auto;
  }

  .pdv-current-items {
    min-height: 300px;
    max-height: 420px;
  }

  .pdv-close-terminal,
  .pdv-small-total,
  .pdv-quick-cash,
  .pdv-final-actions {
    grid-template-columns: 1fr;
    grid-template-rows: auto;
  }

  .pdv-total-display.main,
  .pdv-input-display.received,
  .pdv-change-display,
  .pdv-small-total,
  .pdv-quick-cash,
  .pdv-final-actions {
    grid-column: auto;
    grid-row: auto;
  }
}
</style>

<div class="pdv-terminal">
  <section class="pdv-terminal-top">
    <div class="pdv-brand-terminal">
      <span class="pdv-terminal-logo">A&F</span>
      <div>
        <strong>Arte&Flor - PDV</strong>
        <span>Venda presencial</span>
      </div>
    </div>

    <div class="pdv-terminal-status">
      <div>
        <small>Status operacional</small>
        <strong>CAIXA ABERTO</strong>
      </div>
    </div>

    <div class="pdv-terminal-actions">
      <a class="btn btn-soft" href="<?= site_url('admin/dashboard.php') ?>">Dashboard</a>
      <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar</button>
      <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
    </div>
  </section>

  <script type="application/json" id="pdvProducts"><?= json_encode($pdvProdutos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <section class="pdv-terminal-grid">
    <aside class="pdv-terminal-panel pdv-left-terminal">
      <div class="pdv-terminal-panel-head">
        <div>
          <h2>Produto</h2>
          <span>Selecionado</span>
        </div>
      </div>

      <div class="pdv-terminal-body pdv-left-body">
        <div class="pdv-product-view">
          <div class="pdv-product-image" data-pdv-selected-image>
            <div class="pdv-product-placeholder">
              <strong>🛒</strong>
              <span>Aguardando produto</span>
            </div>
          </div>

          <div class="pdv-product-info">
            <small>Último item lançado</small>
            <strong data-pdv-selected-name>Nenhum produto selecionado</strong>
            <span data-pdv-selected-price>R$ 0,00</span>
          </div>
        </div>

        <div class="pdv-code-card">
          <label class="pdv-field pdv-barcode-input">
            <span>Código / SKU / Nome</span>
            <input type="search" data-pdv-search placeholder="Leia ou digite o produto" autofocus>
          </label>

          <div class="pdv-qty-add-row">
            <label class="pdv-field">
              <span>Qtd.</span>
              <input type="number" min="1" value="1" data-pdv-qty>
            </label>

            <button class="btn btn-primary" type="button" data-pdv-add-search>Inserir item</button>
          </div>
        </div>

        <div class="pdv-value-box">
          <div class="pdv-value-card">
            <span>Valor unitário</span>
            <strong class="pdv-terminal-amount" data-pdv-selected-unit>R$ 0,00</strong>
          </div>

          <div class="pdv-value-card">
            <span>Total do item</span>
            <strong class="pdv-terminal-amount total-item" data-pdv-selected-item-total>R$ 0,00</strong>
          </div>
        </div>

        <div class="pdv-payment-mini">
          <div class="pdv-payment-mini-title">
            <strong>Pagamento</strong>
            <span>Dados mínimos</span>
          </div>

          <input class="pdv-hidden" data-pdv-client value="Cliente balcão">
          <input class="pdv-hidden" data-pdv-contact value="Balcão">

          <label class="pdv-field">
            <span>Forma</span>
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

    <main class="pdv-terminal-panel pdv-center-terminal">
      <div class="pdv-terminal-panel-head">
        <div class="pdv-sale-title">
          <span class="pdv-sale-badge">Venda atual</span>
          <div>
            <h2>Lista de produtos</h2>
            <small>Itens registrados no caixa</small>
          </div>
        </div>
        <small>Operador: Admin</small>
      </div>

      <div class="pdv-products-table">
        <div class="pdv-table-head">
          <span>Img</span>
          <span>Cód.</span>
          <span>Descrição</span>
          <span>Qtd</span>
          <span>Unit.</span>
          <span>Ação</span>
        </div>

        <div class="pdv-current-items" data-pdv-current></div>
      </div>

      <div class="pdv-close-terminal">
        <div class="pdv-total-display main">
          <span>Subtotal</span>
          <strong data-pdv-total>R$ 0,00</strong>
        </div>

        <div class="pdv-input-display received">
          <span>Total recebido</span>
          <input class="pdv-money-input" type="number" min="0" step="0.01" value="0" data-pdv-received>
        </div>

        <div class="pdv-change-display" data-pdv-change-box>
          <span>Troco</span>
          <strong data-pdv-change>R$ 0,00</strong>
        </div>

        <div class="pdv-small-total">
          <div class="pdv-input-display">
            <span>Valor bruto</span>
            <strong data-pdv-subtotal>R$ 0,00</strong>
          </div>

          <div class="pdv-input-display">
            <span>Desconto</span>
            <input class="pdv-money-input" type="number" min="0" step="0.01" value="0" data-pdv-discount>
          </div>
        </div>

        <div class="pdv-quick-cash">
          <button type="button" data-pdv-cash-exact>Exato</button>
          <button type="button" data-pdv-cash-add="10">+10</button>
          <button type="button" data-pdv-cash-add="20">+20</button>
          <button type="button" data-pdv-cash-add="50">+50</button>
          <button type="button" data-pdv-cash-clear>Limpar</button>
        </div>

        <div class="pdv-final-actions">
          <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender</button>
          <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar</button>
          <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
        </div>
      </div>
    </main>

    <aside class="pdv-terminal-panel pdv-right-terminal">
      <div class="pdv-terminal-panel-head">
        <div>
          <h2>Produtos rápidos</h2>
          <span>Selecione para lançar</span>
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
  const selectedUnit = document.querySelector('[data-pdv-selected-unit]');
  const selectedItemTotal = document.querySelector('[data-pdv-selected-item-total]');
  const totalEl = document.querySelector('[data-pdv-total]');
  const receivedInput = document.querySelector('[data-pdv-received]');
  const changeEl = document.querySelector('[data-pdv-change]');
  const changeBox = document.querySelector('[data-pdv-change-box]');
  const exactBtn = document.querySelector('[data-pdv-cash-exact]');
  const clearBtn = document.querySelector('[data-pdv-cash-clear]');
  const addButtons = document.querySelectorAll('[data-pdv-cash-add]');
  const searchInput = document.querySelector('[data-pdv-search]');
  const qtyInput = document.querySelector('[data-pdv-qty]');
  const addButton = document.querySelector('[data-pdv-add-search]');
  const productGrid = document.querySelector('[data-pdv-product-grid]');

  const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });

  function parseMoney(value) {
    if (!value) return 0;

    const normalized = String(value)
      .replace(/[^\d,.-]/g, '')
      .replace(/\./g, '')
      .replace(',', '.');

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function getTotal() {
    return parseMoney(totalEl?.textContent || '0');
  }

  function getQty() {
    return Math.max(1, Number(qtyInput?.value || 1));
  }

  function updateChange() {
    if (!receivedInput || !changeEl) return;

    const total = getTotal();
    const received = Number(receivedInput.value || 0);
    const difference = received - total;

    changeBox?.classList.remove('is-missing');

    if (received > 0 && difference < 0) {
      changeEl.textContent = `Falta ${money.format(Math.abs(difference))}`;
      changeBox?.classList.add('is-missing');
      return;
    }

    changeEl.textContent = money.format(Math.max(0, difference));
  }

  function updateSelectedFromCard(card) {
    if (!card || !selectedImage || !selectedName || !selectedPrice) return;

    const image = card.querySelector('img');
    const name = card.querySelector('strong')?.textContent?.trim() || 'Produto selecionado';

    const priceText = [...card.querySelectorAll('span, small, strong')]
      .map((el) => el.textContent.trim())
      .find((text) => text.includes('R$')) || '';

    const unitValue = parseMoney(priceText);
    const itemTotal = unitValue * getQty();

    if (image?.src) {
      selectedImage.innerHTML = `<img src="${image.src}" alt="${name}">`;
    } else {
      selectedImage.innerHTML = `
        <div class="pdv-product-placeholder">
          <strong>A&F</strong>
          <span>Produto sem imagem</span>
        </div>
      `;
    }

    selectedName.textContent = name;
    selectedPrice.textContent = priceText || 'Produto selecionado';

    if (selectedUnit) {
      selectedUnit.textContent = unitValue > 0 ? money.format(unitValue) : (priceText || 'R$ 0,00');
    }

    if (selectedItemTotal) {
      selectedItemTotal.textContent = unitValue > 0 ? money.format(itemTotal) : 'R$ 0,00';
    }
  }

  function updateSelectedFromSale() {
    if (!currentList) return;

    const items = [...currentList.querySelectorAll('.pdv-sale-item')];
    const lastItem = items[items.length - 1];

    if (!lastItem) {
      selectedImage.innerHTML = `
        <div class="pdv-product-placeholder">
          <strong>🛒</strong>
          <span>Aguardando produto</span>
        </div>
      `;
      selectedName.textContent = 'Nenhum produto selecionado';
      selectedPrice.textContent = 'R$ 0,00';

      if (selectedUnit) selectedUnit.textContent = 'R$ 0,00';
      if (selectedItemTotal) selectedItemTotal.textContent = 'R$ 0,00';

      updateChange();
      return;
    }

    updateSelectedFromCard(lastItem);
    updateChange();
  }

  receivedInput?.addEventListener('input', updateChange);

  qtyInput?.addEventListener('input', () => {
    const productCards = [...document.querySelectorAll('.pdv-product-card')];
    const selectedCard = productCards.find((card) => {
      const name = card.querySelector('strong')?.textContent?.trim();
      return name && selectedName?.textContent?.trim() === name;
    });

    if (selectedCard) updateSelectedFromCard(selectedCard);
  });

  exactBtn?.addEventListener('click', () => {
    if (!receivedInput) return;
    receivedInput.value = getTotal().toFixed(2);
    updateChange();
  });

  clearBtn?.addEventListener('click', () => {
    if (!receivedInput) return;
    receivedInput.value = '0';
    updateChange();
  });

  addButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (!receivedInput) return;

      const current = Number(receivedInput.value || 0);
      const add = Number(button.dataset.pdvCashAdd || 0);

      receivedInput.value = (current + add).toFixed(2);
      updateChange();
    });
  });

  searchInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      addButton?.click();
    }
  });

  productGrid?.addEventListener('click', (event) => {
    const card = event.target.closest('.pdv-product-card');
    if (card) updateSelectedFromCard(card);
  });

  const observer = new MutationObserver(() => {
    updateSelectedFromSale();
    updateChange();
  });

  if (currentList) {
    observer.observe(currentList, {
      childList: true,
      subtree: true
    });
  }

  if (totalEl) {
    observer.observe(totalEl, {
      childList: true,
      characterData: true,
      subtree: true
    });
  }

  setTimeout(() => searchInput?.focus(), 150);
  updateSelectedFromSale();
  updateChange();
})();
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>