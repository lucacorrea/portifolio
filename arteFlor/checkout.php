<?php
$pageTitle = 'Checkout';
$activePage = 'catalogo';
$pageScripts = ['js/checkout.js'];
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container">
    <span class="badge">Checkout demonstrativo</span>
    <h1 class="section-title">Finalizar pedido</h1>
    <p class="section-subtitle">Uma simulação completa para apresentar a jornada de compra: dados do cliente, entrega, pagamento Pix visual, resumo e envio pelo WhatsApp.</p>
  </div>
</section>

<section class="section">
  <div class="container checkout-layout">
    <form class="card form-grid" id="checkoutForm">
      <div class="checkout-intro full">
        <span class="eyebrow">Etapas do pedido</span>
        <div class="checkout-step-list" aria-label="Etapas do checkout">
          <span><strong>1</strong> Dados</span>
          <span><strong>2</strong> Entrega</span>
          <span><strong>3</strong> Pagamento</span>
          <span><strong>4</strong> WhatsApp</span>
        </div>
      </div>

      <div class="checkout-section-heading full">
        <h2>Dados de quem compra</h2>
        <p>Informações usadas para montar a mensagem do pedido e facilitar o atendimento.</p>
      </div>

      <label class="form-group">
        <span>Nome completo</span>
        <input name="nome" autocomplete="name" required>
      </label>
      <label class="form-group">
        <span>WhatsApp</span>
        <input name="whatsapp" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" required>
      </label>
      <label class="form-group full">
        <span>Endereço</span>
        <input name="endereco" autocomplete="street-address" required>
      </label>

      <div class="checkout-section-heading full">
        <h2>Entrega ou retirada</h2>
        <p>O cliente escolhe quando deseja receber e informa detalhes para evitar retrabalho no atendimento.</p>
      </div>

      <label class="form-group">
        <span>Bairro</span>
        <input name="bairro" required>
      </label>
      <label class="form-group">
        <span>Ponto de referência</span>
        <input name="referencia">
      </label>
      <label class="form-group">
        <span>Tipo de recebimento</span>
        <select name="recebimento" required>
          <option>Entrega</option>
          <option>Retirada</option>
        </select>
      </label>
      <label class="form-group">
        <span>Data desejada</span>
        <input name="data" type="date" required>
      </label>
      <label class="form-group">
        <span>Horário desejado</span>
        <input name="horario" type="time" required>
      </label>

      <div class="checkout-section-heading full">
        <h2>Pagamento</h2>
        <p>O Pix abre uma simulação de QR Code. As demais formas ficam para combinar no atendimento.</p>
      </div>

      <div class="form-group full">
        <span>Forma de pagamento</span>
        <div class="payment-options">
          <label class="payment-option">
            <input type="radio" name="pagamento" value="Pix" data-payment-method required>
            <span><strong>Pix com QR Code</strong><small>Exibe código demonstrativo e finalização simulada.</small></span>
          </label>
          <label class="payment-option">
            <input type="radio" name="pagamento" value="Presencial" data-payment-method>
            <span><strong>Presencial</strong><small>Pagamento combinado na loja ou retirada.</small></span>
          </label>
          <label class="payment-option">
            <input type="radio" name="pagamento" value="Dinheiro" data-payment-method>
            <span><strong>Dinheiro</strong><small>Informar troco nas observações do pedido.</small></span>
          </label>
          <label class="payment-option">
            <input type="radio" name="pagamento" value="Cartão na entrega" data-payment-method>
            <span><strong>Cartão na entrega</strong><small>Simulação para maquininha no recebimento.</small></span>
          </label>
        </div>
      </div>

      <div class="pix-checkout-panel form-group full" data-pix-panel hidden>
        <div class="pix-panel-header">
          <div>
            <span class="badge">Pix demonstrativo</span>
            <h2>Pagamento via Pix</h2>
            <p class="muted">O QR Code e o código copia e cola são fictícios, criados somente para aprovação visual da cliente.</p>
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
            <p class="muted" data-system-result>Ao finalizar no sistema, a venda fica marcada como paga apenas nesta demonstração.</p>
          </div>
        </div>
      </div>

      <div class="checkout-section-heading full">
        <h2>Mensagem do presente</h2>
        <p>Campo útil para cartões, preferências de flores e instruções especiais.</p>
      </div>

      <label class="form-group full">
        <span>Mensagem para cartão</span>
        <textarea name="cartao" placeholder="Mensagem que acompanha o presente"></textarea>
      </label>
      <label class="form-group full">
        <span>Observações</span>
        <textarea name="observacoes" placeholder="Preferência de flores, cores, embalagem ou instruções de entrega"></textarea>
      </label>
      <button class="btn btn-primary" type="submit">Enviar pedido pelo WhatsApp</button>
    </form>

    <aside class="card checkout-summary">
      <span class="eyebrow">Resumo</span>
      <h2>Seu pedido</h2>
      <div id="checkoutSummary"></div>
      <div class="summary-line"><span>Subtotal</span><strong id="checkoutSubtotal">R$ 0,00</strong></div>
      <div class="summary-line"><span>Entrega</span><strong>A combinar</strong></div>
      <div class="summary-line"><span>Total</span><strong class="price" id="checkoutTotal">R$ 0,00</strong></div>
      <button class="btn btn-soft checkout-demo-button" type="button" data-load-demo-order>Simular pedido de apresentação</button>
      <div class="checkout-next-step">
        <strong>Próximo passo</strong>
        <p>Depois de preencher o formulário, o sistema monta uma mensagem organizada para o WhatsApp da loja.</p>
      </div>
      <p class="muted">Demonstração sem banco de dados, login real ou pagamento real.</p>
    </aside>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
