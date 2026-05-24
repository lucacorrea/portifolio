<?php
require_once __DIR__ . '/includes/whatsapp.php';

$pageTitle = 'Checkout';
$activePage = 'catalogo';
$pageScripts = ['js/checkout.js'];
$integrationConfig = whatsapp_config();
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================
   CHECKOUT — FORMULÁRIO POR ETAPAS
   CSS interno somente para checkout.php
========================================================= */

.compact-header {
  background: linear-gradient(135deg, #edf3e9 0%, #fbf4ec 100%);
  border-bottom: 1px solid rgba(47, 72, 58, .12);
}

.compact-header .badge {
  border-radius: 10px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  color: #244836;
  box-shadow: none;
}

.checkout-layout {
  grid-template-columns: minmax(0, 1fr) minmax(330px, 390px);
  gap: 28px;
  align-items: start;
}

.checkout-form {
  display: block;
  padding: 0;
  overflow: hidden;
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: 0 14px 34px rgba(45, 55, 48, .07);
}

.checkout-step-header {
  padding: 22px 24px;
  border-bottom: 1px solid rgba(47, 72, 58, .10);
  background: #fffdf8;
}

.checkout-step-header strong {
  display: block;
  color: #244836;
  font-size: 1.15rem;
  font-weight: 950;
}

.checkout-step-header p {
  margin-top: 4px;
  color: #626b64;
  font-size: .94rem;
}

.checkout-steps {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  padding: 18px 24px;
  background: #f8f1e8;
  border-bottom: 1px solid rgba(47, 72, 58, .10);
}

.checkout-step-indicator {
  display: grid;
  gap: 7px;
  min-width: 0;
  padding: 12px;
  border-radius: 12px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .10);
  color: #626b64;
  text-align: left;
}

.checkout-step-indicator span {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: #edf3e9;
  color: #244836;
  font-size: .82rem;
  font-weight: 950;
}

.checkout-step-indicator strong {
  color: inherit;
  font-size: .82rem;
  font-weight: 900;
  line-height: 1.15;
}

.checkout-step-indicator.active {
  background: #244836;
  border-color: #244836;
  color: #fff;
}

.checkout-step-indicator.active span {
  background: #fff;
  color: #244836;
}

.checkout-step-indicator.done {
  background: #edf3e9;
  border-color: rgba(47, 72, 58, .16);
  color: #244836;
}

.checkout-step-panel {
  display: none;
  padding: 24px;
}

.checkout-step-panel.active {
  display: block;
}

.checkout-step-title {
  margin-bottom: 18px;
}

.checkout-step-title strong {
  display: block;
  color: #244836;
  font-size: 1.22rem;
  font-weight: 950;
}

.checkout-step-title p {
  margin-top: 4px;
  color: #626b64;
  font-size: .94rem;
}

.checkout-fields-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.form-group {
  display: grid;
  gap: 7px;
  color: #244836;
  font-weight: 850;
}

.form-group.full {
  grid-column: 1 / -1;
}

.form-group span {
  color: #244836;
  font-size: .9rem;
  font-weight: 900;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  min-height: 46px;
  border-radius: 10px;
  border: 1px solid rgba(47, 72, 58, .14);
  background: #fff;
  color: #2f3631;
  padding: 0 14px;
  font-weight: 700;
  outline: none;
  box-shadow: none;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.form-group textarea {
  min-height: 118px;
  padding-top: 12px;
  resize: vertical;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
  color: #8a928c;
  font-weight: 650;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: #4f8062;
  box-shadow: 0 0 0 3px rgba(79, 128, 98, .12);
}

.checkout-step-actions {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-top: 24px;
  padding-top: 18px;
  border-top: 1px solid rgba(47, 72, 58, .10);
}

.checkout-step-actions .btn {
  min-height: 46px;
  border-radius: 10px;
}

.checkout-step-actions .btn-primary {
  background: #244836;
}

.checkout-step-actions .btn-primary:hover {
  background: #173327;
  transform: none;
}

.checkout-step-actions .btn-soft,
.checkout-step-actions .btn-outline {
  background: #fff;
  border: 1px solid rgba(47, 72, 58, .14);
  color: #244836;
  box-shadow: none;
}

/* Pix dentro da etapa de pagamento */

.pix-checkout-panel {
  margin-top: 18px;
  padding: 18px;
  border-radius: 16px;
  background: #f8f1e8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: none;
}

.pix-panel-header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
  margin-bottom: 16px;
}

