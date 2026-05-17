<?php
$pageTitle = 'Carrinho';
$activePage = 'catalogo';
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header"><div class="container"><h1 class="section-title">Carrinho</h1><p class="section-subtitle">Revise seus produtos antes de finalizar o pedido.</p></div></section>
<section class="section"><div class="container checkout-layout"><div class="card" id="cartList"></div><aside class="card"><h2>Resumo</h2><p class="price" id="cartTotal">R$ 0,00</p><div class="actions"><a class="btn btn-soft" href="catalogo.php">Continuar comprando</a><a class="btn btn-primary" href="checkout.php">Finalizar compra</a></div></aside></div></section>
<script src="<?= asset('js/carrinho.js') ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
