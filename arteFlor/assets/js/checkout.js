(function () {
  const renderSummary = () => {
    const summary = document.getElementById('checkoutSummary');
    const total = document.getElementById('checkoutTotal');

    if (!summary || !total || !window.ArteFlor) {
      return;
    }

    const cart = ArteFlor.getCart();
    const value = cart.reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 0), 0);

    if (!cart.length) {
      summary.innerHTML = '<div class="empty-state">Carrinho vazio. Você ainda pode enviar uma solicitação manual pelo WhatsApp.</div>';
    } else {
      summary.innerHTML = cart.map((item) => `
        <div class="summary-line">
          <span>${item.qty}x ${item.nome}</span>
          <strong>${ArteFlor.money(Number(item.preco || 0) * Number(item.qty || 0))}</strong>
        </div>
      `).join('');
    }

    total.textContent = ArteFlor.money(value);
  };

  const buildMessage = (data, cart, total) => {
    const items = cart.length
      ? cart.map((item) => `- ${item.qty}x ${item.nome} (${ArteFlor.money(Number(item.preco || 0) * Number(item.qty || 0))})`).join('\n')
      : '- Pedido sem itens no carrinho. Cliente deseja atendimento manual.';

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
      `Mensagem para cartão: ${data.cartao || 'Nenhuma'}`,
      `Observações: ${data.observacoes || 'Nenhuma'}`
    ].join('\n');
  };

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('checkoutForm');
    const paymentMethod = document.querySelector('[data-payment-method]');
    const pixPanel = document.querySelector('[data-pix-panel]');
    const pixStatus = document.querySelector('[data-pix-status]');
    const pixCode = document.querySelector('[data-pix-code]');
    const copyPixButton = document.querySelector('[data-copy-pix]');
    const finishSystemButton = document.querySelector('[data-system-finish]');
    const systemResult = document.querySelector('[data-system-result]');
    let systemFinished = false;
    let systemOrderCode = '';

    renderSummary();

    const togglePixPanel = () => {
      const isPix = paymentMethod?.value === 'Pix';
      if (pixPanel) {
        pixPanel.hidden = !isPix;
      }
    };

    const saveDemoSale = () => {
      const cart = ArteFlor.getCart();
      const total = cart.reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 0), 0);
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

    paymentMethod?.addEventListener('change', togglePixPanel);
    togglePixPanel();

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
      const total = cart.reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 0), 0);
      const data = Object.fromEntries(new FormData(form).entries());
      const codigo = `AF-${String(Date.now()).slice(-5)}`;
      const pixInfo = data.pagamento === 'Pix'
        ? `\nStatus Pix demonstrativo: ${systemFinished ? `finalizado no sistema (${systemOrderCode})` : 'QR Code exibido, confirmação pendente'}`
        : '';

      ArteFlor.saveOrder({
        codigo,
        status: 'Pedido recebido',
        pagamento: data.pagamento,
        total,
        itensResumo: cart.length ? cart.map((item) => `${item.qty}x ${item.nome}`).join(', ') : 'Atendimento manual',
        criadoEm: new Date().toISOString()
      });

      window.open(ArteFlor.whatsappUrl(`${buildMessage(data, cart, total)}${pixInfo}`), '_blank', 'noopener');
      ArteFlor.toast(`Pedido #${codigo} gerado para WhatsApp.`);
    });
  });
})();
