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
  const success = document.querySelector('[data-order-success]');
  const orderCodeEl = document.querySelector('[data-order-code]');
  const orderLink = document.querySelector('[data-order-client-link]');
  const whatsappStatus = document.querySelector('[data-whatsapp-status]');
  const submitButton = form?.querySelector('[type="submit"]');

  if (!form || !summary || !window.ArteFlor) return;

  const toNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  };

  const validCartItem = (item) => {
    const productId = toNumber(item?.id || item?.produto_id);
    const quantity = toNumber(item?.qty || item?.quantidade, 1);
    return productId > 0 && quantity > 0;
  };

  const normalizedCart = () => {
    const cart = ArteFlor.getCart();
    const valid = cart.filter(validCartItem).map((item) => ({
      ...item,
      id: String(item.id || item.produto_id),
      produto_id: String(item.produto_id || item.id),
      cartKey: String(item.cartKey || (item.cor_id ? `${item.id || item.produto_id}:cor:${item.cor_id}` : item.id || item.produto_id)),
      qty: Math.max(1, toNumber(item.qty || item.quantidade, 1))
    }));

    if (valid.length !== cart.length) {
      ArteFlor.setCart(valid);
      ArteFlor.toast('Itens inválidos foram removidos antes do checkout.', 'info');
    }

    return valid;
  };

  const renderSummary = () => {
    const cart = normalizedCart();
    const totals = ArteFlor.cartTotals(cart);

    summary.innerHTML = cart.length ? cart.map((item) => `
      <div class="checkout-summary-item">
        ${item.imagem ? `<img src="${ArteFlor.escapeHtml(item.imagem)}" alt="${ArteFlor.escapeHtml(item.nome)}">` : '<span>A&F</span>'}
        <div>
          <strong>${Number(item.qty || 1)}x ${ArteFlor.escapeHtml(item.nome)}</strong>
          ${item.cor_nome ? `<small class="checkout-color-line"><i class="checkout-color-dot" style="--color: ${ArteFlor.escapeHtml(item.cor_hex || '#FFFFFF')}"></i>Cor: ${ArteFlor.escapeHtml(item.cor_nome)}</small>` : ''}
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
    if (pixStatus) {
      pixStatus.textContent = 'Pendente no painel';
      pixStatus.classList.remove('status-ok');
      pixStatus.classList.add('status-warn');
    }
    togglePixPanel();
  });

  copyPixButton?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(pixCode?.textContent?.trim() || '');
      ArteFlor.toast('Chave Pix copiada.');
    } catch (error) {
      ArteFlor.toast('Não foi possível copiar automaticamente.', 'warning');
    }
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
      produto_id: Number(item.produto_id || item.id),
      produto_cor_id: Number(item.cor_id || item.produto_cor_id || 0) || null,
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

    const cart = normalizedCart();
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
