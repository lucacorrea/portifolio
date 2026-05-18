<?php
$pageTitle = 'Checkout';
$activePage = 'catalogo';
$pageScripts = ['js/checkout.js'];
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header compact-header">
  <div class="container">
    <span class="badge">Checkout</span>
    <h1 class="section-title">Finalizar pedido no sistema</h1>
    <p class="section-subtitle">Dados fictícios, pagamento demonstrativo e pedido salvo no navegador para apresentação do MVP.</p>
  </div>
</section>

<section class="section">
  <div class="container checkout-layout">
    <form class="card form-grid checkout-form" id="checkoutForm">
      <div class="form-group full form-section-title">
        <strong>Dados do cliente</strong>
        <p>Informações usadas apenas para simular o pedido visual.</p>
      </div>
      <label class="form-group"><span>Nome completo</span><input name="nome" required placeholder="Ex: Maria Clara"></label>
      <label class="form-group"><span>Contato fictício</span><input name="contato" required placeholder="(97) 90000-0000"></label>

      <div class="form-group full form-section-title">
        <strong>Recebimento</strong>
        <p>Escolha entrega ou retirada e informe data desejada.</p>
      </div>
      <label class="form-group">
        <span>Tipo de recebimento</span>
        <select name="recebimento" required>
          <option value="Entrega">Entrega</option>
          <option value="Retirada">Retirada</option>
        </select>
      </label>
      <label class="form-group"><span>Bairro</span><input name="bairro" required placeholder="Centro"></label>
      <label class="form-group full"><span>Endereço</span><input name="endereco" placeholder="Rua, número e complemento"></label>
      <label class="form-group"><span>Ponto de referência</span><input name="referencia" placeholder="Próximo a..."></label>
      <label class="form-group"><span>Data desejada</span><input name="data" type="date" required></label>
      <label class="form-group"><span>Horário desejado</span><input name="horario" type="time" required></label>

      <div class="form-group full form-section-title">
        <strong>Mensagem e observações</strong>
        <p>Campos opcionais para personalizar o atendimento.</p>
      </div>
      <label class="form-group full"><span>Mensagem para cartão</span><input name="mensagem" placeholder="Ex: Feliz aniversário, com carinho."></label>
      <label class="form-group full"><span>Observações</span><textarea name="observacoes" placeholder="Preferência de cor, embalagem, entrega ou preparo."></textarea></label>

      <div class="form-group full form-section-title">
        <strong>Forma de pagamento</strong>
        <p>O pagamento é apenas demonstrativo neste front-end.</p>
      </div>
      <label class="form-group full">
        <span>Pagamento</span>
        <select name="pagamento" id="paymentMethod" data-payment-method required>
          <option value="">Selecione a forma</option>
          <option value="Pix">Pix</option>
          <option value="Dinheiro">Dinheiro</option>
          <option value="Cartão presencial">Cartão presencial</option>
          <option value="Pagamento na retirada">Pagamento na retirada</option>
        </select>
      </label>

      <div class="pix-checkout-panel form-group full" data-pix-panel hidden>
        <div class="pix-panel-header">
          <div>
            <span class="badge">Pix demonstrativo</span>
            <h2>QR Code visual para apresentação</h2>
            <p class="muted">Este QR Code não processa pagamento real. Serve para demonstrar a experiência da futura integração.</p>
          </div>
          <span class="status status-warn" data-pix-status>Aguardando confirmação</span>
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
            <small>Código Pix copia e cola</small>
            <code data-pix-code>00020126580014BR.GOV.BCB.PIX0136arteflor-demo-checkout5204000053039865802BR5910ARTE E FLOR6005COARI62070503***6304DEMO</code>
            <div class="actions">
              <button class="btn btn-soft" type="button" data-copy-pix>Copiar Pix</button>
              <button class="btn btn-primary" type="button" data-confirm-pix>Confirmar pagamento demonstrativo</button>
            </div>
          </div>
        </div>
      </div>

      <button class="btn btn-primary form-submit" type="submit">Finalizar pedido no sistema</button>
    </form>

    <aside class="checkout-side">
      <div class="card cart-summary">
        <span class="badge">Pedido</span>
        <h2>Resumo</h2>
        <div id="checkoutSummary" class="checkout-summary-list"></div>
        <div class="summary-lines">
          <p><span>Subtotal</span><strong id="checkoutSubtotal">R$ 0,00</strong></p>
          <p><span>Desconto visual</span><strong id="checkoutDiscount">R$ 0,00</strong></p>
          <p class="summary-total"><span>Total</span><strong id="checkoutTotal">R$ 0,00</strong></p>
        </div>
      </div>

      <div class="card order-success" data-order-success hidden>
        <span class="badge badge-rose">Pedido finalizado</span>
        <h2>Pedido <strong data-order-code>#AF-0000</strong></h2>
        <p>O pedido foi salvo no sistema visual deste navegador.</p>
        <div class="actions">
          <a class="btn btn-primary" href="<?= site_url('cliente.php') ?>">Ir para área do cliente</a>
          <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Novo pedido</a>
        </div>
      </div>
    </aside>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
