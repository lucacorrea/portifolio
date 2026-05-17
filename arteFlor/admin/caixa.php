<?php
require_once __DIR__ . '/../includes/helpers.php';

$activeAdmin = 'caixa';
$produtos = load_json('produtos.json');
$pdvProdutos = array_map(function ($produto) {
    $precoPromocional = (float) ($produto['preco_promocional'] ?? 0);
    $preco = $precoPromocional > 0 ? $precoPromocional : (float) ($produto['preco'] ?? 0);

    return [
        'code' => 'AF' . str_pad((string) ($produto['id'] ?? 0), 4, '0', STR_PAD_LEFT),
        'name' => $produto['nome'] ?? 'Produto Arte&Flor',
        'category' => $produto['categoria'] ?? 'Geral',
        'price' => $preco,
        'stock' => (int) ($produto['estoque'] ?? 0),
        'status' => $produto['status'] ?? 'Disponível',
        'image' => $produto['imagem'] ?? '',
        'alt' => $produto['alt'] ?? ($produto['nome'] ?? 'Produto Arte&Flor'),
    ];
}, $produtos);

$categorias = array_values(array_unique(array_map(function ($produto) {
    return $produto['category'];
}, $pdvProdutos)));
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Caixa PDV | Arte&Flor</title>
  <meta name="description" content="Frente de caixa demonstrativa da Arte&Flor.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/pages.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/admin-caixa.css') ?>">
</head>

