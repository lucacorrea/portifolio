<?php
$pageTitle = 'Checkout';
$activePage = 'catalogo';
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero checkout-hero">
  <div class="container">
    <span class="badge">Checkout demonstrativo</span>
    <h1 class="section-title">Finalizar pedido</h1>
    <p class="section-subtitle">Uma experiência de compra organizada para a cliente visualizar o fluxo completo: pedido, entrega, pagamento, Pix demonstrativo e envio pelo WhatsApp.</p>
  </div>
</section>

<section class="section">
  <div class="container checkout-layout">
    <form class="card checkout-form-card" id="checkoutForm">
      <div class="checkout-intro">
        <div>
          <span class="eyebrow">Pedido Arte&Flor</span>
          <h2>Dados para atendimento</h2>
          <p>Os campos abaixo montam uma mensagem pronta para a loja confirmar disponibilidade, entrega e pagamento.</p>
        </div>
        <div class="checkout-step-list" aria-label="Etapas do checkout">
          <span class="is-active"><strong>1</strong> Cliente</span>
          <span><strong>2</strong> Entrega</span>
          <span><strong>3</strong> Pagamento</span>
          <span><strong>4</strong> Envio</span>
        </div>
      </div>

      <fieldset class="checkout-block">
        <legend><span>01</span> Cliente</legend>
        <div class="form-grid">
          <label class="form-group">
            <span>Nome completo</span>
            <input name="nome" autocomplete="name" placeholder="Ex: Maria Clara" required>
          </label>
          <label class="form-group">
            <span>WhatsApp</span>
            <input name="whatsapp" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" required>
          </label>
        </div>
      </fieldset>

      <fieldset class="checkout-block">
        <legend><span>02</span> Entrega</legend>
        <div class="form-grid">
          <label class="form-group full">
            <span>Endereço</span>
            <input name="endereco" autocomplete="street-address" placeholder="Rua, número e complemento" required>
          </label>
          <label class="form-group">
            <span>Bairro</span>
            <input name="bairro" required>
          </label>
          <label class="form-group">
            <span>Ponto de referência</span>
            <input name="referencia" placeholder="Opcional">
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
        </div>
      </fieldset>

      <fieldset class="checkout-block">
        <legend><span>03</span> Pagamento</legend>
        <p class="checkout-block-note">Selecione uma forma. O Pix abre uma prévia com QR Code e finalização demonstrativa.</p>
        <div class="payment-options">
          <label class="payment-option payment-option-featured">
            <input type="radio" name="pagamento" value="Pix" data-payment-method required>
            <span><strong>Pix com QR Code</strong><small>Código copia e cola, status visual e venda simulada.</small></span>
          </label>
          <label class="payment-option">
            <input type="radio" name="pagamento" value="Presencial" data-payment-method>
            <span><strong>Presencial</strong><small>Para pagar na retirada ou diretamente com a loja.</small></span>
          </label>
          <label class="payment-option">
            <input type="radio" name="pagamento" value="Dinheiro" data-payment-method>
            <span><strong>Dinheiro</strong><small>O cliente pode informar troco nas observações.</small></span>
          </label>
          <label class="payment-option">
            <input type="radio" name="pagamento" value="Cartão na entrega" data-payment-method>
            <span><strong>Cartão na entrega</strong><small>Simulação para pagamento na maquininha.</small></span>
          </label>
        </div>
      </fieldset>

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

      <fieldset class="checkout-block">
        <legend><span>04</span> Mensagem e observações</legend>
        <div class="form-grid">
          <label class="form-group full">
            <span>Mensagem para cartão</span>
            <textarea name="cartao" placeholder="Mensagem que acompanha o presente"></textarea>
          </label>
          <label class="form-group full">
            <span>Observações</span>
            <textarea name="observacoes" placeholder="Preferência de flores, cores, embalagem, troco ou instruções de entrega"></textarea>
          </label>
        </div>
      </fieldset>

      <div class="checkout-submit-row">
        <button class="btn btn-primary" type="submit">Enviar pedido pelo WhatsApp</button>
        <p>O envio abre o WhatsApp com uma mensagem pronta. Nenhuma cobrança real é feita.</p>
      </div>
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
<script>
(function () {
  const demoItems = [
    {
      id: 1001,
      nome: 'Buquê Tons Pastel',
      preco: 119.9,
      qty: 1,
      imagem: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=500&q=80'
    },
    {
      id: 1002,
      nome: 'Cartão personalizado',
      preco: 12,
      qty: 1,
      imagem: 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=500&q=80'
    }
  ];

  const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const getCartTotal = (cart) => cart.reduce((sum, item) => (
    sum + Number(item.preco || 0) * Number(item.qty || 0)
  ), 0);

  const getPaymentValue = (form) => {
    const data = new FormData(form);
    return data.get('pagamento') || '';
  };

  const renderSummary = () => {
    const summary = document.getElementById('checkoutSummary');
    const subtotal = document.getElementById('checkoutSubtotal');
    const total = document.getElementById('checkoutTotal');

    if (!summary || !total || !window.ArteFlor) {
      return;
    }

    const cart = ArteFlor.getCart();
    const value = getCartTotal(cart);

    if (!cart.length) {
      summary.innerHTML = `
        <div class="empty-state checkout-empty-state">
          <strong>Nenhum produto no carrinho.</strong>
          <p>Use a simulação para apresentar a experiência completa sem depender de um carrinho real.</p>
        </div>
      `;
    } else {
      summary.innerHTML = cart.map((item) => `
        <div class="checkout-summary-item">
          ${item.imagem ? `<img src="${escapeHtml(item.imagem)}" alt="Imagem de ${escapeHtml(item.nome)}" loading="lazy">` : ''}
          <div>
            <strong>${escapeHtml(item.nome)}</strong>
            <span>${Number(item.qty || 0)} un. · ${ArteFlor.money(Number(item.preco || 0))}</span>
          </div>
          <b>${ArteFlor.money(Number(item.preco || 0) * Number(item.qty || 0))}</b>
        </div>
      `).join('');
    }

    if (subtotal) {
      subtotal.textContent = ArteFlor.money(value);
    }
    total.textContent = ArteFlor.money(value);
  };

  const buildMessage = (data, cart, total, pixStatusText) => {
    const items = cart.length
      ? cart.map((item) => `- ${item.qty}x ${item.nome} (${ArteFlor.money(Number(item.preco || 0) * Number(item.qty || 0))})`).join('\n')
      : '- Pedido personalizado sem itens no carrinho.';

    return [
      'Olá, vim pelo site da Arte&Flor.',
      '',
      'Pedido:',
      items,
      '',
      `Total demonstrativo: ${ArteFlor.money(total)}`,
      '',
      `Cliente: ${data.nome || '-'}`,
      `WhatsApp: ${data.whatsapp || '-'}`,
      `Recebimento: ${data.recebimento || '-'}`,
      `Data desejada: ${data.data || '-'}`,
      `Horário desejado: ${data.horario || '-'}`,
      `Bairro: ${data.bairro || '-'}`,
      `Endereço: ${data.endereco || '-'}`,
      `Referência: ${data.referencia || '-'}`,
      `Pagamento: ${data.pagamento || '-'}`,
      pixStatusText ? `Status Pix demonstrativo: ${pixStatusText}` : '',
      `Mensagem para cartão: ${data.cartao || 'Nenhuma'}`,
      `Observações: ${data.observacoes || 'Nenhuma'}`
    ].filter(Boolean).join('\n');
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (!window.ArteFlor) {
      return;
    }

    const form = document.getElementById('checkoutForm');
    const paymentMethods = document.querySelectorAll('[data-payment-method]');
    const pixPanel = document.querySelector('[data-pix-panel]');
    const pixStatus = document.querySelector('[data-pix-status]');
    const pixCode = document.querySelector('[data-pix-code]');
    const copyPixButton = document.querySelector('[data-copy-pix]');
    const finishSystemButton = document.querySelector('[data-system-finish]');
    const systemResult = document.querySelector('[data-system-result]');
    const loadDemoButton = document.querySelector('[data-load-demo-order]');
    let systemFinished = false;
    let systemOrderCode = '';

    renderSummary();

    const togglePixPanel = () => {
      const isPix = form ? getPaymentValue(form) === 'Pix' : false;
      if (pixPanel) {
        pixPanel.hidden = !isPix;
      }

      if (!isPix && pixStatus) {
        pixStatus.textContent = 'Aguardando pagamento';
      }
    };

    const saveDemoSale = () => {
      const cart = ArteFlor.getCart();
      const total = getCartTotal(cart);
      const sales = JSON.parse(localStorage.getItem('arteflor_demo_sales') || '[]');

      systemOrderCode = `AF-${String(Date.now()).slice(-6)}`;
      systemFinished = true;

      sales.unshift({
        codigo: systemOrderCode,
        status: 'Venda finalizada no sistema',
        pagamento: 'Pix',
        total,
        itens: cart,
        criadoEm: new Date().toISOString()
      });
      localStorage.setItem('arteflor_demo_sales', JSON.stringify(sales.slice(0, 10)));

      if (pixStatus) {
        pixStatus.textContent = 'Pagamento confirmado';
      }
      if (systemResult) {
        systemResult.textContent = `Venda ${systemOrderCode} finalizada no sistema demonstrativo. Nenhum pagamento real foi processado.`;
      }
      if (finishSystemButton) {
        finishSystemButton.textContent = 'Venda finalizada';
        finishSystemButton.disabled = true;
      }
      ArteFlor.toast(`Venda ${systemOrderCode} finalizada no sistema demonstrativo.`);
    };

    paymentMethods.forEach((input) => {
      input.addEventListener('change', togglePixPanel);
    });
    togglePixPanel();

    loadDemoButton?.addEventListener('click', () => {
      ArteFlor.setCart(demoItems);
      ArteFlor.updateCartCount();
      renderSummary();
      ArteFlor.toast('Pedido de apresentação carregado no resumo.');
    });

    copyPixButton?.addEventListener('click', async () => {
      const code = pixCode?.textContent?.trim() || '';

      try {
        await navigator.clipboard.writeText(code);
        if (systemResult) {
          systemResult.textContent = 'Código Pix demonstrativo copiado.';
        }
      } catch (error) {
        if (systemResult) {
          systemResult.textContent = 'Não foi possível copiar automaticamente. Selecione e copie o código manualmente.';
        }
      }
    });

    finishSystemButton?.addEventListener('click', saveDemoSale);

    form?.addEventListener('submit', (event) => {
      event.preventDefault();

      const cart = ArteFlor.getCart();
      const total = getCartTotal(cart);
      const data = Object.fromEntries(new FormData(form).entries());
      const codigo = `AF-${String(Date.now()).slice(-5)}`;
      const pixStatusText = data.pagamento === 'Pix'
        ? (systemFinished ? `finalizado no sistema (${systemOrderCode})` : 'QR Code exibido, confirmação pendente')
        : '';

      ArteFlor.saveOrder({
        codigo,
        status: 'Pedido recebido',
        pagamento: data.pagamento,
        total,
        itensResumo: cart.length ? cart.map((item) => `${item.qty}x ${item.nome}`).join(', ') : 'Atendimento manual',
        criadoEm: new Date().toISOString()
      });

      window.open(ArteFlor.whatsappUrl(buildMessage(data, cart, total, pixStatusText)), '_blank', 'noopener');
      ArteFlor.toast(`Pedido #${codigo} gerado para WhatsApp.`);
    });
  });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
