<?php
$pageTitle = 'Carrinho';
$activePage = 'catalogo';

/*
  Nesta versão demonstrativa, o carrinho é renderizado no próprio PHP.
  Mantive $pageScripts vazio para evitar que js/carrinho.js limpe ou sobrescreva os itens demo.
*/
$pageScripts = [];

require_once __DIR__ . '/includes/header.php';

$produtos = load_json('produtos.json');

if (!function_exists('af_money')) {
  function af_money(float $valor): string {
    return 'R$ ' . number_format($valor, 2, ',', '.');
  }
}

if (!function_exists('af_image_url')) {
  function af_image_url(?string $imagem): string {
    if (!$imagem) {
      return 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=700&q=85';
    }

    if (preg_match('/^https?:\/\//', $imagem)) {
      return $imagem;
    }

    return site_url(ltrim($imagem, '/'));
  }
}

$fallbackProdutos = [
  [
    'id' => 'demo-buque-pastel',
    'nome' => 'Buquê Elegance Pastel',
    'categoria' => 'Buquês',
    'descricao' => 'Composição floral delicada para presente, datas especiais e encomendas personalizadas.',
    'preco' => 145.90,
    'imagem' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=700&q=85'
  ],
  [
    'id' => 'demo-arranjo-especial',
    'nome' => 'Arranjo Presente Especial',
    'categoria' => 'Arranjos',
    'descricao' => 'Arranjo decorativo com acabamento premium, ideal para entrega local e retirada na loja.',
    'preco' => 89.90,
    'imagem' => 'https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?auto=format&fit=crop&w=700&q=85'
  ]
];

$baseProdutos = is_array($produtos) && count($produtos) >= 2
  ? array_slice(array_values($produtos), 0, 2)
  : $fallbackProdutos;

while (count($baseProdutos) < 2) {
  $baseProdutos[] = $fallbackProdutos[count($baseProdutos)];
}

$demoCart = [];

foreach ($baseProdutos as $index => $produto) {
  $preco = (float)($produto['preco'] ?? $produto['valor'] ?? $produto['valor_unitario'] ?? 0);

  if ($preco <= 0) {
    $preco = (float)$fallbackProdutos[$index]['preco'];
  }

  $imagem = $produto['imagem']
    ?? $produto['image']
    ?? $produto['foto']
    ?? ($produto['imagens'][0] ?? null);

  $demoCart[] = [
    'id' => $produto['id'] ?? $fallbackProdutos[$index]['id'],
    'nome' => $produto['nome'] ?? $fallbackProdutos[$index]['nome'],
    'categoria' => $produto['categoria'] ?? $fallbackProdutos[$index]['categoria'],
    'descricao' => $produto['descricao'] ?? $fallbackProdutos[$index]['descricao'],
    'preco' => $preco,
    'quantidade' => $index === 0 ? 1 : 2,
    'imagem' => af_image_url($imagem),
  ];
}

$subtotal = array_reduce($demoCart, function ($total, $item) {
  return $total + ($item['preco'] * $item['quantidade']);
}, 0);

$desconto = $subtotal > 0 ? min(20, $subtotal * 0.05) : 0;
$total = max(0, $subtotal - $desconto);

$demoCartPayload = array_map(function ($item) {
  return [
    'id' => $item['id'],
    'nome' => $item['nome'],
    'categoria' => $item['categoria'],
    'preco' => $item['preco'],
    'valor' => $item['preco'],
    'quantidade' => $item['quantidade'],
    'qtd' => $item['quantidade'],
    'imagem' => $item['imagem'],
  ];
}, $demoCart);
?>

<style>
/* =========================================================
   CARRINHO — DEMONSTRAÇÃO PROFISSIONAL COM 2 PRODUTOS
   CSS interno somente para carrinho.php
========================================================= */

.compact-header {
  background: linear-gradient(135deg, #edf3e9 0%, #fbf4ec 100%);
  border-bottom: 1px solid rgba(47, 72, 58, .12);
}

.compact-header .page-header-grid {
  align-items: center;
}

.compact-header .badge {
  border-radius: 10px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  color: #244836;
  box-shadow: none;
}

.cart-layout {
  grid-template-columns: minmax(0, 1fr) minmax(330px, 390px);
  gap: 28px;
  align-items: start;
}

.cart-list {
  display: grid;
  gap: 14px;
  padding: 18px;
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: 0 14px 34px rgba(45, 55, 48, .07);
}

.cart-list-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 4px 2px 12px;
  border-bottom: 1px solid rgba(47, 72, 58, .10);
}

