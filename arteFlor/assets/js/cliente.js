(() => {
  const root = document.querySelector('[data-client-orders]');
  const search = document.querySelector('[data-order-search]');
  const empty = document.querySelector('[data-client-empty]');
  if (!root || !window.ArteFlor) return;

  const timeline = [
    'Pedido recebido',
    'Aguardando pagamento',
    'Pagamento confirmado',
    'Em preparo',
    'Saiu para entrega',
    'Finalizado'
  ];

  const demoOrders = [
    {
      codigo: '#AF-1024',
      cliente: 'Ana Beatriz',
      pagamento: 'Pix',
      pagamentoStatus: 'Aguardando pagamento',
      recebimento: 'Entrega',
      bairro: 'Centro',
      total: 189.9,
      status: 'Aguardando pagamento',
      origem: 'Catálogo',
      itens: [{ nome: 'Arranjo Floral Premium', qty: 1, preco: 189.9 }]
    },
    {
      codigo: '#AF-1025',
      cliente: 'Maria Clara',
      pagamento: 'Cartão presencial',
      pagamentoStatus: 'Pagamento confirmado',
      recebimento: 'Retirada',
      bairro: 'Tauá Mirim',
      total: 119.9,
      status: 'Em preparo',
      origem: 'PDV',
      itens: [{ nome: 'Buquê Jardim Pastel', qty: 1, preco: 119.9 }]
    },
    {
      codigo: '#AF-1021',
      cliente: 'João Pedro',
      pagamento: 'Dinheiro',
      pagamentoStatus: 'Pagamento confirmado',
      recebimento: 'Entrega',
      bairro: 'União',
      total: 59.9,
      status: 'Finalizado',
      origem: 'Atendimento',
      itens: [{ nome: 'Mini Buquê Delicado', qty: 1, preco: 59.9 }]
    }
  ];

  const statusIndex = (status) => {
    const index = timeline.indexOf(status);
    if (index >= 0) return index;
    if (status === 'Pedido recebido') return 0;
    return 0;
  };

  const renderTimeline = (status) => {
    const current = statusIndex(status);
    return `<ol class="order-timeline">${timeline.map((step, index) => `
      <li class="${index <= current ? 'done' : ''}">
        <span></span>
        <strong>${ArteFlor.escapeHtml(step)}</strong>
      </li>
    `).join('')}</ol>`;
  };

  const render = () => {
    const term = (search?.value || '').toLocaleLowerCase('pt-BR').trim();
    const localOrders = ArteFlor.getOrders();
    const orders = [...localOrders, ...demoOrders].filter((order) => {
      if (!term) return true;
      return String(order.codigo || '').toLocaleLowerCase('pt-BR').includes(term);
    });

    root.innerHTML = orders.map((order) => `
      <article class="card client-order-card">
        <div class="client-order-head">
          <div>
            <span class="status ${order.status === 'Finalizado' || order.status === 'Pagamento confirmado' ? 'status-ok' : 'status-warn'}">${ArteFlor.escapeHtml(order.status || 'Pedido recebido')}</span>
            <h3>${ArteFlor.escapeHtml(order.codigo)}</h3>
          </div>
          <strong>${ArteFlor.formatMoney(order.total || 0)}</strong>
        </div>
        <div class="client-order-meta">
          <span>Cliente: ${ArteFlor.escapeHtml(order.cliente || 'Cliente demonstrativo')}</span>
          <span>Pagamento: ${ArteFlor.escapeHtml(order.pagamento || 'A definir')}</span>
          <span>Recebimento: ${ArteFlor.escapeHtml(order.recebimento || 'Entrega')}</span>
          <span>Bairro: ${ArteFlor.escapeHtml(order.bairro || 'Não informado')}</span>
        </div>
        ${renderTimeline(order.status || 'Pedido recebido')}
        <div class="client-order-items">
          ${(order.itens || []).map((item) => `<span>${Number(item.qty || 1)}x ${ArteFlor.escapeHtml(item.nome)}</span>`).join('')}
        </div>
      </article>
    `).join('');

    if (empty) empty.hidden = orders.length > 0;
  };

  search?.addEventListener('input', render);
  render();
})();
