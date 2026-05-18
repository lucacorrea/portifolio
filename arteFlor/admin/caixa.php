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
   ARTE&FLOR ADMIN — PDV ESTILO CAIXA / SUPERMERCADO
   CSS interno somente para admin/caixa.php
========================================================= */

.pdv-supermarket-page {
  display: grid;
  gap: 18px;
  color: #213b2d;
}

/* ---------- Topo caixa aberto ---------- */

.pdv-cashier-topbar {
  display: grid;
  grid-template-columns: minmax(260px, .8fr) minmax(280px, 1.2fr) auto;
  gap: 14px;
  align-items: stretch;
}

.pdv-top-brand,
.pdv-open-status,
.pdv-top-actions {
  min-height: 78px;
  border-radius: 14px;
  background: #fffdf8;
  border: 1px solid rgba(38, 67, 50, .12);
  box-shadow: 0 10px 28px rgba(45, 55, 48, .06);
}

.pdv-top-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
}

.pdv-top-brand .brand-icon {
  width: 46px;
  height: 46px;
  min-width: 46px;
  border-radius: 12px;
  background: #dfeadd;
  color: #203f30;
  border: 1px solid rgba(38, 67, 50, .12);
  display: grid;
  place-items: center;
  font-weight: 950;
}

.pdv-top-brand strong {
  display: block;
  color: #203f30;
  font-size: 1.05rem;
  font-weight: 950;
}

.pdv-top-brand span {
  color: #647067;
  font-size: .84rem;
  font-weight: 750;
}

.pdv-open-status {
  display: grid;
  place-items: center;
  padding: 12px 18px;
  text-align: center;
  background: #203f30;
  color: #fff;
}

.pdv-open-status small {
  display: block;
  color: rgba(255,255,255,.72);
  font-size: .72rem;
  font-weight: 900;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.pdv-open-status strong {
  display: block;
  margin-top: 3px;
  color: #fff;
  font-size: clamp(1.65rem, 4vw, 2.65rem);
  font-weight: 950;
  letter-spacing: .03em;
  line-height: 1;
}

.pdv-top-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px;
}

.pdv-supermarket-page .btn {
  min-height: 42px;
  padding: 10px 15px;
  border-radius: 10px;
  font-weight: 850;
  box-shadow: none;
}

.pdv-supermarket-page .btn:hover {
  transform: none;
}

.pdv-supermarket-page .btn-primary {
  background: #203f30;
  border-color: #203f30;
  color: #fff;
}

.pdv-supermarket-page .btn-primary:hover {
  background: #173327;
}

.pdv-supermarket-page .btn-soft,
.pdv-supermarket-page .btn-outline {
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .14);
  color: #203f30;
}

.pdv-supermarket-page .btn-soft:hover,
.pdv-supermarket-page .btn-outline:hover {
  background: #edf3e9;
}

/* ---------- Layout principal ---------- */

.pdv-screen {
  display: grid;
  grid-template-columns: 360px minmax(0, 1fr) 340px;
  gap: 16px;
  align-items: start;
}

.pdv-panel {
  min-width: 0;
  border-radius: 14px;
  background: #fffdf8;
  border: 1px solid rgba(38, 67, 50, .12);
  box-shadow: 0 10px 28px rgba(45, 55, 48, .06);
  overflow: hidden;
}

.pdv-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 14px 16px;
  background: #edf3e9;
  border-bottom: 1px solid rgba(38, 67, 50, .10);
}

.pdv-panel-header h2 {
  color: #203f30;
  font-family: var(--fonte-corpo, system-ui);
  font-size: 1rem;
  font-weight: 950;
  letter-spacing: -.01em;
}

.pdv-panel-header span,
.pdv-panel-header small {
  color: #647067;
  font-size: .78rem;
  font-weight: 800;
}

.pdv-panel-body {
  padding: 14px;
}

/* ---------- Painel de entrada ---------- */

.pdv-entry-card {
  display: grid;
  gap: 12px;
}