.cart-list-header strong {
  display: block;
  color: #244836;
  font-size: 1.05rem;
  font-weight: 950;
}

.cart-list-header span {
  color: #626b64;
  font-size: .9rem;
  font-weight: 700;
}

.cart-demo-label {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  padding: 6px 10px;
  border-radius: 10px;
  background: #f4dce4;
  color: #82495c;
  font-size: .72rem;
  font-weight: 900;
  letter-spacing: .04em;
  text-transform: uppercase;
  white-space: nowrap;
}

.demo-cart-item {
  display: grid;
  grid-template-columns: 112px minmax(0, 1fr) auto auto;
  gap: 16px;
  align-items: center;
  padding: 14px;
  border-radius: 16px;
  background: #ffffff;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: none;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.demo-cart-item:hover {
  transform: none;
  border-color: rgba(47, 72, 58, .22);
  box-shadow: 0 10px 26px rgba(45, 55, 48, .06);
}

.cart-thumb {
  width: 112px;
  height: 96px;
  border-radius: 12px;
  overflow: hidden;
  background: #edf3e9;
}

.cart-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cart-item-info {
  display: grid;
  gap: 5px;
  min-width: 0;
}

.cart-item-info strong {
  color: #244836;
  font-size: 1rem;
  font-weight: 950;
  line-height: 1.25;
}

.cart-item-info span {
  color: #626b64;
  font-size: .88rem;
  font-weight: 750;
}

.cart-item-info small {
  color: #7b847d;
  font-size: .82rem;
  font-weight: 650;
  line-height: 1.35;
}

.qty-control {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 40px;
  padding: 4px;
  border-radius: 12px;
  background: #f8f1e8;
  border: 1px solid rgba(47, 72, 58, .12);
}

.qty-control button {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #fff;
  color: #244836;
  border: 1px solid rgba(47, 72, 58, .12);
  font-weight: 950;
  cursor: pointer;
}

.qty-control button:hover {
  background: #edf3e9;
}

.qty-control input {
  width: 46px;
  height: 32px;
  border: 0;
  background: transparent;
  color: #244836;
  font-weight: 950;
  text-align: center;
  outline: none;
}

.cart-line-total {
  display: grid;
  gap: 8px;
  justify-items: end;
  min-width: 122px;
}

.cart-line-total strong {
  color: #82495c;
  font-size: 1.02rem;
  font-weight: 950;
}

.cart-line-total button {
  min-height: 36px;
  padding: 8px 12px;
  border-radius: 10px;
  background: #f5e8e8;
  color: #8b3f4d;
  border: 1px solid rgba(139, 63, 77, .14);
  font-size: .82rem;
  font-weight: 850;
  cursor: pointer;
}

.cart-line-total button:hover {
  background: #efdada;
}

.cart-summary {
  position: sticky;
  top: 96px;
  display: grid;
  gap: 14px;
  padding: 24px;
  border-radius: 18px;
  background: #fffdf8;
  border: 1px solid rgba(47, 72, 58, .12);
  box-shadow: 0 14px 34px rgba(45, 55, 48, .07);
}

.cart-summary .badge {
  width: fit-content;
  border-radius: 10px;
  background: #edf3e9;
  color: #244836;
  border: 1px solid rgba(47, 72, 58, .12);
}

.cart-summary h2 {
  color: #244836;
  font-family: var(--fonte-corpo);
  font-size: 1.55rem;
  font-weight: 950;
  letter-spacing: -.025em;
}

.summary-lines {
  display: grid;
  gap: 0;
}

.summary-lines p {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  padding: 13px 0;
  border-bottom: 1px solid rgba(47, 72, 58, .10);
  color: #626b64;
  font-weight: 750;
}

.summary-lines strong {
  color: #244836;
  font-weight: 950;
  white-space: nowrap;
}

.summary-total strong {
  color: #82495c;
  font-size: 1.45rem;
}

.cart-summary .actions {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-top: 4px;
}

.cart-summary .btn {
  width: 100%;
  min-height: 46px;
  border-radius: 10px;
}

