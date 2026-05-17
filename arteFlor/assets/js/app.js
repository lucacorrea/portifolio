const ArteFlor = {
  cartKey: 'arteflor_cart',
  getCart() {
    try {
      return JSON.parse(localStorage.getItem(this.cartKey) || '[]');
    } catch (error) {
      return [];
    }
  },
  setCart(cart) {
    localStorage.setItem(this.cartKey, JSON.stringify(cart));
  },
  addToCart(product) {
    const cart = this.getCart();
    const current = cart.find((item) => String(item.id) === String(product.id));
    if (current) {
      current.qty += 1;
    } else {
      cart.push({ ...product, qty: 1 });
    }
    this.setCart(cart);
    this.updateCartCount();
    alert('Produto adicionado ao carrinho.');
  },
  removeFromCart(id) {
    this.setCart(this.getCart().filter((item) => String(item.id) !== String(id)));
    location.reload();
  },
  formatMoney(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  },
  updateCartCount() {
    const total = this.getCart().reduce((sum, item) => sum + Number(item.qty || 0), 0);
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
      el.textContent = total;
    });
  }
};

document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => {
  document.querySelector('.main-nav')?.classList.toggle('open');
});

document.querySelectorAll('[data-add-cart]').forEach((button) => {
  button.addEventListener('click', () => {
    ArteFlor.addToCart({
      id: button.dataset.id,
      nome: button.dataset.nome,
      preco: Number(button.dataset.preco || 0),
      imagem: button.dataset.imagem || ''
    });
  });
});

ArteFlor.updateCartCount();
