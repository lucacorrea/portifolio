<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Consulta de Produto — ERP Elétrica</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#19335a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Consultar ERP">
    <link rel="apple-touch-icon" href="public/img/app-icon.png">
    <link rel="manifest" href="manifest.json">

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --azul-principal: #294f87;
            --azul-escuro: #19335a;
            --azul-claro-bg: #bac5d4;
            --amarelo: #f0c21b;
            --branco: #ffffff;
            --cinza-borda: #d8dee7;
            --cinza-texto: #62708a;
            --cinza-suave: #eef3f9;
            --verde: #1f9d55;
            --vermelho: #d64545;
            --sombra: 0 18px 45px rgba(20, 39, 71, 0.18);
            --radius-lg: 22px;
            --radius-md: 14px;
            --radius-sm: 10px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--azul-claro-bg);
            color: var(--azul-escuro);
            min-height: 100vh;
            padding: 24px 16px;
        }

        .page-wrap {
            width: 100%;
            min-height: calc(100vh - 48px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: 720px;
            background: var(--branco);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--sombra);
        }

        .card-top {
            background: var(--azul-principal);
            padding: 30px 24px 26px;
            text-align: center;
        }

        .brand-box {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 110px;
        }

        .brand-box img {
            max-width: 280px;
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        .brand-fallback {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            gap: 8px;
        }

        .brand-fallback .brand-main {
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .brand-fallback .brand-sub {
            font-size: 0.92rem;
            opacity: 0.9;
        }

        .yellow-line {
            height: 6px;
            background: var(--amarelo);
        }

        .card-body {
            padding: 34px 34px 30px;
        }

        .title {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            color: var(--azul-escuro);
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: var(--cinza-texto);
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 28px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 0.94rem;
            font-weight: 800;
            color: var(--azul-escuro);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .field input {
            width: 100%;
            height: 58px;
            border: 1px solid var(--cinza-borda);
            border-radius: var(--radius-sm);
            background: #f7f9fc;
            padding: 0 16px;
            font-size: 1.15rem;
            color: var(--azul-escuro);
            outline: none;
            transition: .2s ease;
        }

        .field input:focus {
            border-color: var(--azul-principal);
            box-shadow: 0 0 0 4px rgba(41, 79, 135, 0.12);
            background: #fff;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 12px 0 20px;
        }

        .btn {
            height: 56px;
            border: 0;
            border-radius: 12px;
            font-size: 1.02rem;
            font-weight: 800;
            cursor: pointer;
            transition: .2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .btn-primary {
            background: var(--azul-principal);
            color: #fff;
        }

        .btn-primary:hover {
            background: #234273;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #e9eef5;
            color: var(--azul-escuro);
        }

        .btn-secondary:hover {
            background: #dde6f1;
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none !important;
        }

        .camera-panel {
            border: 1px solid var(--cinza-borda);
            border-radius: 16px;
            background: #f8fbff;
            padding: 14px;
            margin-bottom: 18px;
        }

        .camera-panel.hidden {
            display: none;
        }

        #reader {
            width: 100%;
            overflow: hidden;
            border-radius: 14px;
            background: #000;
            min-height: 260px;
        }

        .camera-tip {
            margin-top: 10px;
            text-align: center;
            color: var(--cinza-texto);
            font-size: 0.92rem;
        }

        .status-box {
            display: none;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-weight: 700;
            line-height: 1.45;
        }

        .status-box.show {
            display: block;
        }

        .status-info {
            background: #eef4ff;
            color: #2d4f85;
            border: 1px solid #d6e3ff;
        }

        .status-success {
            background: #edf9f1;
            color: #167a42;
            border: 1px solid #c9efd5;
        }

        .status-error {
            background: #fff1f1;
            color: #b73636;
            border: 1px solid #ffd0d0;
        }

        .empty-state {
            border: 1px dashed #cfd8e4;
            border-radius: 18px;
            padding: 26px 20px;
            text-align: center;
            background: #fafcff;
        }

        .empty-state.hidden {
            display: none;
        }

        .empty-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #edf3fa;
            color: var(--azul-principal);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 2rem;
            font-weight: 900;
        }

        .empty-state h3 {
            font-size: 1.15rem;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: var(--cinza-texto);
            line-height: 1.5;
        }

        .product-card {
            display: none;
            grid-template-columns: 220px 1fr;
            gap: 22px;
            align-items: stretch;
            border: 1px solid var(--cinza-borda);
            border-radius: 18px;
            padding: 20px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(31, 56, 98, 0.08);
        }

        .product-card.show {
            display: grid;
        }

        .product-image-wrap {
            background: #f7f9fc;
            border: 1px solid #e4eaf2;
            border-radius: 16px;
            padding: 12px;
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image-wrap img {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            display: block;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .badge-found {
            align-self: flex-start;
            background: #e9f8ee;
            color: var(--verde);
            border: 1px solid #c7ecd5;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .product-name {
            font-size: 1.65rem;
            line-height: 1.25;
            color: var(--azul-escuro);
            font-weight: 800;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .meta-item {
            background: #f6f9fd;
            border: 1px solid #e4ebf3;
            border-radius: 12px;
            padding: 12px 14px;
        }

        .meta-label {
            display: block;
            color: var(--cinza-texto);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .meta-value {
            font-size: 1rem;
            color: var(--azul-escuro);
            font-weight: 800;
            word-break: break-word;
        }

        .price-box {
            margin-top: 2px;
            background: linear-gradient(135deg, #294f87 0%, #1d3d6b 100%);
            border-radius: 16px;
            padding: 18px 20px;
            color: #fff;
        }

        .price-label {
            display: block;
            font-size: 0.84rem;
            opacity: 0.88;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .price-value {
            font-size: 2rem;
            font-weight: 900;
            color: #fff;
        }

        .footer-note {
            text-align: center;
            margin-top: 22px;
            color: var(--cinza-texto);
            font-size: 0.92rem;
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 26px 18px 22px;
            }

            .title {
                font-size: 1.6rem;
            }

            .actions {
                grid-template-columns: 1fr;
            }

            .product-card {
                grid-template-columns: 1fr;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .product-image-wrap {
                min-height: 200px;
            }

            .price-value {
                font-size: 1.7rem;
            }
        }
    </style>
</head>

<body>
    <div class="page-wrap">
        <main class="card">
            <div class="card-top">
                <div class="brand-box">
                    <img
                        src="assets/img/logo-centro-eletricista.png"
                        alt="Centro do Eletricista"
                        onerror="this.style.display='none'; document.getElementById('brandFallback').style.display='flex';">
                    <div class="brand-fallback" id="brandFallback">
                        <div class="brand-main">CENTRO DO ELETRICISTA</div>
                        <div class="brand-sub">Consulta rápida de produtos</div>
                    </div>
                </div>
            </div>

            <div class="yellow-line"></div>

            <div class="card-body">
                <h1 class="title">CONSULTA DE PRODUTO</h1>
                <p class="subtitle">
                    Aponte a câmera para o código de barras do produto ou digite o código manualmente.
                </p>

                <div class="field">
                    <label for="codigoManual">Código de barras</label>
                    <input
                        type="text"
                        id="codigoManual"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="Leia ou digite o código do produto">
                </div>

                <div class="actions">
                    <button class="btn btn-primary" id="btnAbrirCamera" type="button">Abrir leitor</button>
                    <button class="btn btn-secondary" id="btnPararCamera" type="button" disabled>Parar leitor</button>
                    <button class="btn btn-primary" id="btnConsultar" type="button">Consultar produto</button>
                    <button class="btn btn-secondary" id="btnLimpar" type="button">Limpar</button>
                </div>

                <div class="camera-panel hidden" id="cameraPanel">
                    <div id="reader"></div>
                    <div class="camera-tip">
                        Posicione o código de barras dentro da área de leitura.
                    </div>
                </div>

                <div class="status-box" id="statusBox"></div>

                <div class="empty-state" id="emptyState">
                    <div class="empty-icon">📦</div>
                    <h3>Aguardando consulta</h3>
                    <p>
                        Assim que o código for lido, o sistema mostrará aqui o nome, valor e imagem do produto.
                    </p>
                </div>

                <div class="product-card" id="productCard">
                    <div class="product-image-wrap">
                        <img id="produtoImagem" src="" alt="Imagem do produto">
                    </div>

                    <div class="product-info">
                        <span class="badge-found">Produto encontrado</span>

                        <h2 class="product-name" id="produtoNome">-</h2>

                        <div class="meta-grid">
                            <div class="meta-item">
                                <span class="meta-label">Código interno</span>
                                <span class="meta-value" id="produtoCodigo">-</span>
                            </div>

                            <div class="meta-item">
                                <span class="meta-label">Código de barras</span>
                                <span class="meta-value" id="produtoCean">-</span>
                            </div>

                            <div class="meta-item">
                                <span class="meta-label">Categoria</span>
                                <span class="meta-value" id="produtoCategoria">-</span>
                            </div>

                            <div class="meta-item">
                                <span class="meta-label">Unidade</span>
                                <span class="meta-value" id="produtoUnidade">-</span>
                            </div>

                            <div class="meta-item">
                                <span class="meta-label">Estoque Total</span>
                                <span class="meta-value text-primary" id="produtoEstoque">-</span>
                            </div>
                        </div>

                        <div class="price-box">
                            <span class="price-label">Valor de venda</span>
                            <div class="price-value" id="produtoValor">R$ 0,00</div>
                        </div>
                    </div>
                </div>

                <div class="footer-note">
                    Centro do Eletricista • Consulta rápida no ambiente da loja
                </div>
            </div>
        </main>
    </div>

    <div id="erp-toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 11000;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/corporate.js"></script>
    <script src="https://unpkg.com/html5-qrcode" defer></script>
    <script>
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').then(reg => {
                    console.log('SW Registered for PWA');
                }).catch(err => console.log('SW Registration failed', err));
            });
        }

        const apiUrl = 'api/consultar_produto.php';

        const codigoInput = document.getElementById('codigoManual');
        const btnAbrir = document.getElementById('btnAbrirCamera');
        const btnParar = document.getElementById('btnPararCamera');
        const btnConsultar = document.getElementById('btnConsultar');
        const btnLimpar = document.getElementById('btnLimpar');
        const cameraPanel = document.getElementById('cameraPanel');
        const statusBox = document.getElementById('statusBox');
        const emptyState = document.getElementById('emptyState');
        const productCard = document.getElementById('productCard');

        let html5QrCode = null;
        let cameraAtiva = false;
        let ultimoCodigo = '';
        let ultimoTempo = 0;

        function normalizarCodigo(valor) {
            return String(valor || '').trim();
        }

        function mostrarStatus(texto, tipo = 'info') {
            statusBox.className = 'status-box show';
            statusBox.textContent = texto;

            if (tipo === 'success') {
                statusBox.classList.add('status-success');
            } else if (tipo === 'error') {
                statusBox.classList.add('status-error');
            } else {
                statusBox.classList.add('status-info');
            }
        }

        function ocultarStatus() {
            statusBox.className = 'status-box';
            statusBox.textContent = '';
        }

        function limparResultado() {
            productCard.classList.remove('show');
            emptyState.classList.remove('hidden');
            document.getElementById('produtoImagem').src = '';
            document.getElementById('produtoNome').textContent = '-';
            document.getElementById('produtoCodigo').textContent = '-';
            document.getElementById('produtoCean').textContent = '-';
            document.getElementById('produtoCategoria').textContent = '-';
            document.getElementById('produtoUnidade').textContent = '-';
            document.getElementById('produtoValor').textContent = 'R$ 0,00';
        }

        function preencherProduto(produto) {
            document.getElementById('produtoImagem').src = produto.imagem || 'public/img/no-image.png';
            document.getElementById('produtoImagem').alt = produto.nome || 'Produto';
            document.getElementById('produtoNome').textContent = produto.nome || '-';
            document.getElementById('produtoCodigo').textContent = produto.codigo || '-';
            document.getElementById('produtoCean').textContent = produto.cean || '-';
            document.getElementById('produtoCategoria').textContent = produto.categoria || '-';
            document.getElementById('produtoUnidade').textContent = produto.unidade || '-';
            document.getElementById('produtoEstoque').textContent = produto.estoque || '0';
            document.getElementById('produtoValor').textContent = produto.preco_venda_formatado || 'R$ 0,00';

            emptyState.classList.add('hidden');
            productCard.classList.add('show');
        }

        async function consultarProduto(codigo) {
            const codigoFinal = normalizarCodigo(codigo);

            if (!codigoFinal) {
                erpNotify('warning', 'Informe ou leia um código de barras.');
                limparResultado();
                return;
            }

            try {
                const formData = new FormData();
                formData.append('codigo', codigoFinal);

                const data = await erpFetch(apiUrl, {
                    method: 'POST',
                    body: formData
                });

                if (data && data.success) {
                    preencherProduto(data.data.produto);
                } else {
                    limparResultado();
                }
            } catch (error) {
                limparResultado();
                // erpFetch already notifies for server/network errors
            }
        }

        async function iniciarCamera() {
            if (cameraAtiva) return;

            if (typeof Html5Qrcode === 'undefined') {
                mostrarStatus('O leitor de código ainda não carregou. Recarregue a página.', 'error');
                return;
            }

            cameraPanel.classList.remove('hidden');
            ocultarStatus();

            try {
                html5QrCode = new Html5Qrcode('reader');

                await html5QrCode.start({
                        facingMode: 'environment'
                    }, {
                        fps: 10,
                        qrbox: {
                            width: 280,
                            height: 140
                        },
                        aspectRatio: 1.7,
                        rememberLastUsedCamera: true,
                        supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
                        formatsToSupport: [
                            Html5QrcodeSupportedFormats.EAN_13,
                            Html5QrcodeSupportedFormats.EAN_8,
                            Html5QrcodeSupportedFormats.UPC_A,
                            Html5QrcodeSupportedFormats.UPC_E,
                            Html5QrcodeSupportedFormats.CODE_128,
                            Html5QrcodeSupportedFormats.CODE_39
                        ]
                    },
                    (decodedText) => {
                        const codigoLido = normalizarCodigo(decodedText);
                        const agora = Date.now();

                        if (!codigoLido) return;

                        if (codigoLido === ultimoCodigo && (agora - ultimoTempo) < 2500) {
                            return;
                        }

                        ultimoCodigo = codigoLido;
                        ultimoTempo = agora;

                        codigoInput.value = codigoLido;

                        if (navigator.vibrate) {
                            navigator.vibrate(120);
                        }

                        consultarProduto(codigoLido);
                    },
                    () => {}
                );

                cameraAtiva = true;
                btnAbrir.disabled = true;
                btnParar.disabled = false;
                mostrarStatus('Leitor de código ativo. Aponte para o produto.', 'info');
            } catch (error) {
                cameraPanel.classList.add('hidden');
                mostrarStatus('Não foi possível acessar a câmera do aparelho.', 'error');
            }
        }

        async function pararCamera() {
            if (!html5QrCode || !cameraAtiva) return;

            try {
                await html5QrCode.stop();
                await html5QrCode.clear();
            } catch (e) {
                // ignora
            }

            html5QrCode = null;
            cameraAtiva = false;
            cameraPanel.classList.add('hidden');
            btnAbrir.disabled = false;
            btnParar.disabled = true;
            mostrarStatus('Leitor de código parado.', 'info');
        }

        btnAbrir.addEventListener('click', iniciarCamera);
        btnParar.addEventListener('click', pararCamera);

        btnConsultar.addEventListener('click', () => {
            consultarProduto(codigoInput.value);
        });

        btnLimpar.addEventListener('click', async () => {
            codigoInput.value = '';
            limparResultado();
            ocultarStatus();
            ultimoCodigo = '';
            ultimoTempo = 0;
        });

        codigoInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                consultarProduto(codigoInput.value);
            }
        });

        limparResultado();
    </script>
</body>

</html>