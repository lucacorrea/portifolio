(() => {
  const list = document.getElementById('cartList');
  const subtotalEl = document.getElementById('cartSubtotal');
  const discountEl = document.getElementById('cartDiscount');
  const totalEl = document.getElementById('cartTotal');
  const pageCountEl = document.querySelector('[data-cart-page-count]');
  const statusEl = document.querySelector('[data-cart-status]');
  const checkoutLink = document.querySelector('[data-checkout-link]');

  if (!list || !window.ArteFlor) return;

  const toNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  };

  const validCartItem = (item) => {
    const productId = toNumber(item?.id || item?.produto_id);
    const price = toNumber(item?.preco);
    return productId > 0 && price >= 0;
  };

  const normalizeCart = () => {
    const current = ArteFlor.getCart();
    const valid = current.filter(validCartItem).map((item) => ({
      ...item,
      id: String(item.id || item.produto_id),
      qty: Math.max(1, toNumber(item.qty || item.quantidade, 1))
    }));

    if (valid.length !== current.length) {
      ArteFlor.setCart(valid);
      ArteFlor.toast('Itens antigos ou inválidos foram removidos do carrinho.', 'info');
    }

    return valid;
  };

  const itemQuantity = (item) => Math.max(1, toNumber(item.qty || item.quantidade, 1));

  const itemMaxStock = (item) => {
    const stock = toNumber(item.estoque, 0);
    return stock > 0 ? stock : null;
  };

  const clampQuantity = (item, nextQty) => {
    const maxStock = itemMaxStock(item);
    const qty = Math.max(1, toNumber(nextQty, 1));
    return maxStock ? Math.min(qty, maxStock) : qty;
  };

  const updateQuantity = (id, nextQty) => {
    const cart = ArteFlor.getCart().map((item) => {
      if (String(item.id) !== String(id)) return item;
      return { ...item, qty: clampQuantity(item, nextQty) };
    });

    ArteFlor.setCart(cart);
  };

  const setCheckoutEnabled = (enabled) => {
    if (!checkoutLink) return;
    checkoutLink.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    checkoutLink.tabIndex = enabled ? 0 : -1;
  };

  const renderEmpty = () => {
    list.innerHTML = `
      <div class="empty-state">
        <strong>Seu carrinho está vazio.</strong>
        <p>Escolha flores, vasos ou presentes no catálogo para iniciar o pedido.</p>
        <a class="btn btn-primary" href="catalogo.php">Ver catálogo</a>
      </div>
    `;
  };

  const renderItem = (item) => {
    const qty = itemQuantity(item);
    const maxStock = itemMaxStock(item);
    const lineTotal = toNumber(item.preco) * qty;
    const priceLabel = ArteFlor.formatMoney(toNumber(item.preco));
    const stockLabel = maxStock ? `<small>Disponível: ${maxStock} un.</small>` : '';
    const canDecrease = qty > 1;
    const canIncrease = !maxStock || qty < maxStock;
    const image = item.imagem
      ? `<img src="${ArteFlor.escapeHtml(item.imagem)}" alt="${ArteFlor.escapeHtml(item.nome || 'Produto Arte&Flor')}">`
      : 'A&F';
    const thumbClass = item.imagem ? 'cart-thumb' : 'cart-thumb fallback';

    return `
      <article class="cart-item" data-cart-row="${ArteFlor.escapeHtml(item.id)}">
        <div class="${thumbClass}">${image}</div>
        <div class="cart-item-info">
          <strong>${ArteFlor.escapeHtml(item.nome || 'Produto Arte&Flor')}</strong>
          <span>${ArteFlor.escapeHtml(item.categoria || 'Produto Arte&Flor')} • ${priceLabel} cada</span>
          ${stockLabel}
          ${item.mensagem ? `<small>Cartão: ${ArteFlor.escapeHtml(item.mensagem)}</small>` : ''}
          ${item.observacoes ? `<small>Obs.: ${ArteFlor.escapeHtml(item.observacoes)}</small>` : ''}
        </div>
        <div class="qty-control" aria-label="Quantidade de ${ArteFlor.escapeHtml(item.nome || 'produto')}">
          <button type="button" data-cart-minus="${ArteFlor.escapeHtml(item.id)}" ${canDecrease ? '' : 'disabled'} aria-label="Diminuir quantidade">-</button>
          <strong>${qty}</strong>
          <button type="button" data-cart-plus="${ArteFlor.escapeHtml(item.id)}" ${canIncrease ? '' : 'disabled'} aria-label="Aumentar quantidade">+</button>
        </div>
        <div class="cart-line-total">
          <strong>${ArteFlor.formatMoney(lineTotal)}</strong>
          <button type="button" data-cart-remove="${ArteFlor.escapeHtml(item.id)}">Remover</button>
        </div>
      </article>
    `;
  };

  const render = () => {
    const cart = normalizeCart();
    const totals = ArteFlor.cartTotals(cart);
    const totalItems = cart.reduce((sum, item) => sum + itemQuantity(item), 0);

    if (!cart.length) {
      renderEmpty();
    } else {
      list.innerHTML = cart.map(renderItem).join('');
    }

    subtotalEl.textContent = ArteFlor.formatMoney(totals.subtotal);
    discountEl.textContent = totals.discount > 0 ? `-${ArteFlor.formatMoney(totals.discount)}` : ArteFlor.formatMoney(0);
    totalEl.textContent = ArteFlor.formatMoney(totals.total);

    if (pageCountEl) {
      pageCountEl.textContent = `${totalItems} ${totalItems === 1 ? 'item' : 'itens'}`;
    }

    if (statusEl) {
      statusEl.textContent = cart.length
        ? 'Revise antes de finalizar. O estoque será validado novamente no checkout.'
        : 'Nenhum item selecionado no momento.';
    }

    setCheckoutEnabled(cart.length > 0);
  };

  list.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const minusId = target.dataset.cartMinus;
    const plusId = target.dataset.cartPlus;
    const removeId = target.dataset.cartRemove;
    const cart = ArteFlor.getCart();

    if (minusId) {
      const item = cart.find((row) => String(row.id) === String(minusId));
      updateQuantity(minusId, itemQuantity(item || {}) - 1);
      render();
    }

    if (plusId) {
      const item = cart.find((row) => String(row.id) === String(plusId));
      updateQuantity(plusId, itemQuantity(item || {}) + 1);
      render();
    }

    if (removeId) {
      ArteFlor.removeFromCart(removeId);
      render();
    }
  });

  checkoutLink?.addEventListener('click', (event) => {
    if (ArteFlor.getCart().length) return;
    event.preventDefault();
    ArteFlor.toast('Adicione pelo menos um produto ao carrinho antes do checkout.', 'warning');
  });

  document.addEventListener('arteflor:cart-updated', render);
  render();
})();
