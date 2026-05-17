(function () {
  const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const renderCart = () => {
    const list = document.getElementById('cartList');
    const total = document.getElementById('cartTotal');
    const subtotal = document.getElementById('cartSubtotal');

    if (!list || !total || !subtotal || !window.ArteFlor) {
      return;
    }

    const cart = ArteFlor.getCart();
    const value = cart.reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 0), 0);

    if (!cart.length) {
      list.innerHTML = '<div class="empty-state">Seu carrinho está vazio. Adicione produtos pelo catálogo para simular a compra.</div>';
    } else {
      list.innerHTML = cart.map((item) => `
        <article class="cart-item" data-cart-item="${item.id}">
          <img src="${escapeHtml(item.imagem)}" alt="Imagem de ${escapeHtml(item.nome)}" loading="lazy">
          <div class="cart-item-body">
            <div>
              <h3>${escapeHtml(item.nome)}</h3>
              <p class="muted">${ArteFlor.money(item.preco)} cada</p>
            </div>
            <div class="quantity-control" aria-label="Alterar quantidade de ${escapeHtml(item.nome)}">
              <button type="button" data-cart-action="decrement" data-id="${item.id}">-</button>
              <input value="${item.qty}" readonly aria-label="Quantidade">
              <button type="button" data-cart-action="increment" data-id="${item.id}">+</button>
            </div>
            <div class="summary-line"><span>Subtotal</span><strong>${ArteFlor.money(Number(item.preco || 0) * Number(item.qty || 0))}</strong></div>
            <button class="btn btn-danger" type="button" data-cart-action="remove" data-id="${item.id}">Remover item</button>
          </div>
        </article>
      `).join('');
    }

    subtotal.textContent = ArteFlor.money(value);
    total.textContent = ArteFlor.money(value);
    ArteFlor.updateCartCount();
  };

  document.addEventListener('DOMContentLoaded', () => {
    renderCart();

    document.getElementById('cartList')?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-cart-action]');
      if (!button || !window.ArteFlor) {
        return;
      }

      const id = Number(button.dataset.id);
      const cart = ArteFlor.getCart();
      const item = cart.find((current) => Number(current.id) === id);

      if (button.dataset.cartAction === 'remove') {
        ArteFlor.removeFromCart(id);
        ArteFlor.toast('Item removido do carrinho.');
      }

      if (button.dataset.cartAction === 'increment' && item) {
        ArteFlor.updateQty(id, Number(item.qty || 1) + 1);
      }

      if (button.dataset.cartAction === 'decrement' && item) {
        ArteFlor.updateQty(id, Math.max(1, Number(item.qty || 1) - 1));
      }

      renderCart();
    });
  });
})();
