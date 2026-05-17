<?php
$pageTitle = 'Checkout';
$activePage = 'catalogo';
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header"><div class="container"><h1 class="section-title">Finalizar pedido</h1><p class="section-subtitle">Preencha os dados para enviar o pedido organizado pelo WhatsApp.</p></div></section>
<section class="section">
  <div class="container checkout-layout">
    <form class="card form-grid" id="checkoutForm">
      <label class="form-group"><span>Nome</span><input name="nome" required></label>
      <label class="form-group"><span>WhatsApp</span><input name="whatsapp" required></label>
      <label class="form-group"><span>Bairro</span><input name="bairro" required></label>
      <label class="form-group"><span>Endereço</span><input name="endereco" required></label>
      <label class="form-group"><span>Tipo de recebimento</span><select name="recebimento"><option>Entrega</option><option>Retirada</option></select></label>
      <label class="form-group"><span>Pagamento</span><select name="pagamento"><option>Pix</option><option>Presencial</option><option>Dinheiro</option><option>Cartão na entrega</option></select></label>
      <label class="form-group full"><span>Observações</span><textarea name="observacoes"></textarea></label>
      <button class="btn btn-primary" type="submit">Enviar pedido pelo WhatsApp</button>
    </form>
    <aside class="card"><h2>Resumo do pedido</h2><div id="checkoutSummary"></div><p class="price" id="checkoutTotal">R$ 0,00</p></aside>
  </div>
</section>
<script src="<?= asset('js/checkout.js') ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
