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
    renderSummary();

    form?.addEventListener('submit', (event) => {
      event.preventDefault();

      const cart = ArteFlor.getCart();
      const total = cart.reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 0), 0);
      const data = Object.fromEntries(new FormData(form).entries());
      const codigo = `AF-${String(Date.now()).slice(-5)}`;

      ArteFlor.saveOrder({
        codigo,
        status: 'Pedido recebido',
        pagamento: data.pagamento,
        total,
        itensResumo: cart.length ? cart.map((item) => `${item.qty}x ${item.nome}`).join(', ') : 'Atendimento manual',
        criadoEm: new Date().toISOString()
      });

      window.open(ArteFlor.whatsappUrl(buildMessage(data, cart, total)), '_blank', 'noopener');
      ArteFlor.toast(`Pedido #${codigo} gerado para WhatsApp.`);
    });
  });
})();
