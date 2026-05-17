document.addEventListener('DOMContentLoaded', () => {
  if (!window.ArteFlor) {
    return;
  }

  const summary = document.getElementById('checkoutSummary');
  const totalEl = document.getElementById('checkoutTotal');
  const form = document.getElementById('checkoutForm');
  const paymentMethod = document.querySelector('[data-payment-method]');
  const pixPanel = document.querySelector('[data-pix-panel]');
  const pixStatus = document.querySelector('[data-pix-status]');
  const pixCode = document.querySelector('[data-pix-code]');
  const copyPixButton = document.querySelector('[data-copy-pix]');
  const finishSystemButton = document.querySelector('[data-system-finish]');
  const systemResult = document.querySelector('[data-system-result]');

  if (!summary || !totalEl || !form) {
    return;
  }

  const cartCheckout = ArteFlor.getCart();
  const totalCheckout = cartCheckout.reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 0), 0);
  let systemFinished = false;
  let systemOrderCode = '';

  summary.innerHTML = cartCheckout.length
    ? cartCheckout.map((item) => `<p>${item.qty}x ${item.nome} - ${ArteFlor.formatMoney(Number(item.preco || 0) * Number(item.qty || 0))}</p>`).join('')
    : '<p class="muted">Carrinho vazio.</p>';
  totalEl.textContent = ArteFlor.formatMoney(totalCheckout);

  const togglePixPanel = () => {
    const isPix = paymentMethod?.value === 'Pix';
    if (pixPanel) {
      pixPanel.hidden = !isPix;
    }
  };

  const saveDemoSale = () => {
    systemOrderCode = `AF-${Date.now().toString().slice(-6)}`;
    systemFinished = true;

    const sales = JSON.parse(localStorage.getItem('arteflor_demo_sales') || '[]');
    sales.unshift({
      codigo: systemOrderCode,
      total: totalCheckout,
      pagamento: paymentMethod?.value || 'Pix',
      status: 'Venda finalizada no sistema',
      criadoEm: new Date().toISOString(),
      itens: cartCheckout
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

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    const data = Object.fromEntries(new FormData(form).entries());
    const itens = cartCheckout.map((item) => `- ${item.qty}x ${item.nome} (${ArteFlor.formatMoney(Number(item.preco || 0) * Number(item.qty || 0))})`).join('\n');
    const pixInfo = data.pagamento === 'Pix'
      ? `\nStatus Pix demonstrativo: ${systemFinished ? `finalizado no sistema (${systemOrderCode})` : 'QR Code exibido, confirmação pendente'}`
      : '';
    const message = `Olá, vim pelo catálogo da Arte&Flor.\n\nPedido:\n${itens || 'Sem itens no carrinho'}\n\nTotal: ${ArteFlor.formatMoney(totalCheckout)}\n\nCliente: ${data.nome}\nWhatsApp: ${data.whatsapp}\nRecebimento: ${data.recebimento}\nBairro: ${data.bairro}\nEndereço: ${data.endereco}\nPagamento: ${data.pagamento}${pixInfo}\nObservações: ${data.observacoes || 'Nenhuma'}`;

    window.open(`https://wa.me/5597000000000?text=${encodeURIComponent(message)}`, '_blank');
  });
});
