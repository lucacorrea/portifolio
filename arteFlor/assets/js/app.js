const ArteFlor = {
  cartKey: 'arteflor_cart',
  getCart() {
    return JSON.parse(localStorage.getItem(this.cartKey) || '[]');
  },
  setCart(cart) {
    localStorage.setItem(this.cartKey, JSON.stringify(cart));
  },
  addToCart(product) {
    const cart = this.getCart();
    const current = cart.find((item) => item.id === product.id);
    if (current) current.qty += product.qty || 1;
    else cart.push({ ...product, qty: product.qty || 1 });
    this.setCart(cart);
    alert('Produto adicionado ao carrinho.');
  },
  removeFromCart(id) {
    this.setCart(this.getCart().filter((item) => item.id !== id));
    location.reload();
  },
  formatMoney(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }
};

document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => {
  document.querySelector('.main-nav')?.classList.toggle('open');
});