.pdv-product-visual {
  display: grid;
  place-items: center;
  min-height: 160px;
  border-radius: 12px;
  background:
    linear-gradient(135deg, #edf3e9, #f8f1e8);
  border: 1px solid rgba(38, 67, 50, .10);
}

.pdv-product-visual-inner {
  display: grid;
  place-items: center;
  gap: 8px;
  text-align: center;
  color: #203f30;
}

.pdv-product-visual-inner strong {
  font-size: 3.2rem;
  line-height: 1;
}

.pdv-product-visual-inner span {
  color: #647067;
  font-size: .86rem;
  font-weight: 800;
}

.pdv-fast-form {
  display: grid;
  gap: 12px;
}

.pdv-field {
  display: grid;
  gap: 7px;
}

.pdv-field span {
  color: #203f30;
  font-size: .78rem;
  font-weight: 950;
  letter-spacing: .07em;
  text-transform: uppercase;
}

.pdv-field input,
.pdv-field select {
  width: 100%;
  min-height: 46px;
  padding: 0 12px;
  border-radius: 10px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .14);
  color: #203f30;
  font-size: .98rem;
  font-weight: 800;
  outline: none;
  box-shadow: none;
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

.pdv-barcode-field input {
  min-height: 56px;
  font-size: 1.12rem;
  font-weight: 900;
  letter-spacing: .02em;
}

.pdv-qty-row {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  gap: 10px;
  align-items: end;
}

.pdv-help-box {
  display: grid;
  gap: 8px;
  padding: 12px;
  border-radius: 12px;
  background: #f8f1e8;
  border: 1px solid rgba(38, 67, 50, .10);
}

.pdv-help-box strong {
  color: #203f30;
  font-size: .86rem;
  font-weight: 950;
}

.pdv-shortcuts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px;
}

.pdv-shortcuts span {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 7px 8px;
  border-radius: 8px;
  background: #fff;
  color: #647067;
  font-size: .74rem;
  font-weight: 750;
}

.pdv-shortcuts b {
  color: #203f30;
}

/* ---------- Lista da venda ---------- */

.pdv-sale-panel {
  display: grid;
}

.pdv-sale-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pdv-sale-title .badge {
  min-height: 26px;
  padding: 5px 8px;
  border-radius: 8px;
  background: #fff;
  color: #203f30;
  border: 1px solid rgba(38, 67, 50, .12);
  font-size: .68rem;
  font-weight: 950;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.pdv-current-items {
  display: grid;
  gap: 8px;
  min-height: 430px;
  max-height: 520px;
  overflow-y: auto;
  padding: 12px;
  background: #fff;
}

.pdv-current-items::-webkit-scrollbar,
.pdv-product-grid::-webkit-scrollbar,
[data-pdv-history]::-webkit-scrollbar {
  width: 8px;
}

.pdv-current-items::-webkit-scrollbar-thumb,
.pdv-product-grid::-webkit-scrollbar-thumb,
[data-pdv-history]::-webkit-scrollbar-thumb {
  background: rgba(38, 67, 50, .18);
  border-radius: 10px;
}

.pdv-current-items:empty::before {
  content: "Passe o código, busque pelo nome ou selecione um produto para iniciar a venda.";
  display: grid;
  place-items: center;
  min-height: 360px;
  padding: 24px;
  border-radius: 12px;
  background: #fbf8f2;
  border: 1px dashed rgba(38, 67, 50, .18);
  color: #647067;
  text-align: center;
  font-size: .96rem;
  font-weight: 800;
  line-height: 1.45;
}

/* Itens inseridos pelo JS */
.pdv-sale-item {
  display: grid;
  grid-template-columns: 62px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 10px;
  border-radius: 10px;
  background: #fffdf8;
  border: 1px solid rgba(38, 67, 50, .11);
}

.pdv-sale-item img,
.pdv-sale-item > span {
  width: 62px;
  height: 62px;
  border-radius: 8px;
  object-fit: cover;
  background: #edf3e9;
}

.pdv-sale-item strong {
  color: #203f30;
  font-size: .92rem;
  font-weight: 950;
}

.pdv-sale-item small,
.pdv-sale-item span {
  color: #647067;
  font-size: .78rem;
  font-weight: 750;
}

.pdv-sale-item button {
  min-height: 32px;
  padding: 7px 10px;
  border-radius: 8px;
  background: #f5e8e8;
  border: 1px solid rgba(139, 63, 77, .14);
  color: #8b3f4d;
  font-size: .78rem;
  font-weight: 850;
}

/* ---------- Total estilo PDV ---------- */

.pdv-total-board {
  display: grid;
  gap: 10px;
  padding: 14px;
  background: #f8f1e8;
  border-top: 1px solid rgba(38, 67, 50, .10);
}

.pdv-total-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  min-height: 52px;
  padding: 10px 14px;
  border-radius: 10px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .10);
}