.cart-summary .muted {
  color: #7b847d;
  font-size: .86rem;
}

.cart-empty-state {
  display: none;
  padding: 24px;
  border-radius: 16px;
  background: #f8f1e8;
  border: 1px dashed rgba(47, 72, 58, .20);
  text-align: center;
}

.cart-empty-state strong {
  display: block;
  color: #244836;
  margin-bottom: 4px;
}

.cart-empty-state p {
  color: #626b64;
}

@media (max-width: 980px) {
  .cart-layout {
    grid-template-columns: 1fr;
  }

  .cart-summary {
    position: static;
  }
}

@media (max-width: 720px) {
  .cart-list-header {
    flex-direction: column;
  }

  .demo-cart-item {
    grid-template-columns: 96px minmax(0, 1fr);
  }

  .cart-thumb {
    width: 96px;
    height: 96px;
  }

  .qty-control,
  .cart-line-total {
    grid-column: 1 / -1;
  }

  .cart-line-total {
    justify-items: start;
    padding-top: 12px;
    border-top: 1px solid rgba(47, 72, 58, .10);
  }
}

@media (max-width: 480px) {
  .demo-cart-item {
    grid-template-columns: 1fr;
  }

  .cart-thumb {
    width: 100%;
    height: 180px;
  }

  .qty-control {
    width: 100%;
    justify-content: space-between;
  }
}
</style>

<section class="page-header compact-header">
  <div class="container page-header-grid">
    <div>
      <span class="badge">Carrinho</span>
      <h1 class="section-title">Revise sua compra</h1>
      <p class="section-subtitle">Ajuste quantidades, confira imagens e avance para o checkout visual.</p>
    </div>
    <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Continuar comprando</a>
  </div>
</section>

<section class="section">
  <div class="container cart-layout">
    <div class="cart-list card" id="cartList">
      <div class="cart-list-header">
        <div>
          <strong>Produtos selecionados</strong>
          <span>Dois itens demonstrativos para validar o layout do carrinho.</span>
        </div>
        <span class="cart-demo-label">Demonstração</span>
      </div>

      <?php foreach ($demoCart as $item): ?>
        <article
          class="cart-item demo-cart-item"
          data-demo-cart-item
          data-id="<?= e((string)$item['id']) ?>"
          data-name="<?= e($item['nome']) ?>"
          data-category="<?= e($item['categoria']) ?>"
          data-image="<?= e($item['imagem']) ?>"
          data-price="<?= e((string)$item['preco']) ?>"
        >
          <div class="cart-thumb">
            <img src="<?= e($item['imagem']) ?>" alt="<?= e($item['nome']) ?>">
          </div>

          <div class="cart-item-info">
            <strong><?= e($item['nome']) ?></strong>
            <span><?= e($item['categoria']) ?> • <?= af_money($item['preco']) ?> cada</span>
            <small><?= e($item['descricao']) ?></small>
          </div>

          <div class="qty-control" aria-label="Controle de quantidade">
            <button type="button" data-demo-qty-action="minus" aria-label="Diminuir quantidade">−</button>
            <input
              type="number"
              min="1"
              max="20"
              value="<?= e((string)$item['quantidade']) ?>"
              data-demo-qty
              aria-label="Quantidade"
            >
            <button type="button" data-demo-qty-action="plus" aria-label="Aumentar quantidade">+</button>
          </div>

          <div class="cart-line-total">
            <strong data-demo-line-total><?= af_money($item['preco'] * $item['quantidade']) ?></strong>
            <button type="button" data-demo-remove>Remover</button>
          </div>
        </article>
      <?php endforeach; ?>

      <div class="cart-empty-state" data-demo-empty>
        <strong>Seu carrinho demonstrativo está vazio.</strong>
        <p>Volte ao catálogo para escolher outros produtos.</p>
      </div>
    </div>

    <aside class="card cart-summary">
      <span class="badge">Resumo</span>
      <h2>Total do pedido</h2>

      <div class="summary-lines">
        <p><span>Subtotal</span><strong id="cartSubtotal"><?= af_money($subtotal) ?></strong></p>
        <p><span>Desconto demonstrativo</span><strong id="cartDiscount"><?= af_money($desconto) ?></strong></p>
        <p class="summary-total"><span>Total</span><strong id="cartTotal"><?= af_money($total) ?></strong></p>
      </div>

      <div class="actions">
        <a class="btn btn-soft" href="<?= site_url('catalogo.php') ?>">Continuar comprando</a>
        <a class="btn btn-primary" href="<?= site_url('checkout.php') ?>">Ir para checkout</a>
      </div>

      <p class="muted">Carrinho demonstrativo salvo neste navegador para simular a experiência de checkout.</p>
    </aside>
  </div>
