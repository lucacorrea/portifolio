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
      <label class="form-group"><span>Pagamento</span><select name="pagamento" id="paymentMethod" data-payment-method required><option value="">Selecione a forma</option><option>Pix</option><option>Presencial</option><option>Dinheiro</option><option>Cartão na entrega</option></select></label>

      <div class="pix-checkout-panel form-group full" data-pix-panel hidden>
        <div class="pix-panel-header">
          <div>
            <span class="badge">Pix demonstrativo</span>
            <h2>QR Code Pix para apresentação</h2>
            <p class="muted">Este QR Code é visual e não processa pagamento real. A integração com Pix será feita somente na etapa de backend.</p>
          </div>
          <span class="status" data-pix-status>Aguardando pagamento</span>
        </div>

        <div class="pix-panel-grid">
          <div class="pix-qr-demo" role="img" aria-label="QR Code Pix demonstrativo">
            <span></span><span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span><span></span>
            <strong>PIX</strong>
          </div>

          <div class="pix-payment-box">
            <small>Chave Pix demonstrativa</small>
            <strong>arteflor@pix.demo</strong>
            <small>Código copia e cola</small>
            <code data-pix-code>00020126580014BR.GOV.BCB.PIX0136arteflor-demo-checkout5204000053039865802BR5910ARTE E FLOR6005COARI62070503***6304DEMO</code>
            <div class="actions">
              <button class="btn btn-soft" type="button" data-copy-pix>Copiar código Pix</button>
              <button class="btn btn-primary" type="button" data-system-finish>Finalizar no sistema</button>
            </div>
            <p class="muted" data-system-result>Ao finalizar no sistema, salvaremos apenas uma venda simulada neste navegador.</p>
          </div>
        </div>
      </div>

      <label class="form-group full"><span>Observações</span><textarea name="observacoes"></textarea></label>
      <button class="btn btn-primary" type="submit">Enviar pedido pelo WhatsApp</button>
    </form>
    <aside class="card"><h2>Resumo do pedido</h2><div id="checkoutSummary"></div><p class="price" id="checkoutTotal">R$ 0,00</p></aside>
  </div>
</section>
<script src="<?= asset('js/checkout.js') ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
