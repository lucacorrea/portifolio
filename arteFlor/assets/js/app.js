(function () {
  const CART_KEY = 'arteflor_cart';
  const ORDERS_KEY = 'arteflor_orders';
  const SALES_KEY = 'arteflor_pdv_sales';

  const parse = (key, fallback = []) => {
    try {
      const value = JSON.parse(localStorage.getItem(key) || 'null');
      return value ?? fallback;
    } catch (error) {
      return fallback;
    }
  };

  const save = (key, value) => {
    localStorage.setItem(key, JSON.stringify(value));
  };

  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const money = (value) => Number(value || 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });

  const toast = (message, type = 'success') => {
    const root = document.querySelector('[data-toast-root]');
    if (!root) return;

    const item = document.createElement('div');
    item.className = `toast toast-${type}`;
    item.textContent = message;
    root.appendChild(item);

    window.setTimeout(() => {
      item.classList.add('toast-hide');
      window.setTimeout(() => item.remove(), 240);
    }, 3200);
  };

  const normalizeProduct = (product) => ({
    id: String(product.id || product.slug || Date.now()),
    nome: product.nome || 'Produto Arte&Flor',
    slug: product.slug || '',
    categoria: product.categoria || '',
    preco: Number(product.preco || 0),
    imagem: product.imagem || '',
    mensagem: product.mensagem || '',
    observacoes: product.observacoes || '',
    qty: Math.max(1, Number(product.qty || 1))
  });

  const api = {
    cartKey: CART_KEY,
    ordersKey: ORDERS_KEY,
    salesKey: SALES_KEY,
    escapeHtml,
    formatMoney: money,
    getCart() {
      return parse(CART_KEY, []);
    },
    setCart(cart) {
      save(CART_KEY, cart);
      this.updateCartCount();
      document.dispatchEvent(new CustomEvent('arteflor:cart-updated', { detail: cart }));
    },
    addToCart(product) {
      const nextProduct = normalizeProduct(product);
      const cart = this.getCart();
      const current = cart.find((item) => item.id === nextProduct.id);

      if (current) {
        current.qty = Math.max(1, Number(current.qty || 1) + nextProduct.qty);
        current.mensagem = nextProduct.mensagem || current.mensagem || '';
        current.observacoes = nextProduct.observacoes || current.observacoes || '';
      } else {
        cart.push(nextProduct);
      }

      this.setCart(cart);
      toast(`${nextProduct.nome} foi adicionado ao carrinho.`);
    },
    updateCartItem(id, qty) {
      const cart = this.getCart()
        .map((item) => item.id === String(id) ? { ...item, qty: Math.max(1, Number(qty || 1)) } : item);
      this.setCart(cart);
    },
    removeFromCart(id) {
      this.setCart(this.getCart().filter((item) => item.id !== String(id)));
      toast('Item removido do carrinho.', 'info');
    },
    clearCart() {
      this.setCart([]);
    },
    cartTotals(cart = this.getCart()) {
      const subtotal = cart.reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 1), 0);
      const discount = subtotal >= 250 ? subtotal * 0.05 : 0;
      const total = Math.max(0, subtotal - discount);
      return { subtotal, discount, total };
    },
    getOrders() {
      return parse(ORDERS_KEY, []);
    },
    saveOrder(order) {
      const orders = this.getOrders();
      orders.unshift(order);
      save(ORDERS_KEY, orders.slice(0, 20));
      return order;
    },
    getSales() {
      return parse(SALES_KEY, []);
    },
    saveSale(sale) {
      const sales = this.getSales();
      sales.unshift(sale);
      save(SALES_KEY, sales.slice(0, 30));
      return sale;
    },
    toast
  };

  window.ArteFlor = api;

  const bindMenu = () => {
    document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => {
      document.querySelector('.main-nav')?.classList.toggle('open');
    });
  };

  const bindAddButtons = () => {
    document.querySelectorAll('[data-add-cart]').forEach((button) => {
      button.addEventListener('click', () => {
        const qtyTarget = button.dataset.qtyTarget ? document.querySelector(button.dataset.qtyTarget) : null;
        const messageTarget = button.dataset.messageTarget ? document.querySelector(button.dataset.messageTarget) : null;
        const noteTarget = button.dataset.noteTarget ? document.querySelector(button.dataset.noteTarget) : null;

        api.addToCart({
          id: button.dataset.id,
          nome: button.dataset.nome,
          slug: button.dataset.slug,
          categoria: button.dataset.categoria,
          preco: Number(button.dataset.preco || 0),
          imagem: button.dataset.imagem || '',
          qty: qtyTarget ? Number(qtyTarget.value || 1) : Number(button.dataset.qty || 1),
          mensagem: messageTarget ? messageTarget.value.trim() : '',
          observacoes: noteTarget ? noteTarget.value.trim() : ''
        });
      });
    });
  };

  const bindGallery = () => {
    document.querySelectorAll('[data-gallery-thumb]').forEach((button) => {
      button.addEventListener('click', () => {
        const img = document.querySelector('[data-gallery-main]');
        if (!img) return;
        img.src = button.dataset.galleryThumb || '';
        img.alt = button.dataset.galleryAlt || img.alt;
        document.querySelectorAll('[data-gallery-thumb]').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
      });
    });
  };

  const bindCopy = () => {
    document.querySelectorAll('[data-copy-value]').forEach((button) => {
      button.addEventListener('click', async () => {
        const target = button.dataset.copyTarget ? document.querySelector(button.dataset.copyTarget) : null;
        const value = button.dataset.copyValue || target?.textContent?.trim() || '';
        try {
          await navigator.clipboard.writeText(value);
          toast('Informação copiada.');
        } catch (error) {
          toast('Não foi possível copiar automaticamente.', 'warning');
        }
      });
    });
  };

  bindMenu();
  bindAddButtons();
  bindGallery();
  bindCopy();
  api.updateCartCount = function () {
    const total = this.getCart().reduce((sum, item) => sum + Number(item.qty || 0), 0);
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
      el.textContent = total;
    });
  };
  api.updateCartCount();
}());
