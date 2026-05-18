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
document.body.classList.add('pdv-market-mode');
</script>

<style>
/* =========================================================
   ARTE&FLOR — PDV ESTILO MERCADO
   Layout fullscreen, compacto e operacional
========================================================= */

body.pdv-market-mode .admin-shell {
  grid-template-columns: 1fr !important;
  background: #eef2ed;
}

body.pdv-market-mode .admin-sidebar {
  display: none !important;
}

body.pdv-market-mode .admin-main {
  height: 100vh;
  overflow: hidden;
  padding: 8px !important;
  background: #eef2ed;
}

.pdv-market-page {
  height: calc(100vh - 16px);
  display: grid;
  grid-template-rows: 58px minmax(0, 1fr);
  gap: 8px;
  color: #203f30;
}

/* TOPO */

.pdv-market-top {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr) auto;
  gap: 8px;
}

.pdv-market-brand,
.pdv-market-status,
.pdv-market-actions {
  border-radius: 10px;
  background: #fffdf8;
  border: 1px solid rgba(32, 63, 48, .12);
  box-shadow: 0 8px 18px rgba(45, 55, 48, .05);
}

.pdv-market-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
}

.pdv-market-logo {
  width: 38px;
  height: 38px;
  min-width: 38px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  background: #dfeadd;
  border: 1px solid rgba(32, 63, 48, .12);
  color: #203f30;
  font-weight: 950;
}

.pdv-market-brand strong {
  display: block;
  color: #203f30;
  font-size: .96rem;
  font-weight: 950;
  line-height: 1.1;
}

.pdv-market-brand span {
  display: block;
  margin-top: 1px;
  color: #647067;
  font-size: .72rem;
  font-weight: 750;
}

.pdv-market-status {
  display: grid;
  place-items: center;
  background: #203f30;
  color: #fff;
  text-align: center;
}

.pdv-market-status small {
  display: block;
  color: rgba(255, 255, 255, .72);
  font-size: .63rem;
  font-weight: 900;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.pdv-market-status strong {
  display: block;
  color: #fff;
  font-size: clamp(1.25rem, 2.5vw, 2rem);
  font-weight: 950;
  line-height: 1;
}

.pdv-market-actions {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 9px;
}

.pdv-market-page .btn {
  min-height: 38px;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: .8rem;
  font-weight: 850;
  box-shadow: none;
  white-space: nowrap;
}

.pdv-market-page .btn:hover {
  transform: none;
}

.pdv-market-page .btn-primary {
  background: #203f30;
  border-color: #203f30;
  color: #fff;
}

.pdv-market-page .btn-primary:hover {
  background: #173327;
}

.pdv-market-page .btn-soft,
.pdv-market-page .btn-outline {
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .14);
  color: #203f30;
}

.pdv-market-page .btn-soft:hover,
.pdv-market-page .btn-outline:hover {
  background: #edf3e9;
}

/* GRADE PRINCIPAL */

.pdv-market-grid {
  min-height: 0;
  display: grid;
  grid-template-columns: 290px minmax(0, 1fr) 335px;
  gap: 8px;
}

.pdv-panel {
  min-height: 0;
  overflow: hidden;
  border-radius: 10px;
  background: #fffdf8;
  border: 1px solid rgba(32, 63, 48, .12);
  box-shadow: 0 8px 18px rgba(45, 55, 48, .05);
}

.pdv-panel-head {
  min-height: 38px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 10px;
  background: #dfeadd;
  border-bottom: 1px solid rgba(32, 63, 48, .10);
}

.pdv-panel-head h2 {
  margin: 0;
  color: #203f30;
  font-family: var(--fonte-corpo, system-ui);
  font-size: .9rem;
  font-weight: 950;
}

.pdv-panel-head span,
.pdv-panel-head small {
  color: #647067;
  font-size: .68rem;
  font-weight: 800;
}

