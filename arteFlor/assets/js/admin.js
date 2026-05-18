(() => {
  if (!window.ArteFlor) return;

  const productScript = document.getElementById('pdvProducts');
  if (!productScript) return;

  let products = [];
  try {
    products = JSON.parse(productScript.textContent || '[]');
  } catch (error) {
    products = [];
  }

  const productGrid = document.querySelector('[data-pdv-product-grid]');
  const currentList = document.querySelector('[data-pdv-current]');
  const search = document.querySelector('[data-pdv-search]');
  const qtyInput = document.querySelector('[data-pdv-qty]');
  const discountInput = document.querySelector('[data-pdv-discount]');
  const subtotalEl = document.querySelector('[data-pdv-subtotal]');
  const totalEl = document.querySelector('[data-pdv-total]');
  const historyEl = document.querySelector('[data-pdv-history]');
  const clientInput = document.querySelector('[data-pdv-client]');
  const contactInput = document.querySelector('[data-pdv-contact]');
  const paymentInput = document.querySelector('[data-pdv-payment]');
  let currentCategory = 'todos';
  let sale = [];

  const getQty = () => Math.max(1, Number(qtyInput?.value || 1));

  const saleTotals = () => {
    const subtotal = sale.reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 1), 0);
    const discount = Math.max(0, Number(discountInput?.value || 0));
    return { subtotal, discount, total: Math.max(0, subtotal - discount) };
  };

  const addProduct = (product, qty = getQty()) => {
    const current = sale.find((item) => item.id === String(product.id));
    if (current) {
      current.qty += qty;
    } else {
      sale.push({ ...product, id: String(product.id), qty });
    }
    renderSale();
    ArteFlor.toast(`${product.nome} adicionado ao caixa.`);
  };

  const renderProducts = () => {
    if (!productGrid) return;
    const term = (search?.value || '').toLocaleLowerCase('pt-BR').trim();
    const filtered = products.filter((product) => {
      const text = `${product.sku} ${product.nome} ${product.categoria}`.toLocaleLowerCase('pt-BR');
      const categoryOk = currentCategory === 'todos' || product.categoria === currentCategory;
      return categoryOk && (!term || text.includes(term));
    });

    productGrid.innerHTML = filtered.map((product) => `
      <button class="pdv-product-card" type="button" data-pdv-add="${ArteFlor.escapeHtml(product.id)}">
        ${product.imagem ? `<img src="${ArteFlor.escapeHtml(product.imagem)}" alt="${ArteFlor.escapeHtml(product.nome)}">` : '<span>A&F</span>'}
        <strong>${ArteFlor.escapeHtml(product.nome)}</strong>
        <small>${ArteFlor.escapeHtml(product.sku || product.categoria)}</small>
        <em>${ArteFlor.formatMoney(product.preco)}</em>
      </button>
    `).join('') || '<div class="empty-state small"><strong>Nenhum produto</strong><p>Ajuste a busca do PDV.</p></div>';
  };

  const renderSale = () => {
    if (!currentList) return;

    currentList.innerHTML = sale.length ? sale.map((item) => `
      <article class="pdv-sale-item">
        ${item.imagem ? `<img src="${ArteFlor.escapeHtml(item.imagem)}" alt="${ArteFlor.escapeHtml(item.nome)}">` : '<span>A&F</span>'}
        <div>
          <strong>${ArteFlor.escapeHtml(item.nome)}</strong>
          <small>${ArteFlor.formatMoney(item.preco)} un.</small>
        </div>
        <div class="qty-control">
          <button type="button" data-pdv-minus="${ArteFlor.escapeHtml(item.id)}">-</button>
          <strong>${item.qty}</strong>
          <button type="button" data-pdv-plus="${ArteFlor.escapeHtml(item.id)}">+</button>
        </div>
        <button type="button" data-pdv-remove="${ArteFlor.escapeHtml(item.id)}">Remover</button>
      </article>
    `).join('') : '<div class="empty-state small"><strong>Venda vazia</strong><p>Busque produtos ou use os atalhos rápidos.</p></div>';

    const totals = saleTotals();
    if (subtotalEl) subtotalEl.textContent = ArteFlor.formatMoney(totals.subtotal);
    if (totalEl) totalEl.textContent = ArteFlor.formatMoney(totals.total);
  };

  const renderHistory = () => {
    if (!historyEl) return;
    const sales = ArteFlor.getSales();
    historyEl.innerHTML = sales.slice(0, 5).map((item) => `
      <div class="pdv-history-row">
        <strong>${ArteFlor.escapeHtml(item.codigo)}</strong>
        <span>${ArteFlor.escapeHtml(item.pagamento)}</span>
        <em>${ArteFlor.formatMoney(item.total)}</em>
      </div>
    `).join('') || '<p class="muted">Nenhuma venda finalizada neste navegador.</p>';
  };

  productGrid?.addEventListener('click', (event) => {
    const source = event.target;
    const target = source instanceof HTMLElement ? source.closest('[data-pdv-add]') : null;
    if (!target) return;
    const product = products.find((item) => String(item.id) === target.dataset.pdvAdd);
    if (product) addProduct(product);
  });

  document.querySelector('[data-pdv-add-search]')?.addEventListener('click', () => {
    const term = (search?.value || '').toLocaleLowerCase('pt-BR').trim();
    const product = products.find((item) => `${item.id} ${item.sku} ${item.nome}`.toLocaleLowerCase('pt-BR').includes(term));
    if (product) {
      addProduct(product);
    } else {
      ArteFlor.toast('Produto não encontrado na busca.', 'warning');
    }
  });

  document.querySelectorAll('[data-pdv-category]').forEach((button) => {
    button.addEventListener('click', () => {
      currentCategory = button.dataset.pdvCategory || 'todos';
      document.querySelectorAll('[data-pdv-category]').forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      renderProducts();
    });
  });

  currentList?.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const plus = target.dataset.pdvPlus;
    const minus = target.dataset.pdvMinus;
    const remove = target.dataset.pdvRemove;

    if (plus) {
      const item = sale.find((row) => row.id === plus);
      if (item) item.qty += 1;
    }
    if (minus) {
      const item = sale.find((row) => row.id === minus);
      if (item) item.qty = Math.max(1, item.qty - 1);
    }
    if (remove) {
      sale = sale.filter((row) => row.id !== remove);
    }
    renderSale();
  });

  const finishSale = () => {
    if (!sale.length) {
      ArteFlor.toast('Adicione produtos antes de finalizar a venda.', 'warning');
      return;
    }

    const totals = saleTotals();
    const code = `#PDV-${String(Math.floor(1000 + Math.random() * 9000))}`;
    ArteFlor.saveSale({
      codigo: code,
      cliente: clientInput?.value || 'Cliente balcão',
      contato: contactInput?.value || '',
      pagamento: paymentInput?.value || 'Pix',
      subtotal: totals.subtotal,
      desconto: totals.discount,
      total: totals.total,
      status: 'Venda finalizada no sistema',
      itens: sale,
      criadoEm: new Date().toISOString()
    });

    sale = [];
    if (discountInput) discountInput.value = 0;
    renderSale();
    renderHistory();
    ArteFlor.toast(`Venda ${code} finalizada no sistema.`);
  };

  document.querySelectorAll('[data-pdv-finish]').forEach((button) => button.addEventListener('click', finishSale));
  document.querySelector('[data-pdv-cancel]')?.addEventListener('click', () => {
    sale = [];
    renderSale();
    ArteFlor.toast('Venda atual cancelada.', 'info');
  });
  document.querySelectorAll('[data-pdv-suspend]').forEach((button) => {
    button.addEventListener('click', () => ArteFlor.toast('Venda suspensa visualmente.', 'info'));
  });

  search?.addEventListener('input', renderProducts);
  discountInput?.addEventListener('input', renderSale);
  renderProducts();
  renderSale();
  renderHistory();
})();
