(() => {
  const input = document.querySelector('[data-product-images-input]');
  const previewGrid = document.querySelector('[data-product-image-preview]');
  const mainPreview = document.querySelector('[data-product-main-preview]');
  const nameSource = document.querySelector('[data-product-preview-name-source]');
  const descriptionSource = document.querySelector('[data-product-preview-description-source]');
  const priceSource = document.querySelector('[data-product-preview-price-source]');
  const promoSource = document.querySelector('[data-product-preview-promo-source]');
  const nameTarget = document.querySelector('[data-product-preview-name]');
  const descriptionTarget = document.querySelector('[data-product-preview-description]');
  const priceTarget = document.querySelector('[data-product-preview-price]');
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

  const revokeObjectUrls = () => {
    objectUrls.forEach((url) => URL.revokeObjectURL(url));
    objectUrls = [];
  };

  const clearGrid = () => {
    if (!previewGrid) return;
    previewGrid.innerHTML = '';
    previewGrid.hidden = true;
  };

  const makeFigure = (file, url, index) => {
    const figure = document.createElement('figure');
    const img = document.createElement('img');
    const caption = document.createElement('figcaption');

    img.src = url;
    img.alt = file.name || `Imagem ${index + 1}`;
    caption.textContent = `${index + 1}. ${file.name || 'Imagem selecionada'}`;

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
    }
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
      objectUrls.push(url);
      if (!firstValidUrl) {
        firstValidUrl = url;
      }
      previewGrid.appendChild(makeFigure(file, url, index));
    });

    if (mainPreview && firstValidUrl) {
      mainPreview.innerHTML = `<img src="${firstValidUrl}" alt="Preview da primeira imagem selecionada">`;
    }
  };

  input?.addEventListener('change', updateImagePreview);
  [nameSource, descriptionSource, priceSource, promoSource].forEach((field) => {
    field?.addEventListener('input', updateTextPreview);
  });

  window.addEventListener('beforeunload', revokeObjectUrls);
  updateTextPreview();
})();
