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
   ARTE&FLOR ADMIN — PDV FULLSCREEN NOTEBOOK
   Tela de caixa em modo balcão/supermercado
========================================================= */

/* Remove cara de página admin e transforma em tela cheia */
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
  grid-template-rows: 64px minmax(0, 1fr);
  gap: 10px;
  color: #203f30;
}

/* ---------- Topo fullscreen ---------- */

.pdv-topbar {
  display: grid;
  grid-template-columns: 260px minmax(280px, 1fr) auto;
  gap: 10px;
  min-height: 64px;
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
  width: 42px;
  height: 42px;
  min-width: 42px;
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
  font-size: 1rem;
  font-weight: 950;
  line-height: 1.1;
}

.pdv-brand-box span {
  display: block;
  margin-top: 2px;
  color: #647067;
  font-size: .78rem;
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
  font-size: .68rem;
  font-weight: 900;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.pdv-status-box strong {
  display: block;
  color: #fff;
  font-size: clamp(1.45rem, 3vw, 2.25rem);
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
  font-size: .84rem;
  font-weight: 850;
  box-shadow: none;
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

/* ---------- Tela principal ---------- */

.pdv-workspace {
  min-height: 0;
  display: grid;
  grid-template-columns: 290px minmax(420px, 1fr) 320px;
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
  min-height: 44px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #dfeadd;
  border-bottom: 1px solid rgba(32, 63, 48, .10);
}

.pdv-box-header h2 {
  margin: 0;
  color: #203f30;
  font-family: var(--fonte-corpo, system-ui);
  font-size: .96rem;
  font-weight: 950;
  letter-spacing: -.01em;
}

.pdv-box-header span,
.pdv-box-header small {
  color: #647067;
  font-size: .74rem;
  font-weight: 800;
}

.pdv-box-body {
  padding: 10px;
}

/* ---------- Coluna esquerda: entrada ---------- */

.pdv-entry-column {
  display: grid;
  grid-template-rows: auto auto 1fr;
}

.pdv-entry-body {
  display: grid;
  gap: 10px;
  height: 100%;
}

.pdv-product-preview {
  min-height: 118px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: linear-gradient(135deg, #edf3e9, #f8f1e8);
  border: 1px solid rgba(32, 63, 48, .10);
  text-align: center;
}

.pdv-product-preview strong {
  display: block;
  font-size: 2.4rem;
  line-height: 1;
}

.pdv-product-preview span {
  display: block;
  margin-top: 4px;
  color: #647067;
  font-size: .78rem;
  font-weight: 800;
}

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

.pdv-help {
  align-self: end;
  display: grid;
  gap: 7px;
  padding: 10px;
  border-radius: 10px;
  background: #f8f1e8;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-help strong {
  color: #203f30;
  font-size: .78rem;
  font-weight: 950;
}

.pdv-shortcuts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5px;
}

.pdv-shortcuts span {
  display: flex;
  justify-content: space-between;
  gap: 6px;
  padding: 6px;
  border-radius: 7px;
  background: #fff;
  color: #647067;
  font-size: .68rem;
  font-weight: 750;
}

.pdv-shortcuts b {
  color: #203f30;
}

/* ---------- Centro: lista e total ---------- */

.pdv-sale-column {
  display: grid;
  grid-template-rows: 44px minmax(0, 1fr) 190px;
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

/* Itens inseridos pelo JS */
.pdv-sale-item {
  display: grid;
  grid-template-columns: 50px minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
  padding: 8px;
  border-radius: 9px;
  background: #fffdf8;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-sale-item img,
.pdv-sale-item > span {
  width: 50px;
  height: 50px;
  border-radius: 7px;
  object-fit: cover;
  background: #edf3e9;
}

.pdv-sale-item strong {
  color: #203f30;
  font-size: .86rem;
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

/* Total compacto para caber no notebook */
.pdv-total-board {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  padding: 10px;
  background: #f8f1e8;
  border-top: 1px solid rgba(32, 63, 48, .10);
}

.pdv-total-line {
  min-height: 48px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 9px 11px;
  border-radius: 9px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-total-line span {
  color: #647067;
  font-size: .76rem;
  font-weight: 900;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.pdv-total-line strong {
  color: #203f30;
  font-size: 1.08rem;
  font-weight: 950;
  white-space: nowrap;
}

.pdv-total-line.main {
  grid-column: 1 / -1;
  min-height: 66px;
  background: #203f30;
  border-color: #203f30;
}

.pdv-total-line.main span {
  color: rgba(255,255,255,.72);
}

.pdv-total-line.main strong {
  color: #fff;
  font-size: clamp(2rem, 4vw, 3rem);
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

/* ---------- Direita: pagamento, rápidos e histórico ---------- */

.pdv-side-column {
  min-height: 0;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) 150px;
  gap: 10px;
}

.pdv-side-section {
  min-height: 0;
  overflow: hidden;
  border-radius: 12px;
  background: #fffdf8;
  border: 1px solid rgba(32, 63, 48, .12);
}

.pdv-side-title {
  min-height: 38px;
  display: flex;
  align-items: center;
  padding: 9px 11px;
  background: #dfeadd;
  border-bottom: 1px solid rgba(32, 63, 48, .10);
}

.pdv-side-title h2 {
  margin: 0;
  color: #203f30;
  font-size: .92rem;
  font-weight: 950;
}

.pdv-side-content {
  padding: 10px;
}

.pdv-payment-content {
  display: grid;
  gap: 8px;
}

.pdv-payment-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.pdv-payment-grid .pdv-field:last-child {
  grid-column: 1 / -1;
}

.pdv-pix {
  display: grid;
  grid-template-columns: 80px minmax(0, 1fr);
  gap: 9px;
  align-items: center;
  padding: 9px;
  border-radius: 10px;
  background: #f8f1e8;
  border: 1px solid rgba(32, 63, 48, .10);
}

.qr-placeholder {
  width: 80px;
  height: 80px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  background:
    linear-gradient(90deg, rgba(32, 63, 48, .10) 50%, transparent 50%),
    linear-gradient(rgba(32, 63, 48, .10) 50%, transparent 50%),
    #fff;
  background-size: 16px 16px;
  border: 1px solid rgba(32, 63, 48, .14);
  color: #203f30;
  font-size: 1rem;
  font-weight: 950;
  letter-spacing: .08em;
  box-shadow: inset 0 0 0 7px #edf3e9;
}

.pix-key-box {
  min-width: 0;
  display: grid;
  gap: 4px;
}

.pix-key-box small {
  color: #647067;
  font-size: .66rem;
  font-weight: 900;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.pix-key-box strong {
  color: #203f30;
  font-size: .78rem;
  font-weight: 950;
  overflow-wrap: anywhere;
}

.pdv-pix .btn {
  grid-column: 1 / -1;
  min-height: 34px;
}

/* Produtos rápidos */
.pdv-products-content {
  min-height: 0;
  height: 100%;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 8px;
}

.pdv-category-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
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
  padding-right: 3px;
}

/* Cards de produto gerados pelo JS */
.pdv-product-card {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr);
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
  width: 44px;
  height: 44px;
  object-fit: cover;
  border-radius: 7px;
  background: #edf3e9;
}

.pdv-product-card strong {
  color: #203f30;
  font-size: .78rem;
  font-weight: 950;
  line-height: 1.15;
}

.pdv-product-card span,
.pdv-product-card small {
  color: #647067;
  font-size: .66rem;
  font-weight: 750;
}

.pdv-product-card .price,
.pdv-product-card [class*="price"] {
  color: #82495c;
  font-size: .8rem;
  font-weight: 950;
}

/* Histórico */
[data-pdv-history] {
  height: 100%;
  overflow-y: auto;
  display: grid;
  align-content: start;
  gap: 7px;
}

[data-pdv-history]:empty::before {
  content: "Nenhuma venda registrada.";
  display: block;
  padding: 10px;
  border-radius: 9px;
  background: #fff;
  border: 1px dashed rgba(32, 63, 48, .16);
  color: #647067;
  font-size: .78rem;
  font-weight: 750;
}

.pdv-history-row {
  display: grid;
  gap: 3px;
  padding: 8px;
  border-radius: 9px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .11);
}

.pdv-history-row strong {
  color: #203f30;
  font-size: .78rem;
  font-weight: 950;
}

.pdv-history-row span,
.pdv-history-row small {
  color: #647067;
  font-size: .68rem;
  font-weight: 750;
}

/* Scroll fino */
.pdv-current-items::-webkit-scrollbar,
.pdv-product-grid::-webkit-scrollbar,
[data-pdv-history]::-webkit-scrollbar {
  width: 7px;
}

.pdv-current-items::-webkit-scrollbar-thumb,
.pdv-product-grid::-webkit-scrollbar-thumb,
[data-pdv-history]::-webkit-scrollbar-thumb {
  background: rgba(32, 63, 48, .18);
  border-radius: 10px;
}

/* ---------- Notebook menor ---------- */

@media (max-width: 1366px) {
  .pdv-workspace {
    grid-template-columns: 270px minmax(390px, 1fr) 300px;
  }

  .pdv-topbar {
    grid-template-columns: 240px minmax(260px, 1fr) auto;
  }

  .pdv-top-actions .btn {
    padding-inline: 10px;
  }

  .pdv-status-box strong {
    font-size: 1.85rem;
  }

  .pdv-total-line.main strong {
    font-size: 2.45rem;
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

  .pdv-sale-column,
  .pdv-side-column {
    grid-template-rows: auto;
  }

  .pdv-current-items {
    min-height: 300px;
    max-height: 420px;
  }
}
</style>

<div class="pdv-fullscreen-page">
  <section class="pdv-topbar">
    <div class="pdv-brand-box">
      <span class="pdv-brand-mark">A&F</span>
      <div>
        <strong>Arte&Flor PDV</strong>
        <span>Venda presencial e balcão</span>
      </div>
    </div>

    <div class="pdv-status-box">
      <div>
        <small>Status operacional</small>
        <strong>CAIXA ABERTO</strong>
      </div>
    </div>

    <div class="pdv-top-actions">
      <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender</button>
      <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar</button>
    </div>
  </section>

  <script type="application/json" id="pdvProducts"><?= json_encode($pdvProdutos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <section class="pdv-workspace">
    <aside class="pdv-box pdv-entry-column">
      <div class="pdv-box-header">
        <div>
          <h2>Entrada rápida</h2>
          <span>Código, SKU ou produto</span>
        </div>
      </div>

      <div class="pdv-box-body pdv-entry-body">
        <div class="pdv-product-preview">
          <div>
            <strong>🛒</strong>
            <span>Pronto para vender</span>
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

        <div class="pdv-help">
          <strong>Atalhos do caixa</strong>
          <div class="pdv-shortcuts">
            <span><b>F2</b> Buscar</span>
            <span><b>F5</b> Nova</span>
            <span><b>F7</b> Finalizar</span>
            <span><b>ESC</b> Cancelar</span>
          </div>
        </div>
      </div>
    </aside>

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

    <aside class="pdv-side-column">
      <section class="pdv-side-section">
        <div class="pdv-side-title">
          <h2>Pagamento</h2>
        </div>

        <div class="pdv-side-content pdv-payment-content">
          <div class="pdv-payment-grid">
            <label class="pdv-field">
              <span>Cliente</span>
              <input data-pdv-client placeholder="Cliente balcão">
            </label>

            <label class="pdv-field">
              <span>Contato</span>
              <input data-pdv-contact placeholder="(97) 90000-0000">
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

          <div class="pix-box pdv-pix">
            <div class="qr-placeholder">PIX</div>

            <div class="pix-key-box">
              <small>Chave Pix</small>
              <strong>arteflor@pix.demo</strong>
            </div>

            <button class="btn btn-soft" type="button" data-copy-value="arteflor@pix.demo">Copiar Pix</button>
          </div>
        </div>
      </section>

      <section class="pdv-side-section">
        <div class="pdv-side-title">
          <h2>Produtos rápidos</h2>
        </div>

        <div class="pdv-side-content pdv-products-content">
          <div class="pdv-category-pills">
            <button class="filter-pill active" type="button" data-pdv-category="todos">Todos</button>
            <?php foreach ($categorias as $categoria): ?>
              <button class="filter-pill" type="button" data-pdv-category="<?= e($categoria) ?>"><?= e($categoria) ?></button>
            <?php endforeach; ?>
          </div>

          <div class="pdv-product-grid" data-pdv-product-grid></div>
        </div>
      </section>

      <section class="pdv-side-section">
        <div class="pdv-side-title">
          <h2>Últimas vendas</h2>
        </div>

        <div class="pdv-side-content">
          <div data-pdv-history></div>
        </div>
      </section>
    </aside>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>