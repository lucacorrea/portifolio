<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Leitor de Código e QR Code</title>

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
            font-size: 1.7rem;
        }

        .logo-fallback span {
            margin-top: 8px;
            font-size: .95rem;
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
        }

        .subtitle {
            text-align: center;
            color: var(--texto-suave);
            line-height: 1.6;
            margin-bottom: 30px;
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
            color: var(--azul-escuro);
        }

        .field input {
            width: 100%;
            height: 62px;
            border: 1px solid var(--borda);
            border-radius: 16px;
            background: #f7f9fc;
            padding: 0 20px;
            font-size: 1.1rem;
            outline: none;
            transition: .2s;
            font-weight: 600;
        }

        .field input:focus {
            border-color: var(--azul);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(41, 79, 135, .12);
        }

        .buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 8px;
        }

        .btn {
            height: 60px;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: .2s;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--azul);
            color: #fff;
        }

        .btn-secondary {
            background: #e9eef6;
            color: var(--azul-escuro);
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
            padding: 18px 14px;
            background: linear-gradient(to bottom, rgba(0, 0, 0, .78), rgba(0, 0, 0, 0));
        }

        .camera-title {
            color: #fff;
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
        }

        #reader {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            background: #000;
        }

        #reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }

        .scan-guide {
            position: absolute;
            left: 50%;
            top: 50%;
            width: min(85vw, 420px);
            height: 180px;
            transform: translate(-50%, -50%);
            border: 3px solid rgba(255, 255, 255, .92);
            border-radius: 18px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, .35);
            pointer-events: none;
            z-index: 10;
        }

        .scan-line {
            position: absolute;
            left: 10px;
            right: 10px;
            top: 50%;
            height: 2px;
            background: #ff3b3b;
            box-shadow: 0 0 12px rgba(255, 59, 59, .9);
            animation: scanLine 2s linear infinite;
        }

        @keyframes scanLine {

            0% {
                transform: translateY(-70px);
            }

            50% {
                transform: translateY(70px);
            }

            100% {
                transform: translateY(-70px);
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
        }

        @media (max-width: 768px) {

            .buttons {
                grid-template-columns: 1fr;
            }

            .scan-guide {
                width: 88vw;
                height: 150px;
            }
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
                        alt="Logo"
                        onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">

                    <div
                        class="logo-fallback"
                        id="logoFallback">

                        <strong>CENTRO DO ELETRICISTA</strong>

                        <span>Consulta rápida</span>

                    </div>

                </div>

            </div>

            <div class="bar"></div>

            <div class="content">

                <h1 class="title">
                    LEITOR DE CÓDIGO
                </h1>

                <p class="subtitle">
                    Escaneie QR Code ou código de barras automaticamente.
                </p>

                <div class="field">

                    <label for="codigo">
                        Código
                    </label>

                    <input
                        type="text"
                        id="codigo"
                        autocomplete="off"
                        placeholder="Leia ou digite o código">

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

                        Consultar

                    </button>

                </div>

                <div
                    class="status info"
                    id="statusBox"></div>

            </div>

        </main>

    </div>

    <div
        class="camera-overlay"
        id="cameraOverlay">

        <div class="camera-header">

            <div class="camera-title">

                <strong>Leitor ativo</strong>

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
            Posicione o código de barras horizontalmente.
        </div>

    </div>

    <script src="https://unpkg.com/@zxing/library@latest"></script>

    <script>

        const inputCodigo = document.getElementById('codigo');

        const btnCamera = document.getElementById('btnCamera');

        const btnConsultar = document.getElementById('btnConsultar');

        const btnFecharCamera = document.getElementById('btnFecharCamera');

        const cameraOverlay = document.getElementById('cameraOverlay');

        const statusBox = document.getElementById('statusBox');

        let codeReader = null;

        let cameraAtiva = false;

        let streamAtual = null;

        function mostrarStatus(texto, tipo = 'info') {

            statusBox.className = 'status show ' + tipo;

            statusBox.textContent = texto;
        }

        function normalizarCodigo(valor) {

            return String(valor || '')
                .trim()
                .replace(/\s+/g, '');
        }

        function consultarProduto() {

            const codigo = normalizarCodigo(inputCodigo.value);

            if (!codigo) {

                mostrarStatus(
                    'Informe um código válido.',
                    'error'
                );

                return;
            }

            mostrarStatus(
                'Produto encontrado. Abrindo...',
                'success'
            );

            setTimeout(() => {

                window.location.href =
                    'produto_consulta.php?codigo=' +
                    encodeURIComponent(codigo);

            }, 300);
        }

        async function abrirCamera() {

            if (cameraAtiva) {
                return;
            }

            try {

                cameraOverlay.classList.add('show');

                const videoContainer = document.getElementById('reader');

                videoContainer.innerHTML = `
                    <video
                        id="videoPreview"
                        autoplay
                        muted
                        playsinline
                        style="width:100%;height:100%;object-fit:cover;">
                    </video>
                `;

                const video = document.getElementById('videoPreview');

                streamAtual = await navigator.mediaDevices.getUserMedia({

                    video: {
                        facingMode: {
                            ideal: "environment"
                        },
                        width: {
                            ideal: 1920
                        },
                        height: {
                            ideal: 1080
                        }
                    },

                    audio: false
                });

                video.srcObject = streamAtual;

                await video.play();

                cameraAtiva = true;

                codeReader = new ZXing.BrowserMultiFormatReader();

                codeReader.decodeFromVideoElementContinuously(

                    video,

                    (result, err) => {

                        if (result) {

                            const codigo = normalizarCodigo(
                                result.getText()
                            );

                            if (!codigo) {
                                return;
                            }

                            inputCodigo.value = codigo;

                            if (navigator.vibrate) {
                                navigator.vibrate(150);
                            }

                            mostrarStatus(
                                'Código encontrado: ' + codigo,
                                'success'
                            );

                            fecharCamera();

                            consultarProduto();
                        }
                    }
                );

            } catch (erro) {

                console.error(erro);

                mostrarStatus(
                    'Erro ao acessar câmera.',
                    'error'
                );

                fecharCamera();
            }
        }

        function fecharCamera() {

            cameraOverlay.classList.remove('show');

            if (codeReader) {

                try {
                    codeReader.reset();
                } catch (e) {}
            }

            if (streamAtual) {

                streamAtual.getTracks().forEach(track => {

                    track.stop();

                });
            }

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
            'keydown',
            (e) => {

                if (e.key === 'Enter') {

                    e.preventDefault();

                    consultarProduto();
                }
            }
        );

    </script>

</body>

</html>