</section>

<script>
(() => {
  const demoInitialCart = <?= json_encode($demoCartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });

  const cartList = document.getElementById('cartList');
  const subtotalEl = document.getElementById('cartSubtotal');
  const discountEl = document.getElementById('cartDiscount');
  const totalEl = document.getElementById('cartTotal');
  const emptyEl = document.querySelector('[data-demo-empty]');

  function getItems() {
    return [...document.querySelectorAll('[data-demo-cart-item]')].map((item) => {
      const qtyInput = item.querySelector('[data-demo-qty]');
      const quantity = Math.max(1, parseInt(qtyInput.value || '1', 10));
      const price = Number(item.dataset.price || 0);

      return {
        id: item.dataset.id,
        nome: item.dataset.name,
        categoria: item.dataset.category,
        preco: price,
        valor: price,
        quantidade: quantity,
        qtd: quantity,
        imagem: item.dataset.image
      };
    });
  }

  function saveDemoCart(items) {
    const knownKeys = [
      'arteflor_cart',
      'arteFlorCart',
      'arteflorCart',
      'carrinhoArteFlor',
      'carrinho',
      'cart'
    ];

    const payload = JSON.stringify(items);

    try {
      localStorage.setItem('arteflor_demo_cart', payload);

      const hasExistingCart = knownKeys.some((key) => {
        try {
          const current = JSON.parse(localStorage.getItem(key) || '[]');
          return Array.isArray(current) && current.length > 0;
        } catch {
          return false;
        }
      });

      if (!hasExistingCart) {
        knownKeys.forEach((key) => localStorage.setItem(key, payload));
      }
    } catch {
      // Se o navegador bloquear localStorage, a tela continua funcionando visualmente.
    }
  }

  function updateCart() {
    const items = getItems();
    let subtotal = 0;

    document.querySelectorAll('[data-demo-cart-item]').forEach((item) => {
      const qtyInput = item.querySelector('[data-demo-qty]');
      const lineTotalEl = item.querySelector('[data-demo-line-total]');
      const quantity = Math.max(1, parseInt(qtyInput.value || '1', 10));
      const price = Number(item.dataset.price || 0);
      const lineTotal = price * quantity;

      qtyInput.value = quantity;
      lineTotalEl.textContent = money.format(lineTotal);
      subtotal += lineTotal;
    });

    const discount = subtotal > 0 ? Math.min(20, subtotal * 0.05) : 0;
    const total = Math.max(0, subtotal - discount);

    subtotalEl.textContent = money.format(subtotal);
    discountEl.textContent = money.format(discount);
    totalEl.textContent = money.format(total);

    document.querySelectorAll('[data-cart-count]').forEach((countEl) => {
      countEl.textContent = items.reduce((sum, item) => sum + item.quantidade, 0);
    });

    if (emptyEl) {
      emptyEl.style.display = items.length ? 'none' : 'block';
    }

    saveDemoCart(items);
  }

  cartList.addEventListener('click', (event) => {
    const qtyButton = event.target.closest('[data-demo-qty-action]');
    const removeButton = event.target.closest('[data-demo-remove]');

    if (qtyButton) {
      const item = qtyButton.closest('[data-demo-cart-item]');
      const input = item.querySelector('[data-demo-qty]');
      const current = Math.max(1, parseInt(input.value || '1', 10));

      input.value = qtyButton.dataset.demoQtyAction === 'plus'
        ? current + 1
        : Math.max(1, current - 1);

      updateCart();
    }

    if (removeButton) {
      const item = removeButton.closest('[data-demo-cart-item]');
      item.remove();
      updateCart();
    }
  });

  cartList.addEventListener('change', (event) => {
    if (event.target.matches('[data-demo-qty]')) {
      updateCart();
    }
  });

  if (!getItems().length && demoInitialCart.length) {
    saveDemoCart(demoInitialCart);
  }

  updateCart();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>