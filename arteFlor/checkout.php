<?php
$pageTitle = 'Checkout';
$activePage = 'catalogo';
$pageScripts = ['js/checkout.js'];
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container">
    <span class="badge">Pedido por WhatsApp</span>
    <h1 class="section-title">Finalizar pedido</h1>
    <p class="section-subtitle">Preencha os dados para gerar uma mensagem organizada. Não há pagamento, Pix ou integração real nesta etapa.</p>
  </div>
</section>

<section class="section">
  <div class="container checkout-layout">
    <form class="card form-grid" id="checkoutForm">
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
      <label class="form-group">
        <span>Forma de pagamento</span>
        <select name="pagamento" required>
          <option>Pix</option>
          <option>Presencial</option>
          <option>Dinheiro</option>
          <option>Cartão na entrega</option>
        </select>
      </label>
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
      <h2>Resumo do pedido</h2>
      <div id="checkoutSummary"></div>
      <div class="summary-line"><span>Total</span><strong class="price" id="checkoutTotal">R$ 0,00</strong></div>
      <p class="muted">Ao enviar, um pedido demonstrativo também será salvo no localStorage para aparecer na área do cliente.</p>
    </aside>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
