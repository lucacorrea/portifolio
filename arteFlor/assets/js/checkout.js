const summary = document.getElementById('checkoutSummary');
const totalEl = document.getElementById('checkoutTotal');
const form = document.getElementById('checkoutForm');
const cartCheckout = ArteFlor.getCart();
const totalCheckout = cartCheckout.reduce((sum, item) => sum + item.preco * item.qty, 0);

summary.innerHTML = cartCheckout.length ? cartCheckout.map((item) => `<p>${item.qty}x ${item.nome} - ${ArteFlor.formatMoney(item.preco * item.qty)}</p>`).join('') : '<p class="muted">Carrinho vazio.</p>';
totalEl.textContent = ArteFlor.formatMoney(totalCheckout);

form.addEventListener('submit', (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(form).entries());
  const itens = cartCheckout.map((item) => `- ${item.qty}x ${item.nome} (${ArteFlor.formatMoney(item.preco * item.qty)})`).join('\n');
  const message = `Olá, vim pelo catálogo da Arte&Flor.\n\nPedido:\n${itens || 'Sem itens no carrinho'}\n\nTotal: ${ArteFlor.formatMoney(totalCheckout)}\n\nCliente: ${data.nome}\nWhatsApp: ${data.whatsapp}\nRecebimento: ${data.recebimento}\nBairro: ${data.bairro}\nEndereço: ${data.endereco}\nPagamento: ${data.pagamento}\nObservações: ${data.observacoes || 'Nenhuma'}`;
  window.open(`https://wa.me/5597000000000?text=${encodeURIComponent(message)}`, '_blank');
});
