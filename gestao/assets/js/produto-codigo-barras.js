document.addEventListener('DOMContentLoaded', () => {
  const barcodeInput = document.getElementById('productBarcode');
  const lookupButton = document.getElementById('lookupBarcodeButton');
  const scanButton = document.getElementById('scanBarcodeButton');
  const messageBox = document.getElementById('barcodeLookupMessage');
  const form = barcodeInput?.closest('form');
  const saveButton = document.getElementById('saveProductButton');
  const currentProductId = Number(form?.querySelector('input[name="id"]')?.value || 0);
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const scannerBackdrop = document.getElementById('productScannerBackdrop');
  const scannerVideo = document.getElementById('productScannerVideo');
  const scannerCancel = document.getElementById('productScannerCancel');
  const scannerStatus = document.getElementById('productScannerStatus');

  let lookupController = null;
  let lookupInProgress = false;
  let scanLocked = false;
  let lastScannedCode = '';
  let lastScannedAt = 0;
  let scannerStream = null;
  let scannerLoop = 0;
  let zxingControls = null;
  let zxingLoading = null;
  let duplicateBarcode = '';
  const lookupCache = new Map();
  const lookupCacheTtl = 30000;
  const lookupMessages = {
    provider_not_configured: 'A integração externa ainda não foi configurada. Você pode cadastrar manualmente.',
    curl_unavailable: 'O servidor não possui suporte para consulta externa. Você pode cadastrar manualmente.',
    provider_timeout: 'A consulta demorou demais. Tente novamente ou continue manualmente.',
    providers_unavailable: 'As bases externas estão temporariamente indisponíveis. Você pode cadastrar o produto manualmente.',
    provider_unavailable: 'A consulta externa está indisponível. Continue manualmente.',
    rate_limit: 'O limite de consultas foi atingido. Tente novamente depois.',
    lookup_session_limit: 'O limite interno de consultas foi atingido. Você pode cadastrar manualmente.',
    product_not_found: 'Produto não encontrado na base externa. Preencha manualmente.',
    invalid_barcode: 'Código de barras inválido.'
  };

  if (!barcodeInput || !lookupButton || !scanButton || !messageBox || !form) {
    return;
  }

  function normalizeBarcode(value) {
    const raw = String(value || '').trim().replace(/[\s-]+/g, '');
    return /^\d+$/.test(raw) ? raw : String(value || '').trim();
  }

  function setLookupState(type, message) {
    messageBox.hidden = false;
    messageBox.className = `product-lookup-message ${type || ''}`.trim();
    messageBox.replaceChildren(document.createTextNode(message));
  }

  function setLoading(loading) {
    lookupInProgress = loading;
    lookupButton.disabled = loading;
    scanButton.disabled = loading;
    lookupButton.textContent = loading ? 'Consultando...' : 'Consultar';
  }

  function collectExistingFormValues() {
    return new Map(Array.from(form.elements)
      .filter((element) => element.id)
      .map((element) => [element.id, String(element.value || '').trim()]));
  }

  function fillField(id, value, overwrite = false) {
    const field = document.getElementById(id);
    if (!field || value === undefined || value === null) return false;

    const text = String(value);
    if (!overwrite && String(field.value || '').trim() !== '') return false;

    field.value = text;
    field.classList.add('product-autofilled');
    setTimeout(() => field.classList.remove('product-autofilled'), 1800);
    field.dispatchEvent(new Event('input', { bubbles: true }));
    return true;
  }

  function fillProductForm(product) {
    if (!product || typeof product !== 'object') return;

    const before = collectExistingFormValues();
    fillField('productBarcode', product.barcode || barcodeInput.value, true);
    fillField('productName', product.name || '');
    fillField('productSku', product.sku || product.barcode || '');
    fillField('productCategory', product.category || '');
    fillField('productBrand', product.brand || '');
    fillField('productDescription', product.description || '');
    fillField('productUnit', product.unit || '');
    fillField('productPackageQuantity', product.packageQuantity || '');
    fillField('productNcm', product.ncm || '');
    fillField('productCest', product.cest || '');
    fillField('productManufacturer', product.manufacturer || '');

    const source = document.getElementById('productDataSource');
    if (source && product.source) source.value = product.source;

    const externalImage = document.getElementById('productExternalImageUrl');
    const preview = document.getElementById('productPreview');
    if (externalImage && product.externalImageUrl) {
      externalImage.value = product.externalImageUrl;
      if (preview) preview.src = product.externalImageUrl;
    }

    if (!before.get('productSku') && product.barcode) {
      fillField('productSku', product.barcode, true);
    }
  }

  function showExistingProduct(product, editUrl) {
    const ownProduct = Number(product?.id || 0) > 0 && Number(product.id) === currentProductId;
    duplicateBarcode = ownProduct ? '' : normalizeBarcode(product?.barcode || barcodeInput.value);
    if (saveButton) saveButton.disabled = !ownProduct;

    const card = document.createElement('div');
    card.className = 'product-existing-card';
    const title = document.createElement('strong');
    title.textContent = ownProduct
      ? 'Este código pertence ao produto atual.'
      : `Este código já pertence ao produto "${product?.name || 'cadastrado'}".`;
    const meta = document.createElement('span');
    meta.textContent = `SKU ${product?.sku || '-'} · Código ${product?.barcode || '-'}`;
    card.append(title, meta);

    if (!ownProduct && editUrl) {
      const link = document.createElement('a');
      link.href = editUrl;
      link.className = 'secondary-btn';
      link.textContent = 'Abrir produto existente';
      card.append(link);
    }

    messageBox.hidden = false;
    messageBox.className = ownProduct ? 'product-lookup-message success' : 'product-lookup-message danger';
    messageBox.replaceChildren(card);
  }

  function providersText(data) {
    const providers = Array.isArray(data?.providers_checked) ? data.providers_checked.filter(Boolean) : [];
    return providers.length ? `\nProvedores consultados: ${providers.join(', ')}.` : '';
  }

  function prepareManualFallback(barcode) {
    fillField('productBarcode', barcode, true);
    const sku = document.getElementById('productSku');
    if (sku && !String(sku.value || '').trim()) {
      fillField('productSku', barcode, true);
    }
    const source = document.getElementById('productDataSource');
    if (source) source.value = 'manual';
    duplicateBarcode = '';
    if (saveButton) saveButton.disabled = false;
  }

  function focusProductName() {
    const name = document.getElementById('productName');
    name?.focus({ preventScroll: true });
  }

  function handleLookupPayload(data, ok, normalized) {
    if (data.source === 'local' && data.exists) {
      showExistingProduct(data.product, data.edit_url);
      return;
    }

    if (!ok || !data.success) {
      const code = String(data.code || 'provider_unavailable');
      const message = data.message || lookupMessages[code] || lookupMessages.provider_unavailable;

      if (code === 'product_not_found') {
        prepareManualFallback(data.barcode || normalized);
        setLookupState('warning', 'Produto não encontrado nas bases consultadas.\nComplete o cadastro manualmente.' + providersText(data));
        focusProductName();
        return;
      }

      if (['providers_unavailable', 'provider_timeout', 'provider_unavailable', 'rate_limit', 'lookup_session_limit', 'curl_unavailable', 'provider_not_configured'].includes(code)) {
        prepareManualFallback(data.barcode || normalized);
        setLookupState('warning', message + providersText(data));
        return;
      }

      setLookupState(code === 'invalid_barcode' ? 'danger' : 'warning', message + providersText(data));
      return;
    }

    duplicateBarcode = '';
    if (saveButton) saveButton.disabled = false;
    fillProductForm(data.product);
    setLookupState('success', data.source === 'open_food_facts'
      ? 'Produto encontrado no Open Food Facts. Confira os dados antes de salvar.'
      : 'Produto encontrado. Confira os dados antes de salvar.');
  }

  async function lookupBarcode(barcode, format = '') {
    const normalized = normalizeBarcode(barcode);
    if (!normalized) {
      setLookupState('danger', 'Código de barras inválido.');
      return;
    }

    if (lookupInProgress) return;

    const cached = lookupCache.get(normalized);
    if (cached && Date.now() - cached.timestamp < lookupCacheTtl) {
      handleLookupPayload(cached.data, cached.ok, normalized);
      return;
    }

    if (lookupController) lookupController.abort();
    lookupController = new AbortController();
    const timer = window.setTimeout(() => lookupController?.abort(), 12000);

    setLoading(true);
    setLookupState('warning', 'Consultando produto...');

    try {
      const response = await fetch('../api/produto-codigo-barras.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          barcode: normalized,
          format,
          csrf_token: csrfToken
        }),
        signal: lookupController.signal
      });

      let data = {};
      try {
        data = await response.json();
      } catch (error) {
        data = {};
      }

      if (data.source === 'local' && data.exists) {
        lookupCache.set(normalized, { timestamp: Date.now(), data, ok: response.ok });
        handleLookupPayload(data, response.ok, normalized);
        return;
      }

      lookupCache.set(normalized, { timestamp: Date.now(), data, ok: response.ok });
      handleLookupPayload(data, response.ok, normalized);
    } catch (error) {
      if (error.name !== 'AbortError') {
        setLookupState('warning', 'A consulta externa está indisponível. Continue manualmente.');
      } else {
        setLookupState('warning', 'A consulta demorou demais. Continue manualmente.');
      }
    } finally {
      window.clearTimeout(timer);
      setLoading(false);
      lookupController = null;
    }
  }

  async function openBarcodeScanner() {
    if (scanLocked) return;

    if (!window.isSecureContext && location.hostname !== 'localhost') {
      setLookupState('warning', 'A câmera exige acesso HTTPS. Digite o código manualmente.');
      return;
    }

    if (!navigator.mediaDevices?.getUserMedia || !scannerBackdrop || !scannerVideo) {
      setLookupState('warning', 'Este dispositivo não possui câmera disponível.');
      return;
    }

    scanLocked = true;
    scannerBackdrop.classList.add('open');
    scannerBackdrop.setAttribute('aria-hidden', 'false');
    if (scannerStatus) scannerStatus.textContent = 'Aponte a câmera para o código.';

    try {
      if ('BarcodeDetector' in window) {
        const detector = new BarcodeDetector({
          formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'qr_code']
        });
        scannerStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' } },
          audio: false
        });
        scannerVideo.srcObject = scannerStream;
        await scannerVideo.play();

        const readFrame = async () => {
          if (!scannerStream || !scanLocked) return;
          try {
            const codes = await detector.detect(scannerVideo);
            if (codes.length > 0) {
              const first = codes[0];
              handleDetectedBarcode(first.rawValue || '', first.format || '');
              return;
            }
          } catch (error) {
            stopBarcodeScanner();
            setLookupState('warning', 'Não foi possível acessar a câmera.');
            return;
          }
          scannerLoop = requestAnimationFrame(readFrame);
        };

        scannerLoop = requestAnimationFrame(readFrame);
        return;
      }

      const zxing = await loadZxingOnce();
      const reader = new zxing.BrowserMultiFormatReader();
      zxingControls = await reader.decodeFromVideoDevice(null, scannerVideo, (result) => {
        if (!result || !scanLocked) return;
        const text = typeof result.getText === 'function' ? result.getText() : String(result.text || '');
        const format = result.getBarcodeFormat ? String(result.getBarcodeFormat()) : '';
        handleDetectedBarcode(text, format);
      });
    } catch (error) {
      stopBarcodeScanner();
      const message = error?.name === 'NotAllowedError'
        ? 'Permissão da câmera negada.'
        : 'Não foi possível acessar a câmera.';
      setLookupState('warning', message);
    }
  }

  function stopBarcodeScanner() {
    if (scannerLoop) {
      cancelAnimationFrame(scannerLoop);
      scannerLoop = 0;
    }
    if (zxingControls?.stop) {
      zxingControls.stop();
      zxingControls = null;
    }
    if (scannerStream) {
      scannerStream.getTracks().forEach((track) => track.stop());
      scannerStream = null;
    }
    if (scannerVideo) scannerVideo.srcObject = null;
    scannerBackdrop?.classList.remove('open');
    scannerBackdrop?.setAttribute('aria-hidden', 'true');
    scanLocked = false;
    barcodeInput.focus({ preventScroll: true });
  }

  function handleDetectedBarcode(code, format) {
    const normalized = normalizeBarcode(code);
    if (!normalized) return;

    if (normalized === lastScannedCode && Date.now() - lastScannedAt < 3000) {
      return;
    }

    lastScannedCode = normalized;
    lastScannedAt = Date.now();
    stopBarcodeScanner();
    barcodeInput.value = normalized;

    if (!/^\d+$/.test(normalized)) {
      setLookupState('warning', 'Consulta externa automática aceita somente EAN/GTIN numérico. Preencha manualmente.');
      return;
    }

    lookupBarcode(normalized, format);
  }

  function loadZxingOnce() {
    if (window.ZXingBrowser) return Promise.resolve(window.ZXingBrowser);
    if (zxingLoading) return zxingLoading;

    zxingLoading = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://unpkg.com/@zxing/browser@0.1.5/umd/index.min.js';
      script.async = true;
      script.onload = () => window.ZXingBrowser ? resolve(window.ZXingBrowser) : reject(new Error('ZXing indisponível'));
      script.onerror = reject;
      document.head.appendChild(script);
    });

    return zxingLoading;
  }

  lookupButton.addEventListener('click', () => lookupBarcode(barcodeInput.value));
  scanButton.addEventListener('click', openBarcodeScanner);
  scannerCancel?.addEventListener('click', stopBarcodeScanner);
  scannerBackdrop?.addEventListener('click', (event) => {
    if (event.target === scannerBackdrop) stopBarcodeScanner();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && scannerBackdrop?.classList.contains('open')) {
      stopBarcodeScanner();
    }
  });
  barcodeInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      lookupBarcode(barcodeInput.value);
    }
  });
  barcodeInput.addEventListener('input', () => {
    const current = normalizeBarcode(barcodeInput.value);
    if (duplicateBarcode && current !== duplicateBarcode) {
      duplicateBarcode = '';
      if (saveButton) saveButton.disabled = false;
      messageBox.hidden = true;
    }
  });
  form.addEventListener('submit', (event) => {
    if (duplicateBarcode && normalizeBarcode(barcodeInput.value) === duplicateBarcode) {
      event.preventDefault();
      setLookupState('danger', 'Produto já cadastrado. Abra o cadastro existente para editar.');
    }
  });
});
