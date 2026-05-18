(() => {
  const list = document.getElementById('cartList');
  const subtotalEl = document.getElementById('cartSubtotal');
  const discountEl = document.getElementById('cartDiscount');
  const totalEl = document.getElementById('cartTotal');

  if (!list || !window.ArteFlor) return;

  const render = () => {
    const cart = ArteFlor.getCart();
    const totals = ArteFlor.cartTotals(cart);

    if (!cart.length) {
      list.innerHTML = `
        <div class="empty-state">
          <strong>Seu carrinho está vazio.</strong>
          <p>Escolha flores, vasos ou presentes no catálogo para iniciar o pedido.</p>
          <a class="btn btn-primary" href="catalogo.php">Ver catálogo</a>
        </div>
      `;
    } else {
      list.innerHTML = cart.map((item) => {
        const lineTotal = Number(item.preco || 0) * Number(item.qty || 1);
        const image = item.imagem
          ? `<img src="${ArteFlor.escapeHtml(item.imagem)}" alt="${ArteFlor.escapeHtml(item.nome)}">`
          : `A&F`;
        const thumbClass = item.imagem ? 'cart-thumb' : 'cart-thumb fallback';

        return `
          <article class="cart-item" data-cart-row="${ArteFlor.escapeHtml(item.id)}">
            <div class="${thumbClass}">${image}</div>
            <div class="cart-item-info">
              <strong>${ArteFlor.escapeHtml(item.nome)}</strong>
              <span>${ArteFlor.escapeHtml(item.categoria || 'Produto Arte&Flor')}</span>
              ${item.mensagem ? `<small>Cartão: ${ArteFlor.escapeHtml(item.mensagem)}</small>` : ''}
              ${item.observacoes ? `<small>Obs.: ${ArteFlor.escapeHtml(item.observacoes)}</small>` : ''}
            </div>
            <div class="qty-control" aria-label="Quantidade">
              <button type="button" data-cart-minus="${ArteFlor.escapeHtml(item.id)}">-</button>
              <strong>${Number(item.qty || 1)}</strong>
              <button type="button" data-cart-plus="${ArteFlor.escapeHtml(item.id)}">+</button>
            </div>
            <div class="cart-line-total">
              <strong>${ArteFlor.formatMoney(lineTotal)}</strong>
              <button type="button" data-cart-remove="${ArteFlor.escapeHtml(item.id)}">Remover</button>
            </div>
          </article>
        `;
      }).join('');
    }

    subtotalEl.textContent = ArteFlor.formatMoney(totals.subtotal);
    discountEl.textContent = totals.discount > 0 ? `-${ArteFlor.formatMoney(totals.discount)}` : ArteFlor.formatMoney(0);
    totalEl.textContent = ArteFlor.formatMoney(totals.total);
  };

  list.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const minusId = target.dataset.cartMinus;
    const plusId = target.dataset.cartPlus;
    const removeId = target.dataset.cartRemove;
    const cart = ArteFlor.getCart();

    if (minusId) {
      const item = cart.find((row) => row.id === minusId);
      ArteFlor.updateCartItem(minusId, Math.max(1, Number(item?.qty || 1) - 1));
      render();
    }

    if (plusId) {
      const item = cart.find((row) => row.id === plusId);
      ArteFlor.updateCartItem(plusId, Number(item?.qty || 1) + 1);
      render();
    }

    if (removeId) {
      ArteFlor.removeFromCart(removeId);
      render();
    }
  });

  document.addEventListener('arteflor:cart-updated', render);
  render();
})();