.pdv-panel-body {
  padding: 9px;
}

/* COLUNA ESQUERDA */

.pdv-left {
  display: grid;
  grid-template-rows: 38px minmax(0, 1fr);
}

.pdv-left-body {
  min-height: 0;
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr);
  gap: 8px;
}

.pdv-selected-product {
  min-height: 235px;
  display: grid;
  grid-template-rows: 150px auto;
  overflow: hidden;
  border-radius: 9px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .12);
}

.pdv-selected-image {
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
  gap: 5px;
  text-align: center;
  color: #647067;
}

.pdv-selected-placeholder strong {
  color: #203f30;
  font-size: 2.1rem;
  line-height: 1;
}

.pdv-selected-info {
  display: grid;
  gap: 3px;
  padding: 9px;
}

.pdv-selected-info small {
  color: #647067;
  font-size: .64rem;
  font-weight: 900;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.pdv-selected-info strong {
  color: #203f30;
  font-size: .92rem;
  font-weight: 950;
  line-height: 1.18;
}

.pdv-selected-info span {
  color: #82495c;
  font-size: .88rem;
  font-weight: 950;
}

/* BUSCA */

.pdv-field {
  display: grid;
  gap: 4px;
}

.pdv-field span {
  color: #203f30;
  font-size: .66rem;
  font-weight: 950;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.pdv-field input,
.pdv-field select,
.pdv-money-input {
  width: 100%;
  min-height: 36px;
  padding: 0 9px;
  border-radius: 8px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .14);
  color: #203f30;
  font-size: .86rem;
  font-weight: 800;
  outline: none;
}

.pdv-field input::placeholder {
  color: #8b948d;
  font-weight: 650;
}

.pdv-field input:focus,
.pdv-field select:focus,
.pdv-money-input:focus {
  border-color: #4f8062;
  box-shadow: 0 0 0 3px rgba(79, 128, 98, .12);
}

.pdv-code-field input {
  min-height: 48px;
  font-size: .98rem;
  font-weight: 900;
}

.pdv-entry-form {
  display: grid;
  gap: 8px;
}

.pdv-qty-row {
  display: grid;
  grid-template-columns: 72px 1fr;
  gap: 7px;
  align-items: end;
}

/* MINI PAGAMENTO */

.pdv-mini-payment {
  align-self: end;
  display: grid;
  gap: 8px;
  padding: 9px;
  border-radius: 9px;
  background: #f8f1e8;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-mini-payment-title {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.pdv-mini-payment-title strong {
  color: #203f30;
  font-size: .8rem;
  font-weight: 950;
}

.pdv-mini-payment-title span {
  color: #647067;
  font-size: .68rem;
  font-weight: 800;
}

.pdv-hidden {
  display: none !important;
}

/* CENTRO — CUPOM */

.pdv-center {
  display: grid;
  grid-template-rows: 38px minmax(0, 1fr) 252px;
}

.pdv-sale-label {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pdv-step-badge {
  min-height: 22px;
  padding: 4px 7px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .12);
  color: #203f30;
  font-size: .62rem;
  font-weight: 950;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.pdv-ticket {
  min-height: 0;
  height: 100%;
  display: grid;
  grid-template-rows: 32px minmax(0, 1fr);
  background: #fff;
}

.pdv-ticket-head {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr) 90px 92px 72px;
  gap: 8px;
  align-items: center;
  padding: 0 10px;
  background: #fbf8f2;
  border-bottom: 1px solid rgba(32, 63, 48, .10);
  color: #647067;
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
  gap: 6px;
  padding: 8px;
  background: #fff;
}

.pdv-current-items:empty::before {
  content: "Nenhum item lançado. Digite o produto, leia o código ou selecione nos produtos rápidos.";
  display: grid;
  place-items: center;
  min-height: 100%;
  padding: 22px;
  border-radius: 9px;
  background: #fbf8f2;
  border: 1px dashed rgba(32, 63, 48, .18);
  color: #647067;
  text-align: center;
  font-size: .88rem;
  font-weight: 800;
  line-height: 1.45;
}

/* Itens gerados pelo JS */
.pdv-sale-item {
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr) auto;
  gap: 9px;
  align-items: center;
  padding: 7px;
  border-radius: 8px;
  background: #fffdf8;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-sale-item img,
.pdv-sale-item > span {
  width: 54px;
  height: 54px;
  border-radius: 6px;
  object-fit: cover;
  background: #edf3e9;
}

.pdv-sale-item strong {
  color: #203f30;
  font-size: .84rem;
  font-weight: 950;
  line-height: 1.15;
}

.pdv-sale-item small,
.pdv-sale-item span {
  color: #647067;
  font-size: .68rem;
  font-weight: 750;
}

.pdv-sale-item button {
  min-height: 28px;
  padding: 5px 8px;
  border-radius: 6px;
  background: #f5e8e8;
  border: 1px solid rgba(139, 63, 77, .14);
  color: #8b3f4d;
  font-size: .68rem;
  font-weight: 850;
}

/* FECHAMENTO */

.pdv-close-panel {
  display: grid;
  grid-template-columns: 1fr 1fr 1.15fr;
  gap: 7px;
  padding: 8px;
  background: #f8f1e8;
  border-top: 1px solid rgba(32, 63, 48, .10);
}

.pdv-total-box,
.pdv-input-box,
.pdv-change-box {
  display: grid;
  gap: 4px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .10);
}