<body class="admin-pdv-page">
  <div class="admin-shell">
    <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <main class="admin-main pdv-main">
      <header class="pdv-command-bar">
        <div class="pdv-store">
          <span class="pdv-store-icon" aria-hidden="true">A&F</span>
          <div>
            <strong>ARTE&FLOR - PDV</strong>
            <small>CNPJ demonstrativo 00.000.000/0001-00</small>
          </div>
        </div>

        <div class="pdv-hotkeys" aria-label="Atalhos do caixa">
          <span><kbd>F2</kbd> Quantidade</span>
          <span><kbd>F3</kbd> Desconto</span>
          <span><kbd>Enter</kbd> Adicionar</span>
          <span><kbd>F4</kbd> Finalizar</span>
          <span><kbd>F6</kbd> Recebido</span>
        </div>

        <div class="pdv-status-actions">
          <span class="pdv-status is-open">Caixa aberto</span>
          <a class="pdv-exit" href="<?= site_url('admin/dashboard.php') ?>">Voltar</a>
        </div>
      </header>

      <section class="pdv-grid" aria-label="Frente de caixa">
        <aside class="pdv-panel pdv-entry-panel">
          <div class="pdv-field-block">
            <label for="productSearch">Código de barras</label>
            <div class="pdv-scan-box">
              <span aria-hidden="true">▦</span>
              <input id="productSearch" type="text" placeholder="Passe o leitor ou digite" autocomplete="off" autofocus>
            </div>
          </div>

          <div class="pdv-field-block">
            <label for="manualCode">Código</label>
            <input id="manualCode" type="text" placeholder="SKU / interno (opcional)">
          </div>

          <div class="pdv-inline-fields">
            <div class="pdv-field-block">
              <label for="unitPrice">Valor unitário</label>
              <input id="unitPrice" type="text" value="R$ 0,00" readonly>
            </div>

            <div class="pdv-field-block">
              <label for="productQty">Quantidade</label>
              <input id="productQty" type="number" min="1" value="1">
            </div>
          </div>

          <div class="pdv-item-total-box">
            <span>Total do item</span>
            <strong id="itemTotalText">R$ 0,00</strong>
          </div>

          <div class="pdv-product-preview" id="productPreview">
            <div class="pdv-preview-image">
              <img src="https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=800&q=80" alt="Flores da Arte&Flor" loading="lazy">
            </div>
            <div>
              <span>Produto selecionado</span>
              <strong>Nenhum produto selecionado</strong>
              <small>Busque pelo código, nome ou clique em um produto rápido.</small>
            </div>
          </div>

          <div class="pdv-entry-actions">
            <button class="pdv-btn pdv-btn-primary" type="button" id="addProduct">Adicionar</button>
            <button class="pdv-btn pdv-btn-ghost" type="button" id="clearEntry">Limpar</button>
          </div>

          <div class="pdv-category-row" id="categoryFilters">
            <button class="is-active" type="button" data-category="todos">Todos</button>
            <?php foreach ($categorias as $categoria): ?>
              <button type="button" data-category="<?= e($categoria) ?>"><?= e($categoria) ?></button>
            <?php endforeach; ?>
          </div>

          <div class="pdv-quick-products" id="quickProducts"></div>
        </aside>

        <section class="pdv-center">
          <div class="pdv-current-product">
            <div>
              <span>Produto</span>
              <strong id="currentProductName">-</strong>
            </div>
            <div>
              <span>Valor</span>
              <strong id="currentProductPrice">R$ 0,00</strong>
            </div>
          </div>

          <section class="pdv-panel pdv-items-panel">
            <header class="pdv-panel-title">
              <strong>Lista de itens</strong>
              <span id="saleCode">Venda #AF-0001</span>
            </header>

            <div class="pdv-items-list" id="cartList">
              <div class="pdv-empty-list">
                <strong>Sem itens</strong>
                <p>Adicione produtos para iniciar a venda no caixa.</p>
              </div>
            </div>
          </section>

          <div class="pdv-subtotal-bar">
            <span>Subtotal</span>
            <strong id="subtotalText">R$ 0,00</strong>
          </div>
        </section>

        <aside class="pdv-right">
          <section class="pdv-panel pdv-total-card">
            <span>Total</span>
            <strong id="totalText">R$ 0,00</strong>
          </section>

          <section class="pdv-panel pdv-discount-card">
            <label for="discountInput">Desconto</label>
            <div class="pdv-money-input">
              <span>R$</span>
              <input id="discountInput" type="number" min="0" step="0.01" value="0">
            </div>
          </section>

          <section class="pdv-panel pdv-payment-panel">
            <header class="pdv-panel-title">
              <strong>Pagamento</strong>
              <label class="pdv-multi-pay">
                <input id="multiPayment" type="checkbox">
                <span>Múltiplos pagamentos</span>
              </label>
            </header>

            <div class="pdv-payment-grid">
              <label class="pdv-payment-option">
                <input type="radio" name="payment" value="Dinheiro" checked>
                <span>Dinheiro</span>
              </label>
              <label class="pdv-payment-option">
                <input type="radio" name="payment" value="Pix">
                <span>Pix</span>
              </label>
              <label class="pdv-payment-option">
                <input type="radio" name="payment" value="Débito">
                <span>Débito</span>
              </label>
              <label class="pdv-payment-option">
                <input type="radio" name="payment" value="Crédito">
                <span>Crédito</span>
              </label>
            </div>

            <div class="pdv-pix-demo" id="pixPanel" hidden>
              <div class="pdv-qr" aria-label="QR Code Pix demonstrativo">
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <strong>PIX</strong>
              </div>
              <code id="pixCode">00020126580014BR.GOV.BCB.PIX0136arteflor-caixa-demo5204000053039865802BR5910ARTE E FLOR6005COARI62070503***6304DEMO</code>
              <button class="pdv-btn pdv-btn-ghost" type="button" id="copyPix">Copiar Pix</button>
            </div>

            <div class="pdv-received-box">
              <label for="receivedInput">Total recebido</label>
              <div class="pdv-money-input">
                <span>R$</span>
                <input id="receivedInput" type="number" min="0" step="0.01" value="0">
              </div>
            </div>

            <div class="pdv-change-box">
              <span>Troco</span>
              <strong id="changeText">R$ 0,00</strong>
            </div>

            <button class="pdv-btn pdv-btn-finish" type="button" id="finalizeSale">Finalizar venda <kbd>F4</kbd></button>
          </section>
        </aside>
      </section>

     
    </main>
  </div>

  <div class="toast" data-toast role="status" aria-live="polite"></div>
  <div class="pdv-toast" id="pdvToast" role="status" aria-live="polite"></div>
  <script src="<?= asset('js/app.js') ?>"></script>
  <script src="<?= asset('js/admin.js') ?>"></script>
  <script>
    (function () {
      const WHATSAPP_NUMBER = '5597000000000';
      const products = <?= json_encode($pdvProdutos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const $ = (selector) => document.querySelector(selector);
      const $$ = (selector) => document.querySelectorAll(selector);

      let selectedProduct = products[0] || null;
      let cart = [];
      let activeCategory = 'todos';
      let saleNumber = 1;
      let sales = [
        { code: 'AF-1028', customer: 'Maria Clara', payment: 'Pix', total: 189.90 },
        { code: 'AF-1027', customer: 'Cliente balcão', payment: 'Dinheiro', total: 129.90 },
        { code: 'AF-1024', customer: 'Ana Beatriz', payment: 'Crédito', total: 229.90 }
      ];

      const productSearch = $('#productSearch');
      const manualCode = $('#manualCode');
      const unitPrice = $('#unitPrice');
      const productQty = $('#productQty');
      const itemTotalText = $('#itemTotalText');
      const productPreview = $('#productPreview');
      const currentProductName = $('#currentProductName');
      const currentProductPrice = $('#currentProductPrice');
      const quickProducts = $('#quickProducts');
      const cartList = $('#cartList');
      const subtotalText = $('#subtotalText');
      const totalText = $('#totalText');
      const discountInput = $('#discountInput');
      const receivedInput = $('#receivedInput');
      const changeText = $('#changeText');
      const pixPanel = $('#pixPanel');
      const pixCode = $('#pixCode');
      const saleCode = $('#saleCode');
      const salesHistory = $('#salesHistory');
      const toastEl = $('#pdvToast');

      const money = (value) => Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
      });

      const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const toast = (message) => {
        toastEl.textContent = message;
        toastEl.classList.add('is-visible');
        window.clearTimeout(toast.timer);
        toast.timer = window.setTimeout(() => toastEl.classList.remove('is-visible'), 2600);
      };

      const subtotal = () => cart.reduce((sum, item) => sum + item.price * item.qty, 0);
      const discount = () => Math.max(0, Number(discountInput.value || 0));
      const total = () => Math.max(0, subtotal() - discount());
      const selectedPayment = () => document.querySelector('input[name="payment"]:checked')?.value || 'Dinheiro';

      const findProduct = (term) => {
        const normalized = String(term || '').trim().toLowerCase();
        if (!normalized) return null;

        return products.find((product) => (
          product.code.toLowerCase() === normalized ||
          product.code.toLowerCase().includes(normalized) ||
          product.name.toLowerCase().includes(normalized) ||
          product.category.toLowerCase().includes(normalized)
        ));
      };

      const setSelectedProduct = (product) => {
        if (!product) return;

        selectedProduct = product;
        unitPrice.value = money(product.price);
        itemTotalText.textContent = money(product.price * Math.max(1, Number(productQty.value || 1)));
        manualCode.value = product.code;
        currentProductName.textContent = product.name;
        currentProductPrice.textContent = money(product.price);
        productPreview.innerHTML = `
          <div class="pdv-preview-image">
            <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.alt)}" loading="lazy">
          </div>
          <div>
            <span>Produto selecionado</span>
            <strong>${escapeHtml(product.name)}</strong>
            <small>${escapeHtml(product.category)} · Estoque ${product.stock} · ${escapeHtml(product.status)}</small>
          </div>
        `;
      };

      const renderQuickProducts = () => {
        const term = productSearch.value.trim().toLowerCase();
        const filtered = products.filter((product) => {
          const categoryMatch = activeCategory === 'todos' || product.category === activeCategory;
          const termMatch = !term || product.code.toLowerCase().includes(term) || product.name.toLowerCase().includes(term);
          return categoryMatch && termMatch;
        });

        quickProducts.innerHTML = filtered.map((product) => `
          <button class="pdv-product-tile" type="button" data-code="${escapeHtml(product.code)}">
            <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.alt)}" loading="lazy">
            <span>
              <strong>${escapeHtml(product.name)}</strong>
              <small>${escapeHtml(product.code)} · ${escapeHtml(product.category)}</small>
              <b>${money(product.price)}</b>
            </span>
          </button>
        `).join('');

        quickProducts.querySelectorAll('[data-code]').forEach((button) => {
          button.addEventListener('click', () => {
            const product = products.find((item) => item.code === button.dataset.code);
            setSelectedProduct(product);
          });
        });
      };

      const renderCart = () => {
        if (!cart.length) {
          cartList.innerHTML = `
            <div class="pdv-empty-list">
              <strong>Sem itens</strong>
              <p>Adicione produtos para iniciar a venda no caixa.</p>
            </div>
          `;
        } else {
          cartList.innerHTML = cart.map((item, index) => `
            <div class="pdv-cart-row">
              <img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.alt)}" loading="lazy">
              <div>
                <strong>${escapeHtml(item.name)}</strong>
                <small>${escapeHtml(item.code)} · Unitário ${money(item.price)}</small>
              </div>
              <div class="pdv-qty">
                <button type="button" data-dec="${index}">−</button>
                <span>${item.qty}</span>
                <button type="button" data-inc="${index}">+</button>
              </div>
              <b>${money(item.price * item.qty)}</b>
              <button class="pdv-remove" type="button" data-remove="${index}" aria-label="Remover item">×</button>
            </div>
          `).join('');
        }

        subtotalText.textContent = money(subtotal());
        totalText.textContent = money(total());
        changeText.textContent = money(Math.max(0, Number(receivedInput.value || 0) - total()));
        saleCode.textContent = `Venda #AF-${String(saleNumber).padStart(4, '0')}`;

        $$('[data-inc]').forEach((button) => {
          button.addEventListener('click', () => {
            cart[Number(button.dataset.inc)].qty += 1;
            renderCart();
          });
        });

        $$('[data-dec]').forEach((button) => {
          button.addEventListener('click', () => {
            const item = cart[Number(button.dataset.dec)];
            item.qty = Math.max(1, item.qty - 1);
            renderCart();
          });
        });

        $$('[data-remove]').forEach((button) => {
          button.addEventListener('click', () => {
            cart.splice(Number(button.dataset.remove), 1);
            renderCart();
            toast('Item removido da venda.');
          });
        });
      };

      const addSelectedProduct = () => {
        const typedProduct = findProduct(productSearch.value) || findProduct(manualCode.value);
        const product = typedProduct || selectedProduct;
        const qty = Math.max(1, Number(productQty.value || 1));

        if (!product) {
          toast('Selecione ou digite um produto.');
          return;
        }

        const current = cart.find((item) => item.code === product.code);
        if (current) {
          current.qty += qty;
        } else {
          cart.push({ ...product, qty });
        }

        setSelectedProduct(product);
        productSearch.value = '';
        productQty.value = 1;
        productSearch.focus();
        renderQuickProducts();
        renderCart();
        toast(`${product.name} adicionado.`);
      };

      const renderHistory = () => {
        salesHistory.innerHTML = sales.slice(0, 3).map((sale) => `
          <article>
            <strong>${escapeHtml(sale.code)}</strong>
            <span>${escapeHtml(sale.customer)} · ${escapeHtml(sale.payment)}</span>
            <b>${money(sale.total)}</b>
          </article>
        `).join('');
      };

      const togglePix = () => {
        pixPanel.hidden = selectedPayment() !== 'Pix';
      };

      const finalizeSale = () => {
        if (!cart.length) {
          toast('Adicione produtos antes de finalizar.');
          return;
        }

        const code = `AF-${String(Date.now()).slice(-5)}`;
        const payment = selectedPayment();
        const customer = $('#customerName').value || 'Cliente balcão';
        const note = $('#saleNote').value || '';

        sales.unshift({ code, customer, payment, total: total() });

        if (payment === 'Pix') {
          toast(`Venda ${code} finalizada com Pix demonstrativo.`);
        } else {
          toast(`Venda ${code} finalizada em ${payment}.`);
        }

        localStorage.setItem('arteflor_pdv_last_sale', JSON.stringify({
          code,
          customer,
          payment,
          note,
          total: total(),
          items: cart,
          createdAt: new Date().toISOString()
        }));

        cart = [];
        discountInput.value = 0;
        receivedInput.value = 0;
        saleNumber += 1;
        renderCart();
        renderHistory();
      };

      productSearch.addEventListener('input', () => {
        const product = findProduct(productSearch.value);
        if (product) setSelectedProduct(product);
        renderQuickProducts();
      });

      productQty.addEventListener('input', () => {
        if (selectedProduct) {
          itemTotalText.textContent = money(selectedProduct.price * Math.max(1, Number(productQty.value || 1)));
        }
      });

      productSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          addSelectedProduct();
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'F4') {
          event.preventDefault();
          finalizeSale();
        }

        if (event.key === 'F2') {
          event.preventDefault();
          productQty.focus();
          productQty.select();
        }

        if (event.key === 'F3') {
          event.preventDefault();
          discountInput.focus();
          discountInput.select();
        }

        if (event.key === 'F6') {
          event.preventDefault();
          receivedInput.focus();
          receivedInput.select();
        }
      });

      $('#addProduct').addEventListener('click', addSelectedProduct);
      $('#clearEntry').addEventListener('click', () => {
        productSearch.value = '';
        manualCode.value = '';
        productQty.value = 1;
        renderQuickProducts();
        productSearch.focus();
      });

      discountInput.addEventListener('input', renderCart);
      receivedInput.addEventListener('input', renderCart);
      $('#finalizeSale').addEventListener('click', finalizeSale);

      $$('#categoryFilters button').forEach((button) => {
        button.addEventListener('click', () => {
          $$('#categoryFilters button').forEach((item) => item.classList.remove('is-active'));
          button.classList.add('is-active');
          activeCategory = button.dataset.category;
          renderQuickProducts();
        });
      });

      $$('input[name="payment"]').forEach((input) => {
        input.addEventListener('change', togglePix);
      });

      $('#copyPix').addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(pixCode.textContent.trim());
          toast('Código Pix copiado.');
        } catch (error) {
          toast('Copie o código Pix manualmente.');
        }
      });

      setSelectedProduct(selectedProduct);
      renderQuickProducts();
      renderCart();
      renderHistory();
      togglePix();
    })();
  </script>
</body>

</html>