.pix-panel-header h2 {
  margin-top: 8px;
  color: #244836;
  font-family: var(--fonte-corpo);
  font-size: 1.35rem;
  font-weight: 950;
}

.pix-panel-grid {
  display: grid;
  grid-template-columns: 190px minmax(0, 1fr);
  gap: 18px;
  align-items: center;
}

.pix-qr-demo {
  width: min(100%, 190px);
  border-radius: 14px;
  border: 8px solid #fff;
  box-shadow: 0 10px 26px rgba(45, 55, 48, .08);
}

.pix-qr-demo strong {
  border-radius: 10px;
}

.pix-payment-box {
  display: grid;
  gap: 9px;
}

.pix-payment-box small {
  color: #626b64;
  font-size: .72rem;
  font-weight: 900;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.pix-payment-box strong {
  color: #244836;
}

.pix-payment-box code {
  display: block;
  padding: 12px;
  border-radius: 10px;
  background: #fff;
  border: 1px dashed rgba(47, 72, 58, .22);
  color: #244836;
  font-size: .8rem;
  overflow-wrap: anywhere;
}

.pix-payment-box .actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 4px;
}

.pix-payment-box .btn {
  border-radius: 10px;
}

/* Resumo lateral */

.checkout-side {
  display: grid;
  gap: 16px;
  position: sticky;
  top: 96px;
}

.cart-summary,
.order-success {
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: 0 14px 34px rgba(45, 55, 48, .07);
}

.cart-summary .badge,
.order-success .badge {
  width: fit-content;
  border-radius: 10px;
}

.cart-summary h2,
.order-success h2 {
  color: #244836;
  font-family: var(--fonte-corpo);
  font-size: 1.45rem;
  font-weight: 950;
}

