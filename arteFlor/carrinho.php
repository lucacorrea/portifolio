<?php
$pageTitle = 'Carrinho';
$activePage = 'catalogo';
$pageScripts = ['js/carrinho.js'];
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container">
    <span class="badge">Carrinho local</span>
    <h1 class="section-title">Revise sua compra</h1>
    <p class="section-subtitle">Os itens ficam salvos apenas no navegador por localStorage para simular a jornada do cliente.</p>
  </div>
</section>

<section class="section">
  <div class="container checkout-layout">
    <div id="cartList" class="admin-panel" aria-live="polite"></div>
    <aside class="card checkout-summary">
      <h2>Resumo</h2>
      <div class="summary-line"><span>Subtotal</span><strong id="cartSubtotal">R$ 0,00</strong></div>
      <div class="summary-line"><span>Entrega</span><strong>A combinar</strong></div>
      <div class="summary-line"><span>Total</span><strong class="price" id="cartTotal">R$ 0,00</strong></div>
      <div class="actions">
        <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Continuar comprando</a>
        <a class="btn btn-primary" href="<?= site_url('checkout.php') ?>">Finalizar compra</a>
      </div>
    </aside>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