.pdv-total-line span {
  color: #647067;
  font-size: .88rem;
  font-weight: 900;
  letter-spacing: .05em;
  text-transform: uppercase;
}

.pdv-total-line strong {
  color: #203f30;
  font-size: 1.25rem;
  font-weight: 950;
}

.pdv-total-line.main {
  min-height: 76px;
  background: #203f30;
  border-color: #203f30;
}

.pdv-total-line.main span {
  color: rgba(255,255,255,.75);
}

.pdv-total-line.main strong {
  color: #fff;
  font-size: clamp(2rem, 4vw, 3.15rem);
  line-height: 1;
}

.pdv-discount-input {
  width: 128px;
  min-height: 38px;
  border-radius: 8px;
  border: 1px solid rgba(38, 67, 50, .14);
  background: #fff;
  color: #203f30;
  text-align: right;
  padding: 0 10px;
  font-weight: 900;
  outline: none;
}

.pdv-discount-input:focus {
  border-color: #4f8062;
  box-shadow: 0 0 0 3px rgba(79, 128, 98, .12);
}

.pdv-action-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1.4fr;
  gap: 10px;
}

/* ---------- Lateral: pagamento e produtos rápidos ---------- */

.pdv-side-panel {
  display: grid;
  gap: 14px;
}

.pdv-side-block {
  border-radius: 14px;
  background: #fffdf8;
  border: 1px solid rgba(38, 67, 50, .12);
  overflow: hidden;
  box-shadow: 0 10px 28px rgba(45, 55, 48, .06);
}

.pdv-side-block-header {
  padding: 13px 14px;
  background: #edf3e9;
  border-bottom: 1px solid rgba(38, 67, 50, .10);
}

.pdv-side-block-header h2 {
  color: #203f30;
  font-size: 1rem;
  font-weight: 950;
}

.pdv-side-block-body {
  display: grid;
  gap: 12px;
  padding: 14px;
}

.pdv-payment-grid {
  display: grid;
  gap: 12px;
}

.pdv-pix {
  display: grid;
  gap: 12px;
  padding: 12px;
  border-radius: 12px;
  background: #f8f1e8;
  border: 1px solid rgba(38, 67, 50, .10);
}

.pdv-pix .badge {
  width: fit-content;
  min-height: 26px;
  padding: 5px 8px;
  border-radius: 8px;
  background: #fff;
  color: #203f30;
  border: 1px solid rgba(38, 67, 50, .12);
  font-size: .68rem;
  font-weight: 950;
}

.qr-placeholder {
  width: min(100%, 180px);
  aspect-ratio: 1;
  display: grid;
  place-items: center;
  margin-inline: auto;
  border-radius: 12px;
  background:
    linear-gradient(90deg, rgba(32, 63, 48, .10) 50%, transparent 50%),
    linear-gradient(rgba(32, 63, 48, .10) 50%, transparent 50%),
    #fff;
  background-size: 20px 20px;
  border: 1px solid rgba(38, 67, 50, .14);
  color: #203f30;
  font-size: 1.65rem;
  font-weight: 950;
  letter-spacing: .12em;
  box-shadow: inset 0 0 0 10px #edf3e9;
}

.pix-key-box {
  display: grid;
  gap: 4px;
  padding: 11px;
  border-radius: 10px;
  background: #fff;
  border: 1px dashed rgba(38, 67, 50, .22);
}

