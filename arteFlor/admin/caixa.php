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
   ARTE&FLOR ADMIN — FRENTE DE CAIXA / PDV
   CSS interno somente para caixa.php
========================================================= */

.pdv-admin-page {
  display: grid;
  gap: 22px;
}

/* ---------- Hero ---------- */

.pdv-admin-page .admin-page-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 22px;
  padding: 22px;
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(38, 67, 50, .12);
  box-shadow: 0 12px 30px rgba(45, 55, 48, .06);
}

.pdv-admin-page .admin-page-title {
  display: grid;
  gap: 6px;
}

.pdv-admin-page .admin-page-title .badge,
.pdv-admin-page .badge {
  width: fit-content;
  min-height: 28px;
  padding: 6px 10px;
  border-radius: 10px;
  background: #edf3e9;
  color: #203f30;
  border: 1px solid rgba(38, 67, 50, .12);
  font-size: .72rem;
  font-weight: 900;
  letter-spacing: .06em;
  text-transform: uppercase;
  box-shadow: none;
}

.pdv-admin-page .admin-page-title h1 {
  color: #203f30;
  font-size: clamp(1.8rem, 3vw, 2.45rem);
  font-weight: 950;
  letter-spacing: -.035em;
  line-height: 1.05;
}

.pdv-admin-page .admin-page-title p {
  max-width: 680px;
  color: #647067;
  font-size: .96rem;
  font-weight: 650;
}

.pdv-admin-page .admin-hero-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

/* ---------- Botões ---------- */

.pdv-admin-page .btn {
  min-height: 42px;
  padding: 10px 16px;
  border-radius: 10px;
  font-weight: 850;
  box-shadow: none;
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    color 160ms ease,
    box-shadow 160ms ease;
}

.pdv-admin-page .btn:hover {
  transform: none;
}

.pdv-admin-page .btn-primary {
  background: #203f30;
  border-color: #203f30;
  color: #fff;
}

.pdv-admin-page .btn-primary:hover {
  background: #183426;
  border-color: #183426;
}

.pdv-admin-page .btn-soft,
.pdv-admin-page .btn-outline {
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .14);
  color: #203f30;
}

.pdv-admin-page .btn-soft:hover,
.pdv-admin-page .btn-outline:hover {
  background: #edf3e9;
  border-color: rgba(38, 67, 50, .22);
}

/* ---------- Layout principal ---------- */

.pdv-admin-page .pdv-layout {
  display: grid;
  grid-template-columns: minmax(310px, .95fr) minmax(430px, 1.35fr) minmax(310px, .9fr);
  gap: 18px;
  align-items: start;
}

.pdv-admin-page .admin-panel-card {
  min-width: 0;
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(38, 67, 50, .12);
  box-shadow: 0 12px 30px rgba(45, 55, 48, .06);
  padding: 18px;
}

.pdv-admin-page .admin-panel-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 16px;
}

.pdv-admin-page .admin-panel-header h2 {
  margin-top: 6px;
  color: #203f30;
  font-family: var(--fonte-corpo, system-ui);
  font-size: 1.18rem;
  font-weight: 950;
  letter-spacing: -.025em;
}

.pdv-admin-page .admin-panel-header.compact {
  margin: 18px 0 10px;
  padding-top: 14px;
  border-top: 1px solid rgba(38, 67, 50, .10);
}

.pdv-admin-page .admin-panel-header.compact h2 {
  margin: 0;
  font-size: 1rem;
}

/* ---------- Campos ---------- */

.pdv-admin-page .admin-field {
  display: grid;
  gap: 7px;
  color: #203f30;
  font-size: .86rem;
  font-weight: 850;
}

.pdv-admin-page .admin-field span {
  color: #203f30;
  font-size: .82rem;
  font-weight: 900;
}

