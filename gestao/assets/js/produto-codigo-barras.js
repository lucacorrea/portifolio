document.addEventListener('DOMContentLoaded', () => {
  const barcodeInput = document.getElementById('productBarcode');
  const scanButton = document.getElementById('scanBarcodeButton');
  const messageBox = document.getElementById('barcodeLookupMessage');
  const scannerBackdrop = document.getElementById('productScannerBackdrop');
  const scannerVideo = document.getElementById('productScannerVideo');
  const scannerCancel = document.getElementById('productScannerCancel');
  const scannerStatus = document.getElementById('productScannerStatus');

  let scanLocked = false;
  let lastScannedCode = '';
  let lastScannedAt = 0;
  let scannerStream = null;
  let scannerLoop = 0;
  let zxingControls = null;
  let zxingLoading = null;

  if (!barcodeInput || !scanButton || !scannerBackdrop || !scannerVideo) {
    return;
  }

  function normalizeBarcode(value) {
    const text = String(value || '').trim();
    const numeric = text.replace(/[\s-]+/g, '');

    return /^\d+$/.test(numeric) ? numeric : text;
  }

  function setScannerMessage(type, message) {
    if (!messageBox) return;
    messageBox.hidden = false;
    messageBox.className = `product-lookup-message ${type || ''}`.trim();
    messageBox.replaceChildren(document.createTextNode(message));
  }

  function setScannedValue(code) {
    const value = normalizeBarcode(code);
    if (!value) return;

    barcodeInput.value = value;
    barcodeInput.dispatchEvent(new Event('input', { bubbles: true }));
    setScannerMessage('success', 'Código lido pela câmera e preenchido no campo.');
    barcodeInput.focus({ preventScroll: true });
    barcodeInput.select();
  }

  async function openBarcodeScanner() {
    if (scanLocked) return;

    if (!window.isSecureContext && location.hostname !== 'localhost') {
      setScannerMessage('warning', 'A câmera exige acesso HTTPS. Digite o código manualmente.');
      return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      setScannerMessage('warning', 'Este dispositivo não possui câmera disponível.');
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
              handleDetectedBarcode(codes[0].rawValue || '');
              return;
            }
          } catch (error) {
            stopBarcodeScanner();
            setScannerMessage('warning', 'Não foi possível ler pela câmera. Digite o código manualmente.');
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
        handleDetectedBarcode(text);
      });
    } catch (error) {
      stopBarcodeScanner();
      const message = error?.name === 'NotAllowedError'
        ? 'Permissão da câmera negada.'
        : 'Não foi possível acessar a câmera.';
      setScannerMessage('warning', message);
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
    scannerVideo.srcObject = null;
    scannerBackdrop.classList.remove('open');
    scannerBackdrop.setAttribute('aria-hidden', 'true');
    scanLocked = false;
    barcodeInput.focus({ preventScroll: true });
  }

  function handleDetectedBarcode(code) {
    const normalized = normalizeBarcode(code);
    if (!normalized) return;

    if (normalized === lastScannedCode && Date.now() - lastScannedAt < 3000) {
      return;
    }

    lastScannedCode = normalized;
    lastScannedAt = Date.now();
    stopBarcodeScanner();
    setScannedValue(normalized);
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

  scanButton.addEventListener('click', openBarcodeScanner);
  scannerCancel?.addEventListener('click', stopBarcodeScanner);
  scannerBackdrop.addEventListener('click', (event) => {
    if (event.target === scannerBackdrop) stopBarcodeScanner();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && scannerBackdrop.classList.contains('open')) {
      stopBarcodeScanner();
    }
  });
});
