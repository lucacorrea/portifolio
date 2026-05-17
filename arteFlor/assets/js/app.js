(function () {
  const CART_KEY = 'arteflor_cart';
  const ORDER_KEY = 'arteflor_orders';
  const WHATSAPP_NUMBER = '5597000000000';

  const parseJson = (value, fallback) => {
    try {
      return JSON.parse(value);
    } catch (error) {
      return fallback;
    }
  };

  const money = (value) => Number(value || 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });

  const getCart = () => parseJson(localStorage.getItem(CART_KEY) || '[]', []);
  const setCart = (cart) => {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartCount();
  };

  const getOrders = () => parseJson(localStorage.getItem(ORDER_KEY) || '[]', []);
  const setOrders = (orders) => localStorage.setItem(ORDER_KEY, JSON.stringify(orders));

  const toast = (message) => {
    const element = document.querySelector('[data-toast]');
    if (!element) {
      return;
    }
    element.textContent = message;
    element.classList.add('show');
    window.clearTimeout(toast.timer);
    toast.timer = window.setTimeout(() => element.classList.remove('show'), 2600);
  };

  const updateCartCount = () => {
    const total = getCart().reduce((sum, item) => sum + Number(item.qty || 0), 0);
    document.querySelectorAll('[data-cart-count]').forEach((element) => {
      element.textContent = total;
    });
  };

  const getQuantityNearButton = (button) => {
    const scope = button.closest('[data-product-purchase]');
    const input = scope?.querySelector('[data-product-quantity]');
    const qty = Number(input?.value || 1);
    return Number.isFinite(qty) && qty > 0 ? qty : 1;
  };

  const addToCart = (product, qty = 1) => {
    if (!product || !product.id) {
      toast('Produto inválido para o carrinho.');
      return;
    }

    const cart = getCart();
    const id = Number(product.id);
    const current = cart.find((item) => Number(item.id) === id);

    if (current) {
      current.qty = Number(current.qty || 0) + qty;
    } else {
      cart.push({
        id,
        nome: product.nome || 'Produto Arte&Flor',
        preco: Number(product.preco || 0),
        imagem: product.imagem || '',
        slug: product.slug || '',
        qty
      });
    }

    setCart(cart);
    toast('Produto adicionado ao carrinho.');
  };

  const removeFromCart = (id) => {
    setCart(getCart().filter((item) => Number(item.id) !== Number(id)));
  };

  const updateQty = (id, qty) => {
    const nextQty = Math.max(1, Number(qty || 1));
    const cart = getCart().map((item) => (
      Number(item.id) === Number(id) ? { ...item, qty: nextQty } : item
    ));
    setCart(cart);
  };

  const cartTotal = () => getCart().reduce((sum, item) => sum + Number(item.preco || 0) * Number(item.qty || 0), 0);

  const saveOrder = (order) => {
    const orders = getOrders();
    orders.unshift(order);
    setOrders(orders.slice(0, 8));
  };

  const whatsappUrl = (message) => `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;

  const initMenu = () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const nav = document.querySelector('[data-main-nav]');
    if (!toggle || !nav) {
      return;
    }

    toggle.addEventListener('click', () => {
      const isOpen = nav.classList.toggle('open');
      document.body.classList.toggle('menu-open', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
    });

    nav.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        nav.classList.remove('open');
        document.body.classList.remove('menu-open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  };

  const initCartButtons = () => {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-cart-add]');
      if (!button) {
        return;
      }

      const product = parseJson(button.dataset.product || '{}', {});
      addToCart(product, getQuantityNearButton(button));
    });
  };

  const initGallery = () => {
    document.addEventListener('click', (event) => {
      const thumb = event.target.closest('[data-gallery-thumb]');
      if (!thumb) {
        return;
      }

      const main = document.querySelector('[data-gallery-main]');
      if (!main) {
        return;
      }

      main.src = thumb.dataset.galleryThumb;
      document.querySelectorAll('[data-gallery-thumb]').forEach((item) => item.classList.remove('active'));
      thumb.classList.add('active');
    });
  };

  const renderLocalOrders = () => {
    const wrapper = document.getElementById('localOrders');
    if (!wrapper) {
      return;
    }

    const orders = getOrders();
    if (!orders.length) {
      wrapper.innerHTML = '<div class="empty-state">Nenhum pedido foi simulado neste navegador ainda.</div>';
      return;
    }

    wrapper.innerHTML = orders.map((order) => `
      <article class="card order-card">
        <span class="status">${order.status || 'Pedido recebido'}</span>
        <h3>Pedido #${order.codigo}</h3>
        <p class="muted">${order.itensResumo || 'Itens do carrinho'} · ${order.pagamento || 'Pagamento a combinar'}</p>
        <p><strong>${money(order.total)}</strong></p>
        <a class="btn btn-soft" target="_blank" rel="noopener" href="${whatsappUrl(`Olá, quero falar sobre o pedido ${order.codigo}`)}">Falar com a loja</a>
      </article>
    `).join('');
  };

  window.ArteFlor = {
    cartKey: CART_KEY,
    orderKey: ORDER_KEY,
    money,
    getCart,
    setCart,
    getOrders,
    setOrders,
    addToCart,
    removeFromCart,
    updateQty,
    cartTotal,
    saveOrder,
    whatsappUrl,
    toast,
    updateCartCount
  };

  document.addEventListener('DOMContentLoaded', () => {
    initMenu();
    initCartButtons();
    initGallery();
    updateCartCount();
    renderLocalOrders();
  });
})();