.pdv-admin-page .admin-field input,
.pdv-admin-page .admin-field select {
  width: 100%;
  min-height: 42px;
  padding: 0 12px;
  border-radius: 10px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .14);
  color: #2f3631;
  font-size: .92rem;
  font-weight: 700;
  outline: none;
  box-shadow: none;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.pdv-admin-page .admin-field input::placeholder {
  color: #8b948d;
  font-weight: 650;
}

.pdv-admin-page .admin-field input:focus,
.pdv-admin-page .admin-field select:focus {
  border-color: #4f8062;
  box-shadow: 0 0 0 3px rgba(79, 128, 98, .12);
}

/* ---------- Produtos / busca ---------- */

.pdv-admin-page .pdv-products {
  display: grid;
  gap: 14px;
}

.pdv-admin-page .pdv-search-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 96px;
  gap: 10px;
  align-items: end;
  padding: 14px;
  border-radius: 14px;
  background: #f8f1e8;
  border: 1px solid rgba(38, 67, 50, .10);
}

.pdv-admin-page .pdv-search-grid .btn {
  grid-column: 1 / -1;
  width: 100%;
}

.pdv-admin-page .pdv-category-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding-bottom: 2px;
}

.pdv-admin-page .filter-pill {
  min-height: 36px;
  padding: 8px 11px;
  border-radius: 10px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .12);
  color: #4d5a52;
  font-size: .78rem;
  font-weight: 850;
  box-shadow: none;
  transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.pdv-admin-page .filter-pill:hover {
  transform: none;
  background: #edf3e9;
  color: #203f30;
}

.pdv-admin-page .filter-pill.active {
  background: #203f30;
  border-color: #203f30;
  color: #fff;
}

.pdv-admin-page .pdv-product-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  max-height: 560px;
  overflow-y: auto;
  padding-right: 4px;
}

.pdv-admin-page .pdv-product-grid::-webkit-scrollbar,
.pdv-admin-page .pdv-current-items::-webkit-scrollbar,
.pdv-admin-page [data-pdv-history]::-webkit-scrollbar {
  width: 8px;
}

.pdv-admin-page .pdv-product-grid::-webkit-scrollbar-thumb,
.pdv-admin-page .pdv-current-items::-webkit-scrollbar-thumb,
.pdv-admin-page [data-pdv-history]::-webkit-scrollbar-thumb {
  background: rgba(38, 67, 50, .18);
  border-radius: 10px;
}

/* Cards gerados pelo JS */
.pdv-admin-page .pdv-product-card {
  display: grid;
  gap: 8px;
  align-content: start;
  min-height: 100%;
  padding: 10px;
  border-radius: 14px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .12);
  color: #203f30;
  text-align: left;
  cursor: pointer;
  box-shadow: none;
  transition: border-color 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.pdv-admin-page .pdv-product-card:hover {
  transform: none;
  background: #fffdf8;
  border-color: rgba(38, 67, 50, .22);
  box-shadow: 0 8px 20px rgba(45, 55, 48, .05);
}

.pdv-admin-page .pdv-product-card img {
  width: 100%;
  height: 106px;
  object-fit: cover;
  border-radius: 10px;
  background: #edf3e9;
}

.pdv-admin-page .pdv-product-card strong {
  color: #203f30;
  font-size: .9rem;
  font-weight: 950;
  line-height: 1.2;
}

.pdv-admin-page .pdv-product-card span,
.pdv-admin-page .pdv-product-card small {
  color: #647067;
  font-size: .78rem;
  font-weight: 750;
}

.pdv-admin-page .pdv-product-card .price,
.pdv-admin-page .pdv-product-card [class*="price"] {
  color: #82495c;
  font-size: .98rem;
  font-weight: 950;
}

/* ---------- Venda atual ---------- */

.pdv-admin-page .pdv-sale {
  display: grid;
  gap: 14px;
}

.pdv-admin-page .status {
  min-height: 28px;
  padding: 6px 10px;
  border-radius: 10px;
  background: #edf3e9;
  border: 1px solid rgba(38, 67, 50, .12);
  color: #203f30;
  font-size: .72rem;
  font-weight: 900;
  white-space: nowrap;
}