.pdv-total-box span,
.pdv-input-box span,
.pdv-change-box span {
  color: #647067;
  font-size: .66rem;
  font-weight: 900;
  letter-spacing: .05em;
  text-transform: uppercase;
}

.pdv-total-box strong,
.pdv-change-box strong {
  color: #203f30;
  font-size: 1rem;
  font-weight: 950;
}

.pdv-total-box.main {
  grid-column: 1 / 3;
  min-height: 66px;
  background: #203f30;
  border-color: #203f30;
}

.pdv-total-box.main span {
  color: rgba(255, 255, 255, .72);
}

.pdv-total-box.main strong {
  color: #fff;
  font-size: clamp(2rem, 4vw, 2.75rem);
  line-height: 1;
}

.pdv-change-box {
  grid-column: 3 / 4;
  grid-row: 1 / 3;
  align-content: center;
}

.pdv-change-box strong {
  color: #82495c;
  font-size: clamp(1.7rem, 3vw, 2.25rem);
  line-height: 1;
}

.pdv-change-box.is-missing strong {
  color: #8b3f4d;
  font-size: clamp(1.25rem, 2vw, 1.6rem);
}

.pdv-money-input {
  min-height: 32px;
  text-align: right;
  font-weight: 900;
}

.pdv-quick-cash {
  grid-column: 1 / 3;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 5px;
}

.pdv-quick-cash button {
  min-height: 30px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .12);
  color: #203f30;
  font-size: .68rem;
  font-weight: 900;
  cursor: pointer;
}

.pdv-quick-cash button:hover {
  background: #edf3e9;
}

.pdv-final-actions {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: 1fr 1fr 1.35fr;
  gap: 7px;
}

/* DIREITA — PRODUTOS */

.pdv-right {
  display: grid;
  grid-template-rows: 38px auto minmax(0, 1fr);
}

.pdv-category-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  padding: 8px;
  background: #f8f1e8;
  border-bottom: 1px solid rgba(32, 63, 48, .10);
}

.filter-pill {
  min-height: 28px;
  padding: 5px 7px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid rgba(32, 63, 48, .12);
  color: #4d5a52;
  font-size: .66rem;
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
  gap: 6px;
  padding: 8px;
}

/* Cards gerados pelo JS */
.pdv-product-card {
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr);
  gap: 7px;
  align-items: center;
  padding: 6px;
  border-radius: 8px;
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
  border-radius: 6px;
  background: #edf3e9;
}