.checkout-summary-list {
  display: grid;
  gap: 12px;
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

/* Responsivo */

@media (max-width: 980px) {
  .checkout-layout {
    grid-template-columns: 1fr;
  }

  .checkout-side {
    position: static;
  }
}

@media (max-width: 760px) {
  .checkout-steps {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 14px;
  }

  .checkout-step-panel,
  .checkout-step-header {
    padding: 18px;
  }

  .checkout-fields-grid {
    grid-template-columns: 1fr;
  }

  .pix-panel-header,
  .checkout-step-actions {
    flex-direction: column;
  }

  .checkout-step-actions .btn {
    width: 100%;
  }

  .pix-panel-grid {
    grid-template-columns: 1fr;
  }

  .pix-qr-demo {
    margin-inline: auto;
  }
}

@media (max-width: 460px) {
  .checkout-steps {
    grid-template-columns: 1fr;
  }
}
</style>

<section class="page-header compact-header">
  <div class="container">
    <span class="badge">Checkout</span>
    <h1 class="section-title">Finalizar pedido no sistema</h1>
    <p class="section-subtitle">O pedido será salvo no banco, com estoque baixado automaticamente e pagamento manual acompanhado pelo painel.</p>
  </div>
</section>

<section class="section">
  <div class="container checkout-layout">
    <form class="card checkout-form" id="checkoutForm" data-checkout-endpoint="<?= site_url('actions/finalizar-pedido.php') ?>">
      <div class="checkout-step-header">
        <strong>Checkout por etapas</strong>
        <p>Preencha os dados em sequência para registrar o pedido oficial da loja.</p>
      </div>

      <div class="checkout-steps" aria-label="Etapas do checkout">
        <button class="checkout-step-indicator active" type="button" data-step-trigger="0">
          <span>1</span>
          <strong>Cliente</strong>
        </button>

        <button class="checkout-step-indicator" type="button" data-step-trigger="1">
          <span>2</span>
          <strong>Recebimento</strong>
        </button>

        <button class="checkout-step-indicator" type="button" data-step-trigger="2">
          <span>3</span>
          <strong>Mensagem</strong>
        </button>

        <button class="checkout-step-indicator" type="button" data-step-trigger="3">
          <span>4</span>
          <strong>Pagamento</strong>
        </button>
      </div>

      <!-- Etapa 1 -->
      <section class="checkout-step-panel active" data-checkout-step="0">
        <div class="checkout-step-title">
          <strong>Dados do cliente</strong>
          <p>Informe quem está fazendo o pedido para acompanhamento pela área do cliente.</p>
        </div>

        <div class="checkout-fields-grid">
          <label class="form-group">
            <span>Nome completo</span>
            <input name="nome" required placeholder="Ex: Maria Clara">
          </label>

          <label class="form-group">
            <span>Contato/WhatsApp</span>
            <input name="contato" required placeholder="(97) 90000-0000">
          </label>
        </div>

        <div class="checkout-step-actions">
          <a class="btn btn-soft" href="<?= site_url('carrinho.php') ?>">Voltar ao carrinho</a>
          <button class="btn btn-primary" type="button" data-step-next>Continuar</button>
        </div>
      </section>

      <!-- Etapa 2 -->
      <section class="checkout-step-panel" data-checkout-step="1">
        <div class="checkout-step-title">
          <strong>Recebimento</strong>
          <p>Escolha se o pedido será entregue ou retirado e informe os detalhes necessários.</p>
        </div>

        <div class="checkout-fields-grid">
          <label class="form-group">
            <span>Tipo de recebimento</span>
            <select name="recebimento" required>
              <option value="Entrega">Entrega</option>
              <option value="Retirada">Retirada</option>
            </select>
          </label>

          <label class="form-group">
            <span>Bairro</span>
            <input name="bairro" required placeholder="Centro">
          </label>

          <label class="form-group full">
            <span>Endereço</span>
            <input name="endereco" placeholder="Rua, número e complemento">
          </label>

          <label class="form-group">
            <span>Ponto de referência</span>
            <input name="referencia" placeholder="Próximo a...">
          </label>

          <label class="form-group">
            <span>Data desejada</span>
            <input name="data" type="date" required>
          </label>

          <label class="form-group">
            <span>Horário desejado</span>
            <input name="horario" type="time" required>
          </label>
        </div>

        <div class="checkout-step-actions">
          <button class="btn btn-soft" type="button" data-step-prev>Voltar</button>
          <button class="btn btn-primary" type="button" data-step-next>Continuar</button>
        </div>
      </section>

      <!-- Etapa 3 -->
      <section class="checkout-step-panel" data-checkout-step="2">
        <div class="checkout-step-title">
          <strong>Mensagem e observações</strong>
          <p>Personalize o cartão, embalagem, cores ou preferências do preparo.</p>
        </div>

        <div class="checkout-fields-grid">
          <label class="form-group full">
            <span>Mensagem para cartão</span>
            <input name="mensagem" placeholder="Ex: Feliz aniversário, com carinho.">
          </label>

          <label class="form-group full">
            <span>Observações</span>
            <textarea name="observacoes" placeholder="Preferência de cor, embalagem, entrega ou preparo."></textarea>
          </label>
        </div>

        <div class="checkout-step-actions">
          <button class="btn btn-soft" type="button" data-step-prev>Voltar</button>
          <button class="btn btn-primary" type="button" data-step-next>Continuar</button>
        </div>
      </section>

      <!-- Etapa 4 -->
      <section class="checkout-step-panel" data-checkout-step="3">
        <div class="checkout-step-title">
          <strong>Forma de pagamento</strong>
          <p>Selecione a forma de pagamento. Pix segue manual, com confirmação pelo painel administrativo.</p>
        </div>

        <div class="checkout-fields-grid">
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
                <strong><?= e((string) $integrationConfig['pix_key']) ?></strong>

                <small>Código Pix copia e cola</small>
                <code data-pix-code>00020126580014BR.GOV.BCB.PIX0136arteflor-demo-checkout5204000053039865802BR5910ARTE E FLOR6005COARI62070503***6304DEMO</code>

                <div class="actions">
                  <button class="btn btn-soft" type="button" data-copy-pix>Copiar Pix</button>
                  <button class="btn btn-primary" type="button" data-confirm-pix>Confirmar pagamento demonstrativo</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="checkout-step-actions">
          <button class="btn btn-soft" type="button" data-step-prev>Voltar</button>
          <button class="btn btn-primary form-submit" type="submit">Finalizar pedido no sistema</button>
        </div>
      </section>
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
        <p data-order-success-message>O pedido foi salvo no banco e já pode ser acompanhado pela área do cliente.</p>
        <p class="muted" data-whatsapp-status></p>

        <div class="actions">
          <a class="btn btn-primary" href="<?= site_url('cliente.php') ?>" data-order-client-link>Ir para área do cliente</a>
          <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Novo pedido</a>
        </div>
      </div>
    </aside>
  </div>
</section>

<script>
(() => {
  const form = document.getElementById('checkoutForm');
  const panels = [...document.querySelectorAll('[data-checkout-step]')];
  const triggers = [...document.querySelectorAll('[data-step-trigger]')];
  const nextButtons = [...document.querySelectorAll('[data-step-next]')];
  const prevButtons = [...document.querySelectorAll('[data-step-prev]')];
  const paymentMethod = document.querySelector('[data-payment-method]');
  const pixPanel = document.querySelector('[data-pix-panel]');
  const dateInput = form?.querySelector('input[name="data"]');

  let currentStep = 0;

  if (dateInput && !dateInput.min) {
    dateInput.min = new Date().toISOString().split('T')[0];
  }

  function setStep(index) {
    currentStep = Math.max(0, Math.min(index, panels.length - 1));

    panels.forEach((panel, panelIndex) => {
      panel.classList.toggle('active', panelIndex === currentStep);
    });

    triggers.forEach((trigger, triggerIndex) => {
      trigger.classList.toggle('active', triggerIndex === currentStep);
      trigger.classList.toggle('done', triggerIndex < currentStep);
    });

    const activePanel = panels[currentStep];
    const firstField = activePanel?.querySelector('input, select, textarea');

    if (firstField) {
      setTimeout(() => firstField.focus({ preventScroll: true }), 80);
    }
  }

  function validateCurrentStep() {
    const activePanel = panels[currentStep];
    const fields = [...activePanel.querySelectorAll('input, select, textarea')];

    for (const field of fields) {
      if (!field.checkValidity()) {
        field.reportValidity();
        return false;
      }
    }

    return true;
  }

  triggers.forEach((trigger, index) => {
    trigger.addEventListener('click', () => {
      if (index <= currentStep) {
        setStep(index);
        return;
      }

      if (validateCurrentStep()) {
        setStep(index);
      }
    });
  });

  nextButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (validateCurrentStep()) {
        setStep(currentStep + 1);
      }
    });
  });

  prevButtons.forEach((button) => {
    button.addEventListener('click', () => {
      setStep(currentStep - 1);
    });
  });

  paymentMethod?.addEventListener('change', () => {
    if (!pixPanel) return;
    pixPanel.hidden = paymentMethod.value !== 'Pix';
  });

  form?.addEventListener('submit', (event) => {
    const allFields = [...form.querySelectorAll('input, select, textarea')];

    for (const field of allFields) {
      if (!field.checkValidity()) {
        event.preventDefault();

        const panel = field.closest('[data-checkout-step]');
        const index = panels.indexOf(panel);

        if (index >= 0) {
          setStep(index);
        }

        setTimeout(() => field.reportValidity(), 120);
        return;
      }
    }
  });

  setStep(0);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