.pdv-admin-page .status::before {
  background: #4f8062;
}

.pdv-admin-page .pdv-current-items {
  display: grid;
  gap: 10px;
  min-height: 350px;
  max-height: 500px;
  overflow-y: auto;
  padding: 12px;
  border-radius: 14px;
  background: #f8f1e8;
  border: 1px solid rgba(38, 67, 50, .10);
}

.pdv-admin-page .pdv-current-items:empty::before {
  content: "Nenhum produto adicionado. Busque ou selecione um item para iniciar a venda.";
  display: grid;
  place-items: center;
  min-height: 220px;
  padding: 22px;
  border-radius: 12px;
  background: #fffdf8;
  border: 1px dashed rgba(38, 67, 50, .18);
  color: #647067;
  text-align: center;
  font-size: .92rem;
  font-weight: 750;
  line-height: 1.45;
}

/* Itens gerados pelo JS */
.pdv-admin-page .pdv-sale-item {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 10px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .12);
  box-shadow: none;
}

.pdv-admin-page .pdv-sale-item img,
.pdv-admin-page .pdv-sale-item > span {
  width: 64px;
  height: 64px;
  border-radius: 10px;
  object-fit: cover;
  background: #edf3e9;
}

.pdv-admin-page .pdv-sale-item strong {
  color: #203f30;
  font-size: .92rem;
  font-weight: 950;
}

.pdv-admin-page .pdv-sale-item small,
.pdv-admin-page .pdv-sale-item span {
  color: #647067;
  font-size: .78rem;
  font-weight: 750;
}

.pdv-admin-page .pdv-sale-item button {
  min-height: 32px;
  padding: 7px 10px;
  border-radius: 9px;
  background: #f5e8e8;
  border: 1px solid rgba(139, 63, 77, .14);
  color: #8b3f4d;
  font-size: .78rem;
  font-weight: 850;
}

/* ---------- Totais ---------- */

.pdv-admin-page .pdv-totals {
  display: grid;
  gap: 0;
  padding: 16px;
  border-radius: 14px;
  background: #fffdf8;
  border: 1px solid rgba(38, 67, 50, .12);
}

.pdv-admin-page .pdv-totals p {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid rgba(38, 67, 50, .10);
  color: #647067;
  font-size: .92rem;
  font-weight: 800;
}

.pdv-admin-page .pdv-totals p:first-child {
  padding-top: 0;
}

.pdv-admin-page .pdv-totals p:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.pdv-admin-page .pdv-totals strong {
  color: #203f30;
  font-weight: 950;
}

.pdv-admin-page .pdv-total strong {
  color: #82495c;
  font-size: 1.55rem;
}

.pdv-admin-page .pdv-totals input {
  width: 120px;
  min-height: 38px;
  padding: 0 10px;
  border-radius: 10px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .14);
  color: #203f30;
  text-align: right;
  font-weight: 850;
  outline: none;
}

.pdv-admin-page .pdv-totals input:focus {
  border-color: #4f8062;
  box-shadow: 0 0 0 3px rgba(79, 128, 98, .12);
}

.pdv-admin-page .admin-action-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.pdv-admin-page .admin-action-row .btn-primary {
  grid-column: 1 / -1;
}

/* ---------- Pagamento ---------- */

.pdv-admin-page .pdv-side {
  display: grid;
  gap: 14px;
}

.pdv-admin-page .pdv-pix {
  display: grid;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  background: #f8f1e8;
  border: 1px solid rgba(38, 67, 50, .10);
}