.pdv-product-card strong {
  color: #203f30;
  font-size: .76rem;
  font-weight: 950;
  line-height: 1.15;
}

.pdv-product-card span,
.pdv-product-card small {
  color: #647067;
  font-size: .64rem;
  font-weight: 750;
}

.pdv-product-card .price,
.pdv-product-card [class*="price"] {
  color: #82495c;
  font-size: .78rem;
  font-weight: 950;
}

.pdv-hidden-history {
  display: none !important;
}

/* SCROLL */

.pdv-current-items::-webkit-scrollbar,
.pdv-product-grid::-webkit-scrollbar {
  width: 7px;
}

.pdv-current-items::-webkit-scrollbar-thumb,
.pdv-product-grid::-webkit-scrollbar-thumb {
  background: rgba(32, 63, 48, .18);
  border-radius: 10px;
}

/* NOTEBOOK */

@media (max-width: 1366px) {
  .pdv-market-grid {
    grid-template-columns: 275px minmax(0, 1fr) 305px;
  }

  .pdv-market-top {
    grid-template-columns: 225px minmax(0, 1fr) auto;
  }

  .pdv-selected-product {
    min-height: 210px;
    grid-template-rows: 128px auto;
  }

  .pdv-center {
    grid-template-rows: 38px minmax(0, 1fr) 238px;
  }

  .pdv-total-box.main strong {
    font-size: 2.25rem;
  }

  .pdv-change-box strong {
    font-size: 1.9rem;
  }
}

