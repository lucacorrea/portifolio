document.addEventListener('DOMContentLoaded', () => {
    const target = document.querySelector('[data-whatsapp-qr-code]');

    if (!target || !window.QRCode) {
        return;
    }

    const code = target.dataset.whatsappQrCode || '';

    if (!code) {
        return;
    }

    target.textContent = '';

    try {
        new window.QRCode(target, {
            text: code,
            width: 280,
            height: 280,
            colorDark: '#1a2c3e',
            colorLight: '#ffffff',
            correctLevel: window.QRCode.CorrectLevel.H,
        });

        target.removeAttribute('title');
    } catch (error) {
        target.textContent = 'Não foi possível renderizar o QR Code.';
    }
});
