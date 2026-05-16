<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <title>Leitor de Código e QR Code</title>

    <!-- PWA Support -->
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="public/img/app-icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --azul: #294f87;
            --azul-escuro: #18365f;
            --azul-fundo: #b9c4d3;
            --amarelo: #f2c318;
            --branco: #ffffff;
            --texto: #243a5a;
            --texto-suave: #687892;
            --borda: #d7dfeb;
            --cinza: #f5f8fc;
            --verde: #1f9d55;
            --vermelho: #cf4242;
            --sombra: 0 18px 45px rgba(17, 38, 70, .18);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--azul-fundo);
            color: var(--texto);
        }

        body {
            padding: 20px 14px;
        }

        .page {
            min-height: calc(100vh - 40px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: 720px;
            background: var(--branco);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: var(--sombra);
        }

        .top {
            background: linear-gradient(135deg, #294f87 0%, #18365f 100%);
            padding: 34px 22px 28px;
            text-align: center;
            position: relative;
        }

        .logo-wrap {
            min-height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-wrap img {
            max-width: 300px;
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        .logo-fallback {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .logo-fallback strong {
            font-size: 2rem;
            line-height: 1.1;
            font-weight: 900;
            letter-spacing: .5px;
        }

        .logo-fallback span {
            margin-top: 10px;
            opacity: .92;
            font-size: .96rem;
        }

        .bar {
            height: 6px;
            background: var(--amarelo);
        }

        .content {
            padding: 36px 30px 30px;
        }

        .title {
            text-align: center;
            font-size: 2rem;
            font-weight: 900;
            color: var(--azul-escuro);
            margin-bottom: 10px;
            line-height: 1.2;
        }

        .subtitle {
            text-align: center;
            color: var(--texto-suave);
            line-height: 1.65;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            margin-bottom: 10px;
            font-size: .92rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: var(--azul-escuro);
        }

        .field input {
            width: 100%;
            height: 64px;
            border: 1px solid var(--borda);
            border-radius: 16px;
            background: #f7f9fc;
            padding: 0 20px;
            font-size: 1.12rem;
            color: var(--texto);
            outline: none;
            transition: .2s ease;
            font-weight: 700;
        }

        .field input:focus {
            border-color: var(--azul);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(41, 79, 135, .12);
        }

        .produto-nao-encontrado {
            display: none;
            margin-top: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #fff3f3;
            border: 1px solid #ffd1d1;
            color: #bf3f3f;
            font-size: .94rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .produto-nao-encontrado.show {
            display: block;
        }

        .buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 10px;
        }

        .btn {
            height: 60px;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--azul);
            color: #fff;
        }

        .btn-primary:hover {
            background: #214273;
        }

        .btn-secondary {
            background: #e9eef6;
            color: var(--azul-escuro);
        }

        .btn-secondary:hover {
            background: #dce6f1;
        }

        .status {
            margin-top: 18px;
            display: none;
            border-radius: 16px;
            padding: 16px 18px;
            font-weight: 700;
            line-height: 1.5;
            font-size: .95rem;
        }

        .status.show {
            display: block;
        }

        .status.info {
            background: #eef4ff;
            border: 1px solid #d7e4ff;
            color: #2c4f86;
        }

        .status.error {
            background: #fff2f2;
            border: 1px solid #ffd7d7;
            color: #b33a3a;
        }

        .status.success {
            background: #edf9f1;
            border: 1px solid #caecd5;
            color: #1e7c47;
        }

        .tip-box {
            margin-top: 24px;
            background: #f8fbff;
            border: 1px solid #e1e9f2;
            border-radius: 18px;
            padding: 18px 20px;
        }

        .tip-box h3 {
            font-size: 1rem;
            color: var(--azul-escuro);
            margin-bottom: 8px;
        }

        .tip-box p {
            color: var(--texto-suave);
            line-height: 1.65;
            font-size: .96rem;
        }

        .camera-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: #000;
            display: none;
        }

        .camera-overlay.show {
            display: block;
        }

        .camera-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 14px;
            background: linear-gradient(to bottom, rgba(0, 0, 0, .78), rgba(0, 0, 0, 0));
        }

        .camera-title {
            color: #fff;
        }

        .camera-title strong {
            display: block;
            font-size: 1rem;
            margin-bottom: 2px;
        }

        .camera-title span {
            font-size: .88rem;
            opacity: .92;
        }

        .camera-close {
            border: none;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            width: 50px;
            height: 50px;
            border-radius: 15px;
            font-size: 1.4rem;
            cursor: pointer;
            backdrop-filter: blur(5px);
        }

        #reader {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #000;
        }

        #reader video,
        #reader canvas {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }

        .scan-guide {
            position: absolute;
            left: 50%;
            top: 50%;
            width: min(82vw, 420px);
            height: min(82vw, 420px);
            transform: translate(-50%, -50%);
            border: 3px solid rgba(255, 255, 255, .92);
            border-radius: 24px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, .30);
            pointer-events: none;
            z-index: 10;
        }

        .scan-line {
            position: absolute;
            left: 12px;
            right: 12px;
            top: 50%;
            height: 2px;
            background: #ff3b3b;
            box-shadow: 0 0 12px rgba(255, 59, 59, .9);
            animation: scanLine 2.2s linear infinite;
        }

        @keyframes scanLine {
            0% {
                transform: translateY(-120px);
            }

            50% {
                transform: translateY(120px);
            }

            100% {
                transform: translateY(-120px);
            }
        }

        .camera-footer {
            position: absolute;
            left: 14px;
            right: 14px;
            bottom: 18px;
            z-index: 20;
            background: rgba(0, 0, 0, .52);
            color: #fff;
            border-radius: 18px;
            padding: 14px 16px;
            text-align: center;
            font-size: .94rem;
            line-height: 1.45;
            backdrop-filter: blur(8px);
        }

        @media (max-width: 768px) {

            body {
                padding: 12px;
            }

            .content {
                padding: 26px 18px 22px;
            }

            .title {
                font-size: 1.55rem;
            }

            .subtitle {
                font-size: .95rem;
            }

            .buttons {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
            }

            .scan-guide {
                width: 86vw;
                height: 86vw;
            }

            @keyframes scanLine {
                0% {
                    transform: translateY(-90px);
                }

                50% {
                    transform: translateY(90px);
                }

                100% {
                    transform: translateY(-90px);
                }
            }
        }

        .btn-install {
            position: fixed;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            background: #1f9d55;
            color: #fff;
            border: none;
            padding: 14px 28px;
            border-radius: 50px;
            font-weight: 800;
            box-shadow: 0 10px 30px rgba(31, 157, 85, 0.4);
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .btn-install:active {
            transform: translateX(-50%) scale(0.95);
        }

        .btn-install i {
            font-size: 1.1rem;
        }
    </style>
</head>

<body>

    <div class="page">

        <main class="card">

            <div class="top">

                <div class="logo-wrap">

                    <img
                        src="assets/img/logo-centro-eletricista.png"
                        alt="Centro do Eletricista"
                        onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">

                    <div class="logo-fallback" id="logoFallback">

                        <strong>CENTRO DO ELETRICISTA</strong>

                        <span>
                            Consulta rápida de produtos
                        </span>

                    </div>

                </div>

            </div>

            <div class="bar"></div>

            <div class="content">

                <h1 class="title">
                    LEITOR DE CÓDIGO E QR CODE
                </h1>

                <p class="subtitle">
                    Escaneie um código de barras ou QR Code usando a câmera
                    do aparelho, ou digite manualmente o código do produto.
                </p>

                <div class="field">

                    <label for="codigo">
                        Código do produto
                    </label>

                    <input
                        type="text"
                        id="codigo"
                        autocomplete="off"
                        inputmode="text"
                        placeholder="Digite ou leia o código">

                    <div
                        class="produto-nao-encontrado"
                        id="produtoNaoEncontrado">

                        Produto não cadastrado no sistema.

                    </div>

                </div>

                <div class="buttons">

                    <button
                        class="btn btn-primary"
                        id="btnCamera"
                        type="button">

                        Abrir câmera

                    </button>

                    <button
                        class="btn btn-secondary"
                        id="btnConsultar"
                        type="button">

                        Consultar produto

                    </button>

                </div>

                <div
                    class="status info"
                    id="statusBox"></div>

                <div class="tip-box">

                    <h3>Importante</h3>

                    <p>
                        O sistema consulta automaticamente quando um código
                        válido é digitado manualmente.
                        Ao escanear pela câmera, o código será preenchido
                        automaticamente no campo.
                    </p>

                </div>

            </div>

        </main>

    </div>

    <div
        class="camera-overlay"
        id="cameraOverlay">

        <div class="camera-header">

            <div class="camera-title">

                <strong>Leitor ativo</strong>

                <span>
                    Aponte a câmera para o código de barras
                </span>

            </div>

            <button
                type="button"
                class="camera-close"
                id="btnFecharCamera">

                ×

            </button>

        </div>

        <div id="reader"></div>

        <div class="scan-guide">
            <div class="scan-line"></div>
        </div>

        <div class="camera-footer">
            Centralize o código na área destacada para realizar a leitura.
        </div>

    </div>

    <button id="btnInstallApp" class="btn-install">
        <i class="fas fa-cloud-download-alt"></i> INSTALAR NO CELULAR
    </button>

    <script src="https://unpkg.com/html5-qrcode"></script>

    <script>

        const inputCodigo = document.getElementById('codigo');

        const btnCamera = document.getElementById('btnCamera');

        const btnConsultar = document.getElementById('btnConsultar');

        const btnFecharCamera = document.getElementById('btnFecharCamera');

        const cameraOverlay = document.getElementById('cameraOverlay');

        const statusBox = document.getElementById('statusBox');

        const produtoNaoEncontrado = document.getElementById('produtoNaoEncontrado');

        let html5QrCode = null;

        let cameraAtiva = false;

        let ultimoCodigoLido = '';

        let ultimoTempoLeitura = 0;

        let timeoutConsulta = null;

        function mostrarStatus(texto, tipo = 'info') {

            statusBox.className = 'status show ' + tipo;

            statusBox.textContent = texto;
        }

        function esconderStatus() {

            statusBox.className = 'status';

            statusBox.textContent = '';
        }

        function mostrarProdutoNaoEncontrado() {

            produtoNaoEncontrado.classList.add('show');
        }

        function esconderProdutoNaoEncontrado() {

            produtoNaoEncontrado.classList.remove('show');
        }

        function normalizarCodigo(valor) {

            return String(valor || '')
                .trim()
                .replace(/\s+/g, '');
        }

        function consultarProduto() {

            const codigo = normalizarCodigo(inputCodigo.value);

            esconderProdutoNaoEncontrado();

            if (!codigo || codigo.length < 2) {

                mostrarStatus(
                    'Informe um código válido.',
                    'error'
                );

                inputCodigo.focus();

                return;
            }

            mostrarStatus(
                'Consultando produto...',
                'success'
            );

            fetch(
                'produto_consulta.php?codigo=' +
                encodeURIComponent(codigo),
                {
                    method: 'GET'
                }
            )
            .then(response => response.text())
            .then(html => {

                if (
                    html.includes('Produto não localizado') ||
                    html.includes('Produto não encontrado')
                ) {

                    esconderStatus();

                    mostrarProdutoNaoEncontrado();

                    return;
                }

                window.location.href =
                    'produto_consulta.php?codigo=' +
                    encodeURIComponent(codigo);
            })
            .catch(() => {

                mostrarStatus(
                    'Erro ao consultar produto.',
                    'error'
                );
            });
        }

        async function abrirCamera() {

            if (cameraAtiva) {
                return;
            }

            esconderStatus();

            esconderProdutoNaoEncontrado();

            cameraOverlay.classList.add('show');

            try {

                html5QrCode = new Html5Qrcode('reader');

                await html5QrCode.start(

                    {
                        facingMode: "environment"
                    },

                    {
                        fps: 10,

                        aspectRatio: 1.777,

                        disableFlip: false,

                        rememberLastUsedCamera: true,

                        formatsToSupport: [

                            Html5QrcodeSupportedFormats.QR_CODE,

                            Html5QrcodeSupportedFormats.CODE_128,

                            Html5QrcodeSupportedFormats.CODE_39,

                            Html5QrcodeSupportedFormats.EAN_13,

                            Html5QrcodeSupportedFormats.EAN_8,

                            Html5QrcodeSupportedFormats.UPC_A,

                            Html5QrcodeSupportedFormats.UPC_E
                        ],

                        qrbox: (w, h) => {

                            const tamanho = Math.min(
                                w * 0.72,
                                h * 0.42,
                                420
                            );

                            return {
                                width: tamanho,
                                height: tamanho
                            };
                        }
                    },

                    async (decodedText) => {

                        const codigo = normalizarCodigo(decodedText);

                        const agora = Date.now();

                        if (!codigo) {
                            return;
                        }

                        if (
                            codigo === ultimoCodigoLido &&
                            (agora - ultimoTempoLeitura) < 2000
                        ) {
                            return;
                        }

                        ultimoCodigoLido = codigo;

                        ultimoTempoLeitura = agora;

                        inputCodigo.value = codigo;

                        if (navigator.vibrate) {
                            navigator.vibrate(120);
                        }

                        mostrarStatus(
                            'Código lido com sucesso.',
                            'success'
                        );

                        await fecharCamera();

                        consultarProduto();
                    },

                    () => {}

                );

                cameraAtiva = true;

            } catch (erro) {

                console.error(erro);

                cameraOverlay.classList.remove('show');

                mostrarStatus(
                    'Não foi possível acessar a câmera.',
                    'error'
                );
            }
        }

        async function fecharCamera() {

            cameraOverlay.classList.remove('show');

            if (html5QrCode && cameraAtiva) {

                try {
                    await html5QrCode.stop();
                } catch (e) {}

                try {
                    await html5QrCode.clear();
                } catch (e) {}
            }

            html5QrCode = null;

            cameraAtiva = false;
        }

        btnCamera.addEventListener(
            'click',
            abrirCamera
        );

        btnConsultar.addEventListener(
            'click',
            consultarProduto
        );

        btnFecharCamera.addEventListener(
            'click',
            fecharCamera
        );

        inputCodigo.addEventListener(
            'input',
            () => {

                esconderProdutoNaoEncontrado();

                esconderStatus();

                clearTimeout(timeoutConsulta);

                const codigo = normalizarCodigo(inputCodigo.value);

                if (codigo.length >= 3) {

                    timeoutConsulta = setTimeout(() => {

                        consultarProduto();

                    }, 900);
                }
            }
        );

        inputCodigo.addEventListener(
            'keydown',
            (e) => {

                if (e.key === 'Enter') {

                    e.preventDefault();

                    clearTimeout(timeoutConsulta);

                    consultarProduto();
                }
            }
        );

        window.addEventListener(
            'beforeunload',
            () => {

                if (html5QrCode && cameraAtiva) {

                    try {
                        html5QrCode.stop();
                    } catch (e) {}
                }
            }
        );

        inputCodigo.focus();

        // PWA Installation Logic
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('SW registrado!', reg))
                    .catch(err => console.log('Erro SW', err));
            });
        }

        let deferredPrompt;
        const btnInstall = document.getElementById('btnInstallApp');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            btnInstall.style.display = 'flex';
        });

        btnInstall.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                btnInstall.style.display = 'none';
            }
            deferredPrompt = null;
        });

        window.addEventListener('appinstalled', () => {
            btnInstall.style.display = 'none';
            deferredPrompt = null;
        });

    </script>

</body>

</html>