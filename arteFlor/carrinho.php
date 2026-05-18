<?php
$pageTitle = 'Carrinho';
$activePage = 'catalogo';
$pageScripts = ['js/carrinho.js'];
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header compact-header">
  <div class="container page-header-grid">
    <div>
      <span class="badge">Carrinho</span>
      <h1 class="section-title">Revise sua compra</h1>
      <p class="section-subtitle">Ajuste quantidades, confira imagens e avance para o checkout visual.</p>
    </div>
    <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Continuar comprando</a>
  </div>
</section>

<section class="section">
  <div class="container cart-layout">
    <div class="cart-list card" id="cartList"></div>

    <aside class="card cart-summary">
      <span class="badge">Resumo</span>
      <h2>Total do pedido</h2>
      <div class="summary-lines">
        <p><span>Subtotal</span><strong id="cartSubtotal">R$ 0,00</strong></p>
        <p><span>Desconto demonstrativo</span><strong id="cartDiscount">R$ 0,00</strong></p>
        <p class="summary-total"><span>Total</span><strong id="cartTotal">R$ 0,00</strong></p>
      </div>
      <div class="actions">
        <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Continuar comprando</a>
        <a class="btn btn-primary" href="<?= site_url('checkout.php') ?>">Ir para checkout</a>
      </div>
      <p class="muted">Carrinho salvo somente neste navegador por localStorage.</p>
    </aside>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
