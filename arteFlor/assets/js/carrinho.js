const list = document.getElementById('cartList');
const total = document.getElementById('cartTotal');
const cart = ArteFlor.getCart();

if (!cart.length) {
  list.innerHTML = '<p class="muted">Seu carrinho está vazio.</p>';
} else {
  list.innerHTML = cart.map((item) => `
    <div class="card" style="margin-bottom:12px">
      <strong>${item.nome}</strong>
      <p class="muted">Quantidade: ${item.qty}</p>
      <p>${ArteFlor.formatMoney(item.preco * item.qty)}</p>
      <button class="btn btn-soft" onclick="ArteFlor.removeFromCart(${item.id})">Remover</button>
    </div>
  `).join('');
}

total.textContent = ArteFlor.formatMoney(cart.reduce((sum, item) => sum + item.preco * item.qty, 0));