.pdv-admin-page .qr-placeholder {
  width: min(100%, 210px);
  aspect-ratio: 1;
  display: grid;
  place-items: center;
  margin-inline: auto;
  border-radius: 14px;
  background:
    linear-gradient(90deg, rgba(32, 63, 48, .10) 50%, transparent 50%),
    linear-gradient(rgba(32, 63, 48, .10) 50%, transparent 50%),
    #fff;
  background-size: 20px 20px;
  border: 1px solid rgba(38, 67, 50, .14);
  color: #203f30;
  font-size: 1.8rem;
  font-weight: 950;
  letter-spacing: .12em;
  box-shadow: inset 0 0 0 10px #edf3e9;
}

.pdv-admin-page .pix-key-box {
  display: grid;
  gap: 4px;
  padding: 12px;
  border-radius: 12px;
  background: #fff;
  border: 1px dashed rgba(38, 67, 50, .22);
}

.pdv-admin-page .pix-key-box small {
  color: #647067;
  font-size: .72rem;
  font-weight: 900;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.pdv-admin-page .pix-key-box strong {
  color: #203f30;
  font-size: .92rem;
  font-weight: 950;
  overflow-wrap: anywhere;
}

/* ---------- Histórico ---------- */

.pdv-admin-page .pdv-history {
  display: grid;
  gap: 8px;
}

.pdv-admin-page [data-pdv-history] {
  display: grid;
  gap: 8px;
  max-height: 260px;
  overflow-y: auto;
  padding-right: 4px;
}

.pdv-admin-page [data-pdv-history]:empty::before {
  content: "Nenhuma venda registrada nesta sessão.";
  display: block;
  padding: 14px;
  border-radius: 12px;
  background: #fff;
  border: 1px dashed rgba(38, 67, 50, .16);
  color: #647067;
  font-size: .86rem;
  font-weight: 750;
}

.pdv-admin-page .pdv-history-row {
  display: grid;
  gap: 4px;
  padding: 11px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid rgba(38, 67, 50, .12);
}

.pdv-admin-page .pdv-history-row strong {
  color: #203f30;
  font-size: .9rem;
  font-weight: 950;
}

.pdv-admin-page .pdv-history-row span,
.pdv-admin-page .pdv-history-row small {
  color: #647067;
  font-size: .78rem;
  font-weight: 750;
}

/* ---------- Responsivo ---------- */

@media (max-width: 1280px) {
  .pdv-admin-page .pdv-layout {
    grid-template-columns: minmax(300px, .9fr) minmax(430px, 1.1fr);
  }

  .pdv-admin-page .pdv-side {
    grid-column: 1 / -1;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: start;
  }

  .pdv-admin-page .pdv-side .admin-panel-header,
  .pdv-admin-page .pdv-history {
    grid-column: 1 / -1;
  }

  .pdv-admin-page .pdv-pix {
    grid-column: span 1;
  }
}

@media (max-width: 980px) {
  .pdv-admin-page .admin-page-hero {
    flex-direction: column;
    align-items: stretch;
  }

  .pdv-admin-page .admin-hero-actions {
    justify-content: stretch;
  }

  .pdv-admin-page .admin-hero-actions .btn {
    flex: 1;
  }

  .pdv-admin-page .pdv-layout,
  .pdv-admin-page .pdv-side {
    grid-template-columns: 1fr;
  }

  .pdv-admin-page .pdv-side .admin-panel-header,
  .pdv-admin-page .pdv-history,
  .pdv-admin-page .pdv-pix {
    grid-column: auto;
  }

  .pdv-admin-page .pdv-product-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    max-height: none;
  }

  .pdv-admin-page .pdv-current-items {
    max-height: none;
  }
}

@media (max-width: 720px) {
  .pdv-admin-page .pdv-product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .pdv-admin-page .pdv-search-grid {
    grid-template-columns: 1fr;
  }

  .pdv-admin-page .admin-action-row {
    grid-template-columns: 1fr;
  }

  .pdv-admin-page .admin-action-row .btn-primary {
    grid-column: auto;
  }

  .pdv-admin-page .pdv-sale-item {
    grid-template-columns: 56px minmax(0, 1fr);
  }

  .pdv-admin-page .pdv-sale-item button {
    grid-column: 1 / -1;
    width: 100%;
  }
}

