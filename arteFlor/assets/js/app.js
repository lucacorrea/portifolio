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

  const toast = (message, type = 'success', options = {}) => {
    const root = document.querySelector('[data-toast-root]');
    if (!root) return;

    const item = document.createElement('div');
    item.className = `toast toast-${type}`;

    if (message && typeof message === 'object') {
      const title = String(message.title || '').trim();
      const body = String(message.message || '').trim();

      if (title) {
        const strong = document.createElement('strong');
        strong.textContent = title;
        item.appendChild(strong);
      }

      if (body) {
        const span = document.createElement('span');
        span.textContent = body;
        item.appendChild(span);
      }
    } else {
      item.textContent = String(message ?? '');
    }

    root.appendChild(item);

    window.setTimeout(() => {
      item.classList.add('toast-hide');
      window.setTimeout(() => item.remove(), 240);
    }, Number(options.duration || 4200));
  };

  const alertToastType = (alert) => {
    if (alert.classList.contains('admin-alert-danger')) return 'danger';
    if (alert.classList.contains('admin-alert-warning')) return 'warning';
    if (alert.classList.contains('admin-alert-info')) return 'info';
    return 'success';
  };

  const alertToastPayload = (alert) => {
    const title = alert.querySelector('strong')?.textContent?.trim() || 'Aviso';
    const clone = alert.cloneNode(true);
    clone.querySelector('strong')?.remove();

    return {
      title,
      message: clone.textContent.replace(/\s+/g, ' ').trim()
    };
  };

  const bindAdminFlashAlerts = () => {
    document.querySelectorAll('.admin-alert-card[role="status"], .admin-alert-card[data-admin-flash]').forEach((alert) => {
      if (alert.dataset.toastBound === '1') return;

      alert.dataset.toastBound = '1';
      toast(alertToastPayload(alert), alertToastType(alert), {
        duration: Number(alert.dataset.toastDuration || 4200)
      });
      alert.remove();
    });
  };

  const normalizeProduct = (product) => {
    const id = String(product.id || product.produto_id || product.slug || Date.now());
    const corId = String(product.cor_id || product.produto_cor_id || '').trim();
    const cartKey = corId ? `${id}:cor:${corId}` : id;

    return {
      id,
      produto_id: id,
      cartKey,
      nome: product.nome || 'Produto Arte&Flor',
      slug: product.slug || '',
      categoria: product.categoria || '',
      preco: Number(product.preco || 0),
      imagem: product.cor_imagem || product.imagem || '',
      estoque: Number(product.cor_estoque || product.estoque || 0),
      status: product.status || 'disponivel',
      cor_id: corId,
      cor_nome: product.cor_nome || '',
      cor_hex: product.cor_hex || '',
      cor_imagem: product.cor_imagem || '',
      mensagem: product.mensagem || '',
      observacoes: product.observacoes || '',
      qty: Math.max(1, Number(product.qty || 1))
    };
  };

  const cartItemKey = (item) => String(item?.cartKey || item?.id || item?.produto_id || '');

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
      const current = cart.find((item) => cartItemKey(item) === nextProduct.cartKey);

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
        .map((item) => cartItemKey(item) === String(id) ? { ...item, qty: Math.max(1, Number(qty || 1)) } : item);
      this.setCart(cart);
    },
    removeFromCart(id) {
      this.setCart(this.getCart().filter((item) => cartItemKey(item) !== String(id)));
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
    const button = document.querySelector('[data-menu-toggle]');
    const nav = document.querySelector('.main-nav');
    const buttonText = document.querySelector('[data-menu-toggle-text]');

    if (!button || !nav) return;

    const closeMenu = () => {
      nav.classList.remove('open');
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', 'Abrir menu');
      if (buttonText) buttonText.textContent = 'Menu';
    };

    const toggleMenu = () => {
      const isOpen = nav.classList.toggle('open');
      button.setAttribute('aria-expanded', String(isOpen));
      button.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
      if (buttonText) buttonText.textContent = isOpen ? 'Fechar' : 'Menu';
    };

    button.addEventListener('click', (event) => {
      event.stopPropagation();
      toggleMenu();
    });

    nav.addEventListener('click', (event) => {
      const link = event.target.closest('a[href]');
      if (!link) return;

      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || (link.target && link.target !== '_self') || link.hasAttribute('download')) {
        window.setTimeout(closeMenu, 120);
        return;
      }

      event.preventDefault();
      const destination = link.href;
      closeMenu();

      if (destination && destination !== window.location.href) {
        window.location.assign(destination);
      }
    });

    document.addEventListener('click', (event) => {
      if (!nav.classList.contains('open')) return;
      if (nav.contains(event.target) || button.contains(event.target)) return;
      closeMenu();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeMenu();
    });

    window.addEventListener('resize', () => {
      if (window.matchMedia('(min-width: 921px)').matches) closeMenu();
    });
  };

  const bindAddButtons = () => {
    document.querySelectorAll('[data-add-cart]').forEach((button) => {
      button.addEventListener('click', () => {
        const status = button.dataset.status || 'disponivel';
        const stock = Number(button.dataset.estoque || 0);
        const colorTarget = button.dataset.colorTarget ? document.querySelector(button.dataset.colorTarget) : null;
        const requiresColor = button.dataset.requireColor === '1';
        if (requiresColor && !colorTarget) {
          toast('Escolha a cor antes de adicionar ao carrinho.', 'warning');
          return;
        }

        const colorData = colorTarget instanceof HTMLElement ? colorTarget.dataset : {};
        const effectiveStock = colorData.corEstoque ? Number(colorData.corEstoque || 0) : stock;

        if (status !== 'disponivel' || effectiveStock <= 0) {
          toast('Este produto não está disponível para compra direta.', 'warning');
          return;
        }

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
          estoque: effectiveStock,
          status,
          cor_id: colorData.corId || '',
          cor_nome: colorData.corNome || '',
          cor_hex: colorData.corHex || '',
          cor_imagem: colorData.corImagem || '',
          cor_estoque: effectiveStock,
          qty: qtyTarget ? Number(qtyTarget.value || 1) : Number(button.dataset.qty || 1),
          mensagem: messageTarget ? messageTarget.value.trim() : '',
          observacoes: noteTarget ? noteTarget.value.trim() : ''
        });
      });
    });
  };

  const bindProductColors = () => {
    const qtyInput = document.querySelector('#productQty');
    const addButton = document.querySelector('[data-add-cart][data-color-target]');
    const mainImage = document.querySelector('[data-gallery-main]');

    document.querySelectorAll('input[name="produto_cor_id"]').forEach((input) => {
      input.addEventListener('change', () => {
        const stock = Number(input.dataset.corEstoque || 0);
        const image = input.dataset.corImagem || '';

        if (qtyInput instanceof HTMLInputElement) {
          qtyInput.max = String(Math.max(1, stock));
          qtyInput.value = String(Math.min(Math.max(1, Number(qtyInput.value || 1)), Math.max(1, stock)));
        }

        if (addButton) {
          addButton.dataset.estoque = String(stock);
        }

        if (mainImage instanceof HTMLImageElement && image) {
          mainImage.src = image;
          mainImage.alt = `${addButton?.dataset.nome || 'Produto'} - ${input.dataset.corNome || 'cor selecionada'}`;
        }
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

  const bindConfirmForms = () => {
    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;

      const message = form.dataset.confirm || form.querySelector('[data-confirm]')?.dataset.confirm || '';
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  };

  bindMenu();
  bindConfirmForms();
  bindAddButtons();
  bindProductColors();
  bindGallery();
  bindCopy();
  bindAdminFlashAlerts();
  api.updateCartCount = function () {
    const total = this.getCart().reduce((sum, item) => sum + Number(item.qty || 0), 0);
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
      el.textContent = total;
    });
  };
  api.updateCartCount();
}());
