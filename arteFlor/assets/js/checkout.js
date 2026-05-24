(() => {
  const form = document.getElementById('checkoutForm');
  const summary = document.getElementById('checkoutSummary');
  const subtotalEl = document.getElementById('checkoutSubtotal');
  const discountEl = document.getElementById('checkoutDiscount');
  const totalEl = document.getElementById('checkoutTotal');
  const paymentMethod = document.querySelector('[data-payment-method]');
  const pixPanel = document.querySelector('[data-pix-panel]');
  const pixStatus = document.querySelector('[data-pix-status]');
  const pixCode = document.querySelector('[data-pix-code]');
  const copyPixButton = document.querySelector('[data-copy-pix]');
  const confirmPixButton = document.querySelector('[data-confirm-pix]');
  const success = document.querySelector('[data-order-success]');
  const orderCodeEl = document.querySelector('[data-order-code]');
  const orderLink = document.querySelector('[data-order-client-link]');
  const whatsappStatus = document.querySelector('[data-whatsapp-status]');
  const submitButton = form?.querySelector('[type="submit"]');
  let pixConfirmed = false;

  if (!form || !summary || !window.ArteFlor) return;

  const renderSummary = () => {
    const cart = ArteFlor.getCart();
    const totals = ArteFlor.cartTotals(cart);

    summary.innerHTML = cart.length ? cart.map((item) => `
      <div class="checkout-summary-item">
        ${item.imagem ? `<img src="${ArteFlor.escapeHtml(item.imagem)}" alt="${ArteFlor.escapeHtml(item.nome)}">` : '<span>A&F</span>'}
        <div>
          <strong>${Number(item.qty || 1)}x ${ArteFlor.escapeHtml(item.nome)}</strong>
          <small>${ArteFlor.formatMoney(Number(item.preco || 0) * Number(item.qty || 1))}</small>
        </div>
      </div>
    `).join('') : '<div class="empty-state small"><strong>Carrinho vazio</strong><p>Adicione produtos antes de finalizar.</p></div>';

    subtotalEl.textContent = ArteFlor.formatMoney(totals.subtotal);
    discountEl.textContent = totals.discount > 0 ? `-${ArteFlor.formatMoney(totals.discount)}` : ArteFlor.formatMoney(0);
    totalEl.textContent = ArteFlor.formatMoney(totals.total);
  };

  const togglePixPanel = () => {
    const isPix = paymentMethod?.value === 'Pix';
    if (pixPanel) pixPanel.hidden = !isPix;
  };

  paymentMethod?.addEventListener('change', () => {
    pixConfirmed = false;
    if (pixStatus) {
      pixStatus.textContent = 'Aguardando confirmação';
      pixStatus.classList.remove('status-ok');
      pixStatus.classList.add('status-warn');
    }
    togglePixPanel();
  });

  copyPixButton?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(pixCode?.textContent?.trim() || '');
      ArteFlor.toast('Código Pix demonstrativo copiado.');
    } catch (error) {
      ArteFlor.toast('Não foi possível copiar automaticamente.', 'warning');
    }
  });

  confirmPixButton?.addEventListener('click', () => {
    pixConfirmed = true;
    if (pixStatus) {
      pixStatus.textContent = 'Pagamento demonstrativo confirmado';
      pixStatus.classList.remove('status-warn');
      pixStatus.classList.add('status-ok');
    }
    ArteFlor.toast('Pagamento Pix demonstrativo confirmado.');
  });

  const checkoutPayload = (data, cart) => ({
    cliente: {
      nome: data.nome,
      contato: data.contato
    },
    recebimento: data.recebimento,
    bairro: data.bairro,
    endereco: data.endereco,
    referencia: data.referencia,
    data_desejada: data.data,
    horario_desejado: data.horario,
    mensagem_cartao: data.mensagem,
    observacoes: data.observacoes,
    forma_pagamento: data.pagamento,
    itens: cart.map((item) => ({
      produto_id: Number(item.id || item.produto_id),
      quantidade: Number(item.qty || item.quantidade || 1),
      mensagem_cartao: item.mensagem || '',
      observacoes: item.observacoes || ''
    }))
  });

  const whatsappLabel = (result) => {
    const status = result?.whatsapp?.status;
    if (status === 'enviado') return 'Notificação WhatsApp enviada.';
    if (status === 'simulado') return 'Notificação WhatsApp registrada em modo simulação.';
    if (status === 'erro') return 'Pedido salvo. A notificação WhatsApp não foi enviada agora.';
    return 'Pedido salvo no sistema.';
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const cart = ArteFlor.getCart();
    if (!cart.length) {
      ArteFlor.toast('Adicione pelo menos um produto ao carrinho.', 'warning');
      return;
    }

    const data = Object.fromEntries(new FormData(form).entries());
    const endpoint = form.dataset.checkoutEndpoint || 'actions/finalizar-pedido.php';
    const originalText = submitButton?.textContent || '';

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Salvando pedido...';
    }

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(checkoutPayload(data, cart))
      });
      const result = await response.json().catch(() => ({}));

      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Não foi possível finalizar o pedido.');
      }

      ArteFlor.clearCart();
      renderSummary();

      if (orderCodeEl) orderCodeEl.textContent = result.codigo;
      if (orderLink) orderLink.href = `cliente.php?pedido=${encodeURIComponent(String(result.codigo || '').replace(/^#/, ''))}`;
      if (whatsappStatus) whatsappStatus.textContent = whatsappLabel(result);
      if (success) success.hidden = false;
      form.reset();
      pixConfirmed = false;
      togglePixPanel();
      success?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      ArteFlor.toast(`Pedido ${result.codigo} salvo no sistema.`);
    } catch (error) {
      ArteFlor.toast(error.message || 'Não foi possível finalizar o pedido.', 'error');
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
      }
    }
  });

  renderSummary();
  togglePixPanel();
})();
