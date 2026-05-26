<?php
$pageTitle = 'Carrinho';
$activePage = 'catalogo';
$pageScripts = ['js/carrinho.js'];

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================
   CARRINHO - FLUXO REAL LOCALSTORAGE + CHECKOUT VALIDADO
   CSS interno somente para carrinho.php
========================================================= */

.cart-page-header {
  background: linear-gradient(135deg, #edf3e9 0%, #fbf4ec 100%);
  border-bottom: 1px solid rgba(47, 72, 58, .12);
}

.cart-page-header .page-header-grid {
  align-items: center;
}

.cart-page-header .badge {
  border-radius: 10px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  color: #244836;
  box-shadow: none;
}

.cart-page {
  grid-template-columns: minmax(0, 1fr) minmax(320px, 390px);
  gap: 28px;
  align-items: start;
}

.cart-page-list {
  display: grid;
  gap: 14px;
  padding: 18px;
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: 0 14px 34px rgba(45, 55, 48, .07);
}

.cart-list-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 4px 2px 12px;
  border-bottom: 1px solid rgba(47, 72, 58, .10);
}

.cart-list-header strong {
  display: block;
  color: #244836;
  font-size: 1.05rem;
  font-weight: 950;
}

.cart-list-header span {
  color: #626b64;
  font-size: .9rem;
  font-weight: 700;
}

.cart-count-pill {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  padding: 6px 10px;
  border-radius: 10px;
  background: #edf3e9;
  color: #244836;
  border: 1px solid rgba(47, 72, 58, .12);
  font-size: .72rem;
  font-weight: 900;
  letter-spacing: .04em;
  text-transform: uppercase;
  white-space: nowrap;
}

.cart-page-list .cart-item {
  display: grid;
  grid-template-columns: 104px minmax(0, 1fr) auto auto;
  gap: 16px;
  align-items: center;
  padding: 14px;
  border-radius: 16px;
  background: #fff;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: none;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.cart-page-list .cart-item:hover {
  transform: none;
  border-color: rgba(47, 72, 58, .22);
  box-shadow: 0 10px 26px rgba(45, 55, 48, .06);
}

.cart-page-list .cart-thumb {
  width: 104px;
  height: 92px;
  border-radius: 12px;
  overflow: hidden;
  background: #edf3e9;
  display: grid;
  place-items: center;
  color: #244836;
  font-weight: 950;
}

.cart-page-list .cart-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cart-page-list .cart-item-info {
  display: grid;
  gap: 5px;
  min-width: 0;
}

.cart-page-list .cart-item-info strong {
  color: #244836;
  font-size: 1rem;
  font-weight: 950;
  line-height: 1.25;
}

.cart-page-list .cart-item-info span {
  color: #626b64;
  font-size: .88rem;
  font-weight: 750;
}

.cart-page-list .cart-item-info small {
  color: #7b847d;
  font-size: .82rem;
  font-weight: 650;
  line-height: 1.35;
}

.cart-page-list .qty-control {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 40px;
  padding: 4px;
  border-radius: 12px;
  background: #f8f1e8;
  border: 1px solid rgba(47, 72, 58, .12);
}

.cart-page-list .qty-control button {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #fff;
  color: #244836;
  border: 1px solid rgba(47, 72, 58, .12);
  font-weight: 950;
  cursor: pointer;
}

.cart-page-list .qty-control button:hover:not(:disabled) {
  background: #edf3e9;
}

.cart-page-list .qty-control button:disabled {
  opacity: .46;
  cursor: not-allowed;
}

.cart-page-list .qty-control strong {
  min-width: 38px;
  color: #244836;
  font-weight: 950;
  text-align: center;
}

.cart-page-list .cart-line-total {
  display: grid;
  gap: 8px;
  justify-items: end;
  min-width: 122px;
}

.cart-page-list .cart-line-total strong {
  color: #82495c;
  font-size: 1.02rem;
  font-weight: 950;
}

.cart-page-list .cart-line-total button {
  min-height: 36px;
  padding: 8px 12px;
  border-radius: 10px;
  background: #f5e8e8;
  color: #8b3f4d;
  border: 1px solid rgba(139, 63, 77, .14);
  font-size: .82rem;
  font-weight: 850;
  cursor: pointer;
}

.cart-page-list .cart-line-total button:hover {
  background: #efdada;
}

.cart-page-summary {
  position: sticky;
  top: 96px;
  display: grid;
  gap: 14px;
  padding: 24px;
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: 0 14px 34px rgba(45, 55, 48, .07);
}

.cart-page-summary .badge {
  width: fit-content;
  border-radius: 10px;
  background: #edf3e9;
  color: #244836;
  border: 1px solid rgba(47, 72, 58, .12);
}

