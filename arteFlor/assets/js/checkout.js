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