.pix-key-box small {
  color: #647067;
  font-size: .7rem;
  font-weight: 900;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.pix-key-box strong {
  color: #203f30;
  font-size: .9rem;
  font-weight: 950;
  overflow-wrap: anywhere;
}

/* Produtos rápidos */

.pdv-product-tools {
  display: grid;
  gap: 12px;
}

.pdv-category-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-pill {
  min-height: 34px;
  padding: 7px 10px;
  border-radius: 8px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .12);
  color: #4d5a52;
  font-size: .76rem;
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
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
  max-height: 310px;
  overflow-y: auto;
  padding-right: 4px;
}

/* Cards gerados pelo JS */
.pdv-product-card {
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr);
  gap: 10px;
  align-items: center;
  padding: 9px;
  border-radius: 10px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .12);
  text-align: left;
  cursor: pointer;
}

.pdv-product-card:hover {
  transform: none;
  background: #fffdf8;
  border-color: rgba(38, 67, 50, .22);
}

.pdv-product-card img {
  width: 56px;
  height: 56px;
  object-fit: cover;
  border-radius: 8px;
  background: #edf3e9;
}

.pdv-product-card strong {
  color: #203f30;
  font-size: .84rem;
  font-weight: 950;
  line-height: 1.2;
}

.pdv-product-card span,
.pdv-product-card small {
  color: #647067;
  font-size: .72rem;
  font-weight: 750;
}

.pdv-product-card .price,
.pdv-product-card [class*="price"] {
  color: #82495c;
  font-size: .9rem;
  font-weight: 950;
}

/* Histórico */

[data-pdv-history] {
  display: grid;
  gap: 8px;
  max-height: 190px;
  overflow-y: auto;
  padding-right: 4px;
}

[data-pdv-history]:empty::before {
  content: "Nenhuma venda registrada nesta sessão.";
  display: block;
  padding: 12px;
  border-radius: 10px;
  background: #fff;
  border: 1px dashed rgba(38, 67, 50, .16);
  color: #647067;
  font-size: .82rem;
  font-weight: 750;
}

.pdv-history-row {
  display: grid;
  gap: 4px;
  padding: 10px;
  border-radius: 10px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .12);
}

.pdv-history-row strong {
  color: #203f30;
  font-size: .86rem;
  font-weight: 950;
}

.pdv-history-row span,
.pdv-history-row small {
  color: #647067;
  font-size: .74rem;
  font-weight: 750;
}

/* ---------- Responsivo ---------- */

