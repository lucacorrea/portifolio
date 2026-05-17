const list = document.getElementById('cartList');
const total = document.getElementById('cartTotal');
const cart = ArteFlor.getCart();

if (!cart.length) {
  list.innerHTML = '<div class="admin-empty-state"><strong>Seu carrinho está vazio.</strong><p class="muted">Volte ao catálogo e escolha um produto para continuar.</p></div>';
} else {
  list.innerHTML = cart.map((item) => `
    <div class="cart-item">
      ${item.imagem ? `<img src="${item.imagem}" alt="${item.nome}">` : '<div class="product-img">💐</div>'}
      <div>
        <strong>${item.nome}</strong>
        <p class="muted">Quantidade: ${item.qty}</p>
        <p>${ArteFlor.formatMoney(item.preco * item.qty)}</p>
      </div>
      <button class="btn btn-soft" onclick="ArteFlor.removeFromCart('${item.id}')">Remover</button>
    </div>
  `).join('');
}

total.textContent = ArteFlor.formatMoney(cart.reduce((sum, item) => sum + Number(item.preco) * Number(item.qty || 1), 0));