@media (max-width: 1180px) {
  body.pdv-market-mode .admin-main {
    overflow-y: auto;
  }

  .pdv-market-page {
    height: auto;
    min-height: calc(100vh - 16px);
    grid-template-rows: auto auto;
  }

  .pdv-market-top,
  .pdv-market-grid {
    grid-template-columns: 1fr;
  }

  .pdv-left,
  .pdv-center,
  .pdv-right {
    grid-template-rows: auto;
  }

  .pdv-current-items {
    min-height: 300px;
    max-height: 420px;
  }

  .pdv-close-panel {
    grid-template-columns: 1fr;
  }

  .pdv-total-box.main,
  .pdv-change-box,
  .pdv-quick-cash,
  .pdv-final-actions {
    grid-column: auto;
    grid-row: auto;
  }

  .pdv-quick-cash,
  .pdv-final-actions {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="pdv-market-page">
  <section class="pdv-market-top">
    <div class="pdv-market-brand">
      <span class="pdv-market-logo">A&F</span>
      <div>
        <strong>Arte&Flor PDV</strong>
        <span>Venda presencial</span>
      </div>
    </div>

    <div class="pdv-market-status">
      <div>
        <small>Status operacional</small>
        <strong>CAIXA ABERTO</strong>
      </div>
    </div>

    <div class="pdv-market-actions">
      <a class="btn btn-soft" href="<?= site_url('admin/dashboard.php') ?>">Dashboard</a>
      <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar</button>
      <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar</button>
    </div>
  </section>

  <script type="application/json" id="pdvProducts"><?= json_encode($pdvProdutos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <section class="pdv-market-grid">
    <aside class="pdv-panel pdv-left">
      <div class="pdv-panel-head">
        <div>
          <h2>Produto selecionado</h2>
          <span>Imagem do último item</span>
        </div>
      </div>

      <div class="pdv-panel-body pdv-left-body">
        <div class="pdv-selected-product">
          <div class="pdv-selected-image" data-pdv-selected-image>
            <div class="pdv-selected-placeholder">
              <strong>🛒</strong>
              <span>Nenhum produto selecionado</span>
            </div>
          </div>

          <div class="pdv-selected-info">
            <small>Último item</small>
            <strong data-pdv-selected-name>Aguardando produto</strong>
            <span data-pdv-selected-price>R$ 0,00</span>
          </div>
        </div>

        <div class="pdv-entry-form">
          <label class="pdv-field pdv-code-field">
            <span>Código / SKU / Nome</span>
            <input type="search" data-pdv-search placeholder="Leia ou digite o produto" autofocus>
          </label>

          <div class="pdv-qty-row">
            <label class="pdv-field">
              <span>Qtd.</span>
              <input type="number" min="1" value="1" data-pdv-qty>
            </label>

            <button class="btn btn-primary" type="button" data-pdv-add-search>Adicionar</button>
          </div>
        </div>

        <div class="pdv-mini-payment">
          <div class="pdv-mini-payment-title">
            <strong>Pagamento</strong>
            <span>Dados mínimos</span>
          </div>

          <label class="pdv-field">
            <span>Cliente</span>
            <input data-pdv-client placeholder="Cliente balcão">
          </label>

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

    <main class="pdv-panel pdv-center">
      <div class="pdv-panel-head">
        <div class="pdv-sale-label">
          <span class="pdv-step-badge">Venda atual</span>
          <div>
            <h2>Cupom de venda</h2>
            <small>Itens registrados no caixa</small>
          </div>
        </div>
        <small>Operador: Admin</small>
      </div>

      <div class="pdv-ticket">
        <div class="pdv-ticket-head">
          <span>Img</span>
          <span>Produto</span>
          <span>Qtd</span>
          <span>Valor</span>
          <span>Ação</span>
        </div>

        <div class="pdv-current-items" data-pdv-current></div>
      </div>

      <div class="pdv-close-panel">
        <div class="pdv-total-box">
          <span>Subtotal</span>
          <strong data-pdv-subtotal>R$ 0,00</strong>
        </div>

        <div class="pdv-input-box">
          <span>Desconto</span>
          <input class="pdv-money-input" type="number" min="0" step="0.01" value="0" data-pdv-discount>
        </div>

        <div class="pdv-total-box main">
          <span>Total da venda</span>
          <strong data-pdv-total>R$ 0,00</strong>
        </div>

        <div class="pdv-input-box">
          <span>Valor recebido</span>
          <input class="pdv-money-input" type="number" min="0" step="0.01" value="0" data-pdv-received>
        </div>

        <div class="pdv-quick-cash">
          <button type="button" data-pdv-cash-exact>Exato</button>
          <button type="button" data-pdv-cash-add="10">+10</button>
          <button type="button" data-pdv-cash-add="20">+20</button>
          <button type="button" data-pdv-cash-add="50">+50</button>
          <button type="button" data-pdv-cash-clear>Limpar</button>
        </div>

        <div class="pdv-change-box" data-pdv-change-box>
          <span>Troco</span>
          <strong data-pdv-change>R$ 0,00</strong>
        </div>

        <div class="pdv-final-actions">
          <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender</button>
          <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar</button>
          <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
        </div>
      </div>
    </main>

    <aside class="pdv-panel pdv-right">
      <div class="pdv-panel-head">
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
  const totalEl = document.querySelector('[data-pdv-total]');
  const receivedInput = document.querySelector('[data-pdv-received]');
  const changeEl = document.querySelector('[data-pdv-change]');
  const changeBox = document.querySelector('[data-pdv-change-box]');
  const exactBtn = document.querySelector('[data-pdv-cash-exact]');
  const clearBtn = document.querySelector('[data-pdv-cash-clear]');
  const addButtons = document.querySelectorAll('[data-pdv-cash-add]');
  const searchInput = document.querySelector('[data-pdv-search]');
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
    selectedPrice.textContent = priceText || 'Produto selecionado';
  }

  function updateSelectedFromSale() {
    if (!currentList) return;

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
      updateChange();
      return;
    }

    updateSelectedFromCard(lastItem);
    updateChange();
  }

  receivedInput?.addEventListener('input', updateChange);

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