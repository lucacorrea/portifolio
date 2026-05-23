(() => {
  const payload = document.getElementById('productListPayload');
  const modal = document.querySelector('[data-product-modal]');
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
  const status = modal.querySelector('[data-product-modal-status]');
  const stock = modal.querySelector('[data-product-modal-stock]');
  const highlight = modal.querySelector('[data-product-modal-highlight]');
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

    thumbs.hidden = images.length <= 1;
  };

  const openModal = (product, source) => {
    currentProduct = product;
    lastFocus = source || document.activeElement;
    renderMedia(product);
    renderThumbs(product);

    if (title) title.textContent = product.nome || 'Produto';
    if (category) category.textContent = product.categoria || 'Sem categoria';
    if (description) description.textContent = product.descricaoCompleta || product.descricaoCurta || 'Produto sem descrição cadastrada.';
    if (price) price.textContent = product.precoPromocional || product.preco || 'R$ 0,00';
    if (promo) {
      promo.hidden = !product.precoPromocional;
      promo.textContent = product.precoPromocional ? `Preço original: ${product.preco}` : '';
    }
    if (sku) sku.textContent = product.sku || '-';
    if (status) status.textContent = product.status || '-';
    if (stock) stock.textContent = `${product.estoque ?? 0} un. | mínimo ${product.estoqueMinimo ?? 0}`;
    if (highlight) highlight.textContent = `Destaque: ${product.destaque || 'Normal'} | Sob encomenda: ${product.sobEncomenda || 'Não'}`;
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