@media (max-width: 1380px) {
  .pdv-screen {
    grid-template-columns: 330px minmax(0, 1fr);
  }

  .pdv-side-panel {
    grid-column: 1 / -1;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 1080px) {
  .pdv-cashier-topbar {
    grid-template-columns: 1fr;
  }

  .pdv-top-actions {
    justify-content: stretch;
  }

  .pdv-top-actions .btn {
    flex: 1;
  }

  .pdv-screen {
    grid-template-columns: 1fr;
  }

  .pdv-side-panel {
    grid-template-columns: 1fr;
  }

  .pdv-product-grid {
    max-height: none;
  }
}

@media (max-width: 720px) {
  .pdv-action-row {
    grid-template-columns: 1fr;
  }

  .pdv-qty-row {
    grid-template-columns: 1fr;
  }

  .pdv-current-items {
    min-height: 300px;
  }

  .pdv-sale-item {
    grid-template-columns: 52px minmax(0, 1fr);
  }

  .pdv-sale-item button {
    grid-column: 1 / -1;
    width: 100%;
  }

  .pdv-total-line {
    align-items: flex-start;
    flex-direction: column;
  }

  .pdv-total-line.main strong {
    font-size: 2.4rem;
  }
}
</style>

<div class="pdv-supermarket-page">
  <section class="pdv-cashier-topbar">
    <div class="pdv-top-brand">
      <span class="brand-icon" aria-hidden="true">A&F</span>
      <div>
        <strong>Arte&Flor PDV</strong>
        <span>Venda presencial e balcão</span>
      </div>
    </div>

    <div class="pdv-open-status">
      <div>
        <small>Status operacional</small>
        <strong>CAIXA ABERTO</strong>
      </div>
    </div>

    <div class="pdv-top-actions">
      <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender venda</button>
      <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
    </div>
  </section>

  <script type="application/json" id="pdvProducts"><?= json_encode($pdvProdutos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <section class="pdv-screen">
    <!-- Entrada rápida -->
    <aside class="pdv-panel">
      <div class="pdv-panel-header">
        <div>
          <h2>Entrada rápida</h2>
          <span>Código, SKU ou nome</span>
        </div>
      </div>

      <div class="pdv-panel-body pdv-entry-card">
        <div class="pdv-product-visual">
          <div class="pdv-product-visual-inner">
            <strong>🛒</strong>
            <span>Pronto para registrar venda</span>
          </div>
        </div>

        <div class="pdv-fast-form">
          <label class="pdv-field pdv-barcode-field">
            <span>Código / SKU / Produto</span>
            <input type="search" data-pdv-search placeholder="Leia o código ou digite o produto">
          </label>

          <div class="pdv-qty-row">
            <label class="pdv-field">
              <span>Qtd.</span>
              <input type="number" min="1" value="1" data-pdv-qty>
            </label>

            <button class="btn btn-primary" type="button" data-pdv-add-search>Adicionar item</button>
          </div>
        </div>

        <div class="pdv-help-box">
          <strong>Atalhos operacionais</strong>
          <div class="pdv-shortcuts">
            <span><b>F2</b> Buscar</span>
            <span><b>F5</b> Nova venda</span>
            <span><b>F7</b> Finalizar</span>
            <span><b>ESC</b> Cancelar</span>
          </div>
        </div>
      </div>
    </aside>

    <!-- Venda atual -->
    <section class="pdv-panel pdv-sale-panel">
      <div class="pdv-panel-header">
        <div class="pdv-sale-title">
          <span class="badge">Venda atual</span>
          <div>
            <h2>Lista de produtos</h2>
            <small>Itens adicionados ao caixa</small>
          </div>
        </div>
        <small>Caixa aberto</small>
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

        <div class="pdv-action-row">
          <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender</button>
          <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar</button>
          <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
        </div>
      </div>
    </section>

    <!-- Lateral -->
    <aside class="pdv-side-panel">
      <div class="pdv-side-block">
        <div class="pdv-side-block-header">
          <h2>Pagamento</h2>
        </div>

        <div class="pdv-side-block-body pdv-payment-grid">
          <label class="pdv-field">
            <span>Cliente</span>
            <input data-pdv-client placeholder="Cliente balcão">
          </label>

          <label class="pdv-field">
            <span>Contato fictício</span>
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

          <div class="pix-box pdv-pix">
            <span class="badge">Pix demonstrativo</span>
            <div class="qr-placeholder">PIX</div>

            <div class="pix-key-box">
              <small>Chave Pix</small>
              <strong>arteflor@pix.demo</strong>
            </div>

            <button class="btn btn-soft" type="button" data-copy-value="arteflor@pix.demo">Copiar chave</button>
          </div>
        </div>
      </div>

      <div class="pdv-side-block">
        <div class="pdv-side-block-header">
          <h2>Produtos rápidos</h2>
        </div>

        <div class="pdv-side-block-body pdv-product-tools">
          <div class="pdv-category-pills">
            <button class="filter-pill active" type="button" data-pdv-category="todos">Todos</button>
            <?php foreach ($categorias as $categoria): ?>
              <button class="filter-pill" type="button" data-pdv-category="<?= e($categoria) ?>"><?= e($categoria) ?></button>
            <?php endforeach; ?>
          </div>

          <div class="pdv-product-grid" data-pdv-product-grid></div>
        </div>
      </div>

      <div class="pdv-side-block">
        <div class="pdv-side-block-header">
          <h2>Últimas vendas</h2>
        </div>

        <div class="pdv-side-block-body">
          <div data-pdv-history></div>
        </div>
      </div>
    </aside>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>