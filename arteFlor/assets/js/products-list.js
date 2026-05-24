(() => {
  const payload = document.getElementById('productListPayload');
  const modal = document.querySelector('[data-product-modal]');
  const stockModal = document.querySelector('[data-stock-modal]');

  const bindStockModal = () => {
    if (!stockModal) return;

    const productId = stockModal.querySelector('[data-stock-product-id]');
    const name = stockModal.querySelector('[data-stock-product-name]');
    const sku = stockModal.querySelector('[data-stock-product-sku]');
    const stock = stockModal.querySelector('[data-stock-product-current]');
    const minStock = stockModal.querySelector('[data-stock-product-min]');
    let lastFocus = null;

    const open = (button) => {
      lastFocus = button;
      if (productId) productId.value = button.dataset.productId || '';
      if (name) name.textContent = button.dataset.productName || '-';
      if (sku) sku.textContent = button.dataset.productSku || '-';
      if (stock) stock.textContent = `${button.dataset.productStock || 0} un.`;
      if (minStock) minStock.textContent = `${button.dataset.productMinStock || 0} un.`;
      stockModal.hidden = false;
      document.body.classList.add('admin-modal-open');
      stockModal.querySelector('select, input, button')?.focus();
    };

    const close = () => {
      stockModal.hidden = true;
      document.body.classList.remove('admin-modal-open');
      if (lastFocus instanceof HTMLElement) {
        lastFocus.focus();
      }
    };

    document.addEventListener('click', (event) => {
      const source = event.target;
      if (!(source instanceof HTMLElement)) return;

      const opener = source.closest('[data-stock-modal-open]');
      if (opener instanceof HTMLElement) {
        open(opener);
        return;
      }

      if (source.closest('[data-stock-modal-close]') || source === stockModal) {
        close();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !stockModal.hidden) {
        close();
      }
    });
  };

  bindStockModal();

  if (!payload || !modal) return;

  let products = [];
  try {
    products = JSON.parse(payload.textContent || '[]');
  } catch (error) {
    products = [];
  }

  const byId = new Map(products.map((product) => [String(product.id), product]));
  const media = modal.querySelector('[data-product-modal-media]');
  const title = modal.querySelector('[data-product-modal-title]');
  const category = modal.querySelector('[data-product-modal-category]');
  const description = modal.querySelector('[data-product-modal-description]');
  const price = modal.querySelector('[data-product-modal-price]');
  const promo = modal.querySelector('[data-product-modal-promo]');
  const sku = modal.querySelector('[data-product-modal-sku]');
  const slug = modal.querySelector('[data-product-modal-slug]');
  const status = modal.querySelector('[data-product-modal-status]');
  const stock = modal.querySelector('[data-product-modal-stock]');
  const minStock = modal.querySelector('[data-product-modal-min-stock]');
  const stockFill = modal.querySelector('[data-product-modal-stock-fill]');
  const stockLabel = modal.querySelector('[data-product-modal-stock-label]');
  const highlight = modal.querySelector('[data-product-modal-highlight]');
  const order = modal.querySelector('[data-product-modal-order]');
  const tags = modal.querySelector('[data-product-modal-tags]');
  const shortDescription = modal.querySelector('[data-product-modal-short]');
  const fullDescription = modal.querySelector('[data-product-modal-full]');
  const thumbs = modal.querySelector('[data-product-modal-thumbs]');
  const edit = modal.querySelector('[data-product-modal-edit]');
  let lastFocus = null;
  let currentProduct = null;

  const escapeHtml = (value) => {
    if (window.ArteFlor?.escapeHtml) {
      return window.ArteFlor.escapeHtml(value);
    }

    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  };

  const setBadgeClass = (element, className) => {
    if (!element) return;

    element.classList.remove('admin-badge-ok', 'admin-badge-warn', 'admin-badge-danger', 'admin-badge-info', 'admin-badge-soft');
    element.classList.add(className || 'admin-badge-soft');
  };

  const renderMedia = (product, selectedUrl = '') => {
    if (!media) return;

    const images = Array.isArray(product.images) ? product.images : [];
    const selected = selectedUrl || images[0]?.url || '';

    media.innerHTML = selected
      ? `<img src="${escapeHtml(selected)}" alt="${escapeHtml(product.nome || 'Produto')}">`
      : '<div class="admin-upload-placeholder">A&F</div>';
  };

  const renderThumbs = (product) => {
    if (!thumbs) return;

    const images = Array.isArray(product.images) ? product.images : [];
    thumbs.innerHTML = '';

    images.forEach((image, index) => {
      if (!image.url) return;

      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.productThumb = image.url;
      button.setAttribute('aria-label', `Ver imagem ${index + 1}`);
      if (index === 0) {
        button.classList.add('active');
      }

      const img = document.createElement('img');
      img.src = image.url;
      img.alt = image.alt || product.nome || 'Imagem do produto';
      button.appendChild(img);
      thumbs.appendChild(button);
    });

    thumbs.hidden = images.length === 0;
  };

  const openModal = (product, source) => {
    currentProduct = product;
    lastFocus = source || document.activeElement;
    renderMedia(product);
    renderThumbs(product);

    if (title) title.textContent = product.nome || 'Produto';
    if (category) category.textContent = product.categoria || 'Sem categoria';
    if (description) description.textContent = product.descricaoCurta || product.descricaoCompleta || 'Produto sem descrição cadastrada.';
    if (price) price.textContent = product.precoPromocional || product.preco || 'R$ 0,00';
    if (promo) {
      promo.hidden = !product.precoPromocional;
      promo.textContent = product.precoPromocional ? `Preço original: ${product.preco}` : '';
    }
    if (sku) sku.textContent = product.sku || '-';
    if (slug) slug.textContent = product.slug || '-';
    if (status) status.textContent = product.status || '-';
    if (stock) stock.textContent = `Estoque: ${product.estoque ?? 0} un.`;
    if (minStock) minStock.textContent = `Mínimo: ${product.estoqueMinimo ?? 0} un.`;
    if (stockFill) {
      stockFill.classList.remove('sem_estoque', 'baixo', 'medio', 'normal');
      stockFill.classList.add(product.stockStatus || 'normal');
      stockFill.style.width = `${Number(product.stockPercent || 0)}%`;
    }
    if (stockLabel) {
      stockLabel.textContent = product.stockLabel || 'Estoque';
      setBadgeClass(stockLabel, product.stockBadgeClass);
    }
    if (highlight) highlight.textContent = product.destaque || 'Normal';
    if (order) order.textContent = product.sobEncomenda || 'Não';
    if (tags) {
      const productTags = Array.isArray(product.tags) ? product.tags.filter(Boolean) : [];
      tags.hidden = productTags.length === 0;
      tags.innerHTML = productTags.map((tag) => `<span>${escapeHtml(tag)}</span>`).join('');
    }
    if (shortDescription) shortDescription.textContent = product.descricaoCurta || 'Produto sem descrição curta cadastrada.';
    if (fullDescription) fullDescription.textContent = product.descricaoCompleta || 'Produto sem descrição completa cadastrada.';
    if (edit) edit.href = product.editUrl || edit.href;

    modal.hidden = false;
    document.body.classList.add('admin-modal-open');
    modal.querySelector('[data-product-modal-close]')?.focus();
  };

  const closeModal = () => {
    modal.hidden = true;
    document.body.classList.remove('admin-modal-open');
    if (lastFocus instanceof HTMLElement) {
      lastFocus.focus();
    }
  };

  document.addEventListener('click', (event) => {
    const source = event.target;
    if (!(source instanceof HTMLElement)) return;

    const opener = source.closest('[data-product-modal-open]');
    if (opener instanceof HTMLElement) {
      const product = byId.get(String(opener.dataset.productModalOpen || ''));
      if (product) {
        openModal(product, opener);
      }
      return;
    }

    if (source.closest('[data-product-modal-close]')) {
      closeModal();
      return;
    }

    if (source === modal) {
      closeModal();
      return;
    }

    const thumb = source.closest('[data-product-thumb]');
    if (thumb instanceof HTMLElement) {
      const product = currentProduct || products[0] || {};
      modal.querySelectorAll('[data-product-thumb]').forEach((item) => item.classList.remove('active'));
      thumb.classList.add('active');
      renderMedia(product, thumb.dataset.productThumb || '');
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });
})();