@media (max-width: 480px) {
  .pdv-admin-page .admin-panel-card,
  .pdv-admin-page .admin-page-hero {
    padding: 14px;
    border-radius: 14px;
  }

  .pdv-admin-page .pdv-product-grid {
    grid-template-columns: 1fr;
  }

  .pdv-admin-page .pdv-product-card img {
    height: 150px;
  }

  .pdv-admin-page .admin-hero-actions {
    display: grid;
    grid-template-columns: 1fr;
  }
}
</style>

<div class="pdv-admin-page">
  <section class="admin-page-hero">
    <div class="admin-page-title">
      <span class="badge">PDV</span>
      <h1>Frente de caixa</h1>
      <p>Venda presencial com busca de produto, carrinho do caixa, pagamento demonstrativo e histórico local.</p>
    </div>

    <div class="admin-hero-actions">
      <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender venda</button>
      <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
    </div>
  </section>

  <script type="application/json" id="pdvProducts"><?= json_encode($pdvProdutos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <section class="pdv-layout">
    <aside class="admin-panel-card pdv-products">
      <div class="admin-panel-header">
        <div>
          <span class="badge">Produtos</span>
          <h2>Busca rápida</h2>
        </div>
      </div>

      <div class="pdv-search-grid">
        <label class="admin-field">
          <span>Código, SKU ou nome</span>
          <input type="search" data-pdv-search placeholder="Digite para buscar">
        </label>

        <label class="admin-field">
          <span>Qtd.</span>
          <input type="number" min="1" value="1" data-pdv-qty>
        </label>

        <button class="btn btn-primary" type="button" data-pdv-add-search>Adicionar ao caixa</button>
      </div>

      <div class="pdv-category-pills">
        <button class="filter-pill active" type="button" data-pdv-category="todos">Todos</button>
        <?php foreach ($categorias as $categoria): ?>
          <button class="filter-pill" type="button" data-pdv-category="<?= e($categoria) ?>"><?= e($categoria) ?></button>
        <?php endforeach; ?>
      </div>

      <div class="pdv-product-grid" data-pdv-product-grid></div>
    </aside>

    <section class="admin-panel-card pdv-sale">
      <div class="admin-panel-header">
        <div>
          <span class="badge">Venda atual</span>
          <h2>Carrinho do caixa</h2>
        </div>
        <span class="status status-ok">Caixa aberto</span>
      </div>

      <div class="pdv-current-items" data-pdv-current></div>

      <div class="pdv-totals">
        <p><span>Subtotal</span><strong data-pdv-subtotal>R$ 0,00</strong></p>
        <p>
          <span>Desconto</span>
          <input type="number" min="0" step="0.01" value="0" data-pdv-discount>
        </p>
        <p class="pdv-total"><span>Total</span><strong data-pdv-total>R$ 0,00</strong></p>
      </div>

      <div class="admin-action-row">
        <button class="btn btn-soft" type="button" data-pdv-suspend>Suspender venda</button>
        <button class="btn btn-outline" type="button" data-pdv-cancel>Cancelar venda</button>
        <button class="btn btn-primary" type="button" data-pdv-finish>Finalizar venda</button>
      </div>
    </section>

    <aside class="admin-panel-card pdv-side">
      <div class="admin-panel-header">
        <div>
          <span class="badge">Pagamento</span>
          <h2>Dados rápidos</h2>
        </div>
      </div>

      <label class="admin-field">
        <span>Cliente</span>
        <input data-pdv-client placeholder="Cliente balcão">
      </label>

      <label class="admin-field">
        <span>Contato fictício</span>
        <input data-pdv-contact placeholder="(97) 90000-0000">
      </label>

      <label class="admin-field">
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

      <div class="pdv-history">
        <div class="admin-panel-header compact">
          <h2>Últimas vendas</h2>
        </div>
        <div data-pdv-history></div>
      </div>
    </aside>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>