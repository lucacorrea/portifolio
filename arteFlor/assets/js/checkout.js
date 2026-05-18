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

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    const cart = ArteFlor.getCart();
    if (!cart.length) {
      ArteFlor.toast('Adicione pelo menos um produto ao carrinho.', 'warning');
      return;
    }

    const data = Object.fromEntries(new FormData(form).entries());
    const totals = ArteFlor.cartTotals(cart);
    const code = `#AF-${String(Math.floor(1000 + Math.random() * 9000))}`;
    const now = new Date();
    const paymentStatus = data.pagamento === 'Pix'
      ? (pixConfirmed ? 'Pagamento confirmado' : 'Aguardando pagamento')
      : 'Pagamento a confirmar';

    const order = {
      codigo: code,
      cliente: data.nome,
      contato: data.contato,
      recebimento: data.recebimento,
      endereco: data.endereco,
      bairro: data.bairro,
      referencia: data.referencia,
      data: data.data,
      horario: data.horario,
      mensagem: data.mensagem,
      observacoes: data.observacoes,
      pagamento: data.pagamento,
      pagamentoStatus: paymentStatus,
      status: paymentStatus === 'Pagamento confirmado' ? 'Pagamento confirmado' : 'Pedido recebido',
      origem: 'Catálogo',
      itens: cart,
      subtotal: totals.subtotal,
      desconto: totals.discount,
      total: totals.total,
      criadoEm: now.toISOString()
    };

    ArteFlor.saveOrder(order);
    ArteFlor.clearCart();
    renderSummary();

    if (orderCodeEl) orderCodeEl.textContent = code;
    if (success) success.hidden = false;
    form.reset();
    togglePixPanel();
    success?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    ArteFlor.toast(`Pedido ${code} finalizado no sistema.`);
  });

  renderSummary();
  togglePixPanel();
})();
