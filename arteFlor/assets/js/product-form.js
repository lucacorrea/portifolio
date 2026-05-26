(() => {
  const input = document.querySelector('[data-product-images-input]');
  const previewGrid = document.querySelector('[data-product-image-preview]');
  const mainPreview = document.querySelector('[data-product-main-preview]');
  const nameSource = document.querySelector('[data-product-preview-name-source]');
  const descriptionSource = document.querySelector('[data-product-preview-description-source]');
  const priceSource = document.querySelector('[data-product-preview-price-source]');
  const promoSource = document.querySelector('[data-product-preview-promo-source]');
  const categorySource = document.querySelector('[data-product-preview-category-source]');
  const statusSource = document.querySelector('[data-product-preview-status-source]');
  const stockSource = document.querySelector('[data-product-preview-stock-source]');
  const minStockSource = document.querySelector('[data-product-preview-min-stock-source]');
  const flagSources = document.querySelectorAll('[data-product-preview-flag-source]');
  const colorList = document.querySelector('[data-product-color-list]');
  const colorTemplate = document.querySelector('[data-product-color-template]');
  const colorAddButton = document.querySelector('[data-product-color-add]');
  const nameTarget = document.querySelector('[data-product-preview-name]');
  const descriptionTarget = document.querySelector('[data-product-preview-description]');
  const priceTarget = document.querySelector('[data-product-preview-price]');
  const originalPriceTarget = document.querySelector('[data-product-preview-original-price]');
  const categoryTarget = document.querySelector('[data-product-preview-category]');
  const statusTarget = document.querySelector('[data-product-preview-status]');
  const stockValueTarget = document.querySelector('[data-product-preview-stock-value]');
  const minStockValueTarget = document.querySelector('[data-product-preview-min-stock-value]');
  const stockFillTarget = document.querySelector('[data-product-preview-stock-fill]');
  const stockLabelTarget = document.querySelector('[data-product-preview-stock-label]');
  const flagTargets = new Map(
    Array.from(document.querySelectorAll('[data-product-preview-flag]')).map((item) => [
      item.dataset.productPreviewFlag,
      item
    ])
  );
  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
  const maxFiles = 8;
  const maxBytes = 5 * 1024 * 1024;
  const initialMainPreview = mainPreview?.innerHTML || '';
  let objectUrls = [];

  const money = (value) => {
    if (window.ArteFlor?.formatMoney) {
      return window.ArteFlor.formatMoney(value);
    }

    return Number(value || 0).toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    });
  };

  const parseMoney = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return 0;

    const normalized = raw.includes(',')
      ? raw.replace(/\./g, '').replace(',', '.')
      : raw;

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
  };

  const parseInteger = (value) => {
    const parsed = Number.parseInt(String(value || '0'), 10);
    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
  };

  const selectedText = (select, fallback = '-') => {
    if (!(select instanceof HTMLSelectElement)) return fallback;
    return select.options[select.selectedIndex]?.textContent?.trim() || fallback;
  };

  const inventoryStatus = (stock, minStock) => {
    if (stock <= 0) return 'sem_estoque';
    if (minStock <= 0) return 'normal';
    if (stock <= minStock) return 'baixo';
    if (stock <= minStock * 2) return 'medio';
    return 'normal';
  };

  const inventoryLabel = (status) => ({
    sem_estoque: 'Sem estoque',
    baixo: 'Estoque baixo',
    medio: 'Estoque médio',
    normal: 'Estoque normal'
  }[status] || 'Estoque');

  const inventoryBadgeClass = (status) => ({
    sem_estoque: 'admin-badge-danger',
    baixo: 'admin-badge-warn',
    medio: 'admin-badge-info',
    normal: 'admin-badge-ok'
  }[status] || 'admin-badge-soft');

  const inventoryPercent = (stock, minStock) => {
    if (stock <= 0) return 0;
    if (minStock <= 0) return 100;
    return Math.min(100, Math.max(8, Math.round((stock / Math.max(1, minStock * 2)) * 100)));
  };

  const setBadgeClass = (element, className) => {
    if (!element) return;
    element.classList.remove('admin-badge-ok', 'admin-badge-warn', 'admin-badge-danger', 'admin-badge-info', 'admin-badge-soft');
    element.classList.add(className);
  };

  const bindColorRow = (row) => {
    if (!(row instanceof HTMLElement)) return;

    const hexInput = row.querySelector('[data-product-color-hex]');
    const swatch = row.querySelector('.product-color-admin-swatch');
    const updateSwatch = () => {
      if (swatch && hexInput instanceof HTMLInputElement) {
        swatch.style.setProperty('--color', hexInput.value || '#FFFFFF');
      }
    };

    hexInput?.addEventListener('input', updateSwatch);
    updateSwatch();
  };

  const addColorRow = () => {
    if (!colorList || !(colorTemplate instanceof HTMLTemplateElement)) return;

    const nextIndex = String(colorList.querySelectorAll('[data-product-color-row]').length + Date.now());
    const wrapper = document.createElement('div');
    wrapper.innerHTML = colorTemplate.innerHTML.replaceAll('__INDEX__', nextIndex).trim();
    const row = wrapper.firstElementChild;
    if (!(row instanceof HTMLElement)) return;

    colorList.appendChild(row);
    bindColorRow(row);
    row.querySelector('input[name*="[nome]"]')?.focus();
  };

  const revokeObjectUrls = () => {
    objectUrls.forEach((url) => URL.revokeObjectURL(url));
    objectUrls = [];
  };

  const clearGrid = () => {
    if (!previewGrid) return;
    previewGrid.innerHTML = '';
    previewGrid.hidden = true;
  };

  const makeFigure = (file, url, index, isPrimary = false) => {
    const figure = document.createElement('figure');
    const img = document.createElement('img');
    const caption = document.createElement('figcaption');

    img.src = url;
    img.alt = file.name || `Imagem ${index + 1}`;
    caption.textContent = isPrimary
      ? `Principal: ${file.name || 'Imagem selecionada'}`
      : `${index + 1}. ${file.name || 'Imagem selecionada'}`;

    if (isPrimary) {
      figure.classList.add('is-primary');
    }
    figure.append(img, caption);
    return figure;
  };

  const makeRejectedItem = (file, reason) => {
    const item = document.createElement('figure');
    const placeholder = document.createElement('div');
    const caption = document.createElement('figcaption');

    placeholder.className = 'admin-upload-placeholder product-rejected-preview';
    placeholder.textContent = '!';
    caption.textContent = `${file.name || 'Arquivo'}: ${reason}`;

    item.classList.add('product-preview-rejected');
    item.append(placeholder, caption);
    return item;
  };

  const updateTextPreview = () => {
    if (nameTarget) {
      nameTarget.textContent = nameSource?.value?.trim() || 'Novo produto';
    }

    if (descriptionTarget) {
      descriptionTarget.textContent = descriptionSource?.value?.trim() || 'Produto pronto para catálogo.';
    }

    if (priceTarget) {
      const price = parseMoney(priceSource?.value);
      const promo = parseMoney(promoSource?.value);
      priceTarget.textContent = money(promo > 0 ? promo : price);
      if (originalPriceTarget) {
        originalPriceTarget.hidden = !(promo > 0 && price > 0);
        originalPriceTarget.textContent = promo > 0 && price > 0 ? `Original: ${money(price)}` : '';
      }
    }

    if (categoryTarget) {
      categoryTarget.textContent = selectedText(categorySource, 'Buquês');
    }

    if (statusTarget) {
      statusTarget.textContent = selectedText(statusSource, 'Disponível');
    }

    const stock = parseInteger(stockSource?.value);
    const minStock = parseInteger(minStockSource?.value);
    const status = inventoryStatus(stock, minStock);

    if (stockValueTarget) {
      stockValueTarget.textContent = `Estoque: ${stock} un.`;
    }

    if (minStockValueTarget) {
      minStockValueTarget.textContent = `Mínimo: ${minStock} un.`;
    }

    if (stockFillTarget) {
      stockFillTarget.classList.remove('sem_estoque', 'baixo', 'medio', 'normal');
      stockFillTarget.classList.add(status);
      stockFillTarget.style.width = `${inventoryPercent(stock, minStock)}%`;
    }

    if (stockLabelTarget) {
      stockLabelTarget.textContent = inventoryLabel(status);
      setBadgeClass(stockLabelTarget, inventoryBadgeClass(status));
    }

    flagSources.forEach((source) => {
      const target = flagTargets.get(source.dataset.productPreviewFlagSource);
      if (!target) return;

      target.classList.toggle('is-active', source.checked);
      target.classList.toggle('is-muted', !source.checked);
    });
  };

  const updateImagePreview = () => {
    if (!input || !previewGrid) return;

    revokeObjectUrls();
    clearGrid();

    const files = Array.from(input.files || []);
    if (!files.length) {
      if (mainPreview) {
        mainPreview.innerHTML = initialMainPreview;
      }
      return;
    }

    previewGrid.hidden = false;

    if (files.length > maxFiles && window.ArteFlor?.toast) {
      window.ArteFlor.toast('O limite é de 8 imagens por envio. Remova o excesso antes de salvar.', 'warning');
    }

    let firstValidUrl = '';

    files.forEach((file, index) => {
      if (index >= maxFiles) {
        previewGrid.appendChild(makeRejectedItem(file, 'excede o limite de 8 imagens'));
        return;
      }

      if (!allowedTypes.includes(file.type)) {
        previewGrid.appendChild(makeRejectedItem(file, 'formato não permitido'));
        return;
      }

      if (file.size > maxBytes) {
        previewGrid.appendChild(makeRejectedItem(file, 'maior que 5 MB'));
        return;
      }

      const url = URL.createObjectURL(file);
      const isPrimary = !firstValidUrl;
      objectUrls.push(url);
      if (!firstValidUrl) {
        firstValidUrl = url;
      }
      previewGrid.appendChild(makeFigure(file, url, index, isPrimary));
    });

    if (mainPreview && firstValidUrl) {
      mainPreview.innerHTML = `<img src="${firstValidUrl}" alt="Preview da primeira imagem selecionada">`;
    }
  };

  input?.addEventListener('change', updateImagePreview);
  [nameSource, descriptionSource, priceSource, promoSource, stockSource, minStockSource].forEach((field) => {
    field?.addEventListener('input', updateTextPreview);
  });
  [categorySource, statusSource].forEach((field) => {
    field?.addEventListener('change', updateTextPreview);
  });
  flagSources.forEach((field) => {
    field.addEventListener('change', updateTextPreview);
  });
  colorList?.querySelectorAll('[data-product-color-row]').forEach(bindColorRow);
  colorAddButton?.addEventListener('click', addColorRow);

  window.addEventListener('beforeunload', revokeObjectUrls);
  updateTextPreview();
})();