.cart-page-summary h2 {
  color: #244836;
  font-family: var(--fonte-corpo);
  font-size: 1.55rem;
  font-weight: 950;
  letter-spacing: -.025em;
}

.summary-lines {
  display: grid;
  gap: 0;
}

.summary-lines p {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  padding: 13px 0;
  border-bottom: 1px solid rgba(47, 72, 58, .10);
  color: #626b64;
  font-weight: 750;
}

.summary-lines strong {
  color: #244836;
  font-weight: 950;
  white-space: nowrap;
}

.summary-total strong {
  color: #82495c;
  font-size: 1.45rem;
}

.cart-page-summary .actions {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-top: 4px;
}

.cart-page-summary .btn {
  width: 100%;
  min-height: 46px;
  border-radius: 10px;
}

.cart-page-summary .btn[aria-disabled="true"] {
  opacity: .54;
  cursor: not-allowed;
  pointer-events: none;
}

.cart-page-summary .muted {
  color: #7b847d;
  font-size: .86rem;
}

.cart-page-list .empty-state {
  padding: 28px;
  border-radius: 16px;
  background: #f8f1e8;
  border: 1px dashed rgba(47, 72, 58, .20);
  text-align: center;
}

.cart-page-list .empty-state strong {
  display: block;
  color: #244836;
  margin-bottom: 4px;
}

.cart-page-list .empty-state p {
  color: #626b64;
}

.cart-page-list .empty-state .btn {
  margin-top: 14px;
}

@media (max-width: 980px) {
  .cart-page {
    grid-template-columns: 1fr;
  }

  .cart-page-summary {
    position: static;
  }
}

@media (max-width: 720px) {
  .cart-list-header {
    flex-direction: column;
  }

  .cart-page-list .cart-item {
    grid-template-columns: 88px minmax(0, 1fr);
  }

  .cart-page-list .cart-thumb {
    width: 88px;
    height: 88px;
  }

  .cart-page-list .qty-control,
  .cart-page-list .cart-line-total {
    grid-column: 1 / -1;
  }

  .cart-page-list .cart-line-total {
    justify-items: start;
    padding-top: 12px;
    border-top: 1px solid rgba(47, 72, 58, .10);
  }
}

@media (max-width: 480px) {
  .cart-page-list .cart-item {
    grid-template-columns: 1fr;
  }

  .cart-page-list .cart-thumb {
    width: 100%;
    height: 180px;
  }

  .cart-page-list .qty-control {
    width: 100%;
    justify-content: space-between;
  }
}
</style>

<section class="page-header cart-page-header">
  <div class="container page-header-grid">
    <div>
      <span class="badge">Carrinho</span>
      <h1 class="section-title">Revise sua compra</h1>
      <p class="section-subtitle">Ajuste quantidades, confira os itens escolhidos e avance para um checkout validado no banco.</p>
    </div>
    <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Continuar comprando</a>
  </div>
</section>

<section class="section">
  <div class="container cart-layout cart-page">
    <div class="cart-list cart-page-list card">
      <div class="cart-list-header">
        <div>
          <strong>Produtos selecionados</strong>
          <span data-cart-status>Carregando carrinho...</span>
        </div>
        <span class="cart-count-pill" data-cart-page-count>0 itens</span>
      </div>

      <div id="cartList" aria-live="polite">
        <div class="empty-state">
          <strong>Carregando carrinho...</strong>
          <p>Se nada aparecer, escolha produtos no catálogo para iniciar o pedido.</p>
        </div>
      </div>

      <noscript>
        <div class="empty-state">
          <strong>JavaScript necessário</strong>
          <p>Ative o JavaScript para revisar itens, ajustar quantidades e seguir para o checkout.</p>
        </div>
      </noscript>
    </div>

    <aside class="cart-summary cart-page-summary card">
      <span class="badge">Resumo</span>
      <h2>Total do pedido</h2>

      <div class="summary-lines">
        <p><span>Subtotal</span><strong id="cartSubtotal">R$ 0,00</strong></p>
        <p><span>Desconto automático</span><strong id="cartDiscount">R$ 0,00</strong></p>
        <p class="summary-total"><span>Total</span><strong id="cartTotal">R$ 0,00</strong></p>
      </div>

      <div class="actions">
        <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Continuar comprando</a>
        <a class="btn btn-primary" href="<?= site_url('checkout.php') ?>" data-checkout-link aria-disabled="true">Ir para checkout</a>
      </div>

      <p class="muted">Preços, estoque e disponibilidade são conferidos novamente no checkout antes de salvar o pedido.</p>
    </aside>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
