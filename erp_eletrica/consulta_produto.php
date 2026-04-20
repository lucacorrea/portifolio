<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leitor de Código de Barras</title>

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
            max-width: 680px;
            background: var(--branco);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--sombra);
        }

        .top {
            background: var(--azul);
            padding: 32px 20px 26px;
            text-align: center;
        }

        .logo-wrap {
            min-height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-wrap img {
            max-width: 280px;
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
            line-height: 1.1;
        }

        .logo-fallback span {
            margin-top: 8px;
            opacity: .9;
            font-size: .95rem;
        }

        .bar {
            height: 6px;
            background: var(--amarelo);
        }

        .content {
            padding: 34px 28px 28px;
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
            color: var(--texto-suave);
            line-height: 1.55;
            margin-bottom: 28px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            font-size: .92rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: var(--azul-escuro);
        }

        .field input {
            width: 100%;
            height: 60px;
            border: 1px solid var(--borda);
            border-radius: 14px;
            background: #f7f9fc;
            padding: 0 18px;
            font-size: 1.12rem;
            color: var(--texto);
            outline: none;
            transition: .2s ease;
        }

        .field input:focus {
            border-color: var(--azul);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(41, 79, 135, .12);
        }

        .buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
        }

        .btn {
            height: 58px;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn-primary {
            background: var(--azul);
            color: #fff;
        }

        .btn-primary:hover {
            background: #214273;
        }

        .btn-secondary {
            background: #e8eef6;
            color: var(--azul-escuro);
        }

        .btn-secondary:hover {
            background: #dce6f1;
        }

        .tip-box {
            margin-top: 20px;
            background: #f8fbff;
            border: 1px solid #e1e9f2;
            border-radius: 16px;
            padding: 16px 18px;
        }

        .tip-box h3 {
            font-size: 1rem;
            color: var(--azul-escuro);
            margin-bottom: 6px;
        }

        .tip-box p {
            color: var(--texto-suave);
            line-height: 1.55;
            font-size: .95rem;
        }

        .status {
            margin-top: 16px;
            display: none;
            border-radius: 14px;
            padding: 14px 16px;
            font-weight: 700;
            line-height: 1.5;
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
            padding: 16px 14px;
            background: linear-gradient(to bottom, rgba(0, 0, 0, .75), rgba(0, 0, 0, 0));
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
            width: 48px;
            height: 48px;
            border-radius: 14px;
            font-size: 1.4rem;
            cursor: pointer;
            backdrop-filter: blur(5px);
        }

        #reader {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            background: #000;
            overflow: hidden;
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
            width: min(88vw, 760px);
            height: min(28vw, 180px);
            max-height: 180px;
            transform: translate(-50%, -50%);
            border: 3px solid rgba(255, 255, 255, .92);
            border-radius: 18px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, .30);
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
            animation: scanLine 2.2s linear infinite;
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
            line-height: 1.45;
            backdrop-filter: blur(8px);
        }

        @media (max-width: 768px) {
            .content {
                padding: 26px 18px 22px;
            }

            .title {
                font-size: 1.55rem;
            }

            .buttons {
                grid-template-columns: 1fr;
            }

            .scan-guide {
                height: 110px;
                width: 90vw;
            }

            @keyframes scanLine {
                0% {
                    transform: translateY(-42px);
                }

                50% {
                    transform: translateY(42px);
                }

                100% {
                    transform: translateY(-42px);
                }
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
                        alt="Centro do Eletricista"
                        onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
                    <div class="logo-fallback" id="logoFallback">
                        <strong>CENTRO DO ELETRICISTA</strong>
                        <span>Consulta rápida de produtos</span>
                    </div>
                </div>
            </div>

            <div class="bar"></div>

            <div class="content">
                <h1 class="title">LEITOR DE CÓDIGO</h1>
                <p class="subtitle">
                    Leia o código de barras com a câmera ou digite o código do produto.
                    Assim que o sistema identificar o código, a consulta abre automaticamente.
                </p>

                <div class="field">
                    <label for="codigo">Código do produto</label>
                    <input
                        type="text"
                        id="codigo"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="Digite ou escaneie o código">
                </div>

                <div class="buttons">
                    <button class="btn btn-primary" id="btnCamera" type="button">Ler com câmera</button>
                    <button class="btn btn-secondary" id="btnLimpar" type="button">Limpar código</button>
                </div>

                <div class="status info" id="statusBox"></div>

                <div class="tip-box">
                    <h3>Como funciona</h3>
                    <p>
                        Ao tocar em <strong>Ler com câmera</strong>, a câmera abre em tela cheia.
                        Quando o código for reconhecido, a página do produto abre automaticamente.
                        Ao digitar manualmente, o sistema também redireciona sozinho após a digitação parar.
                    </p>
                </div>
            </div>
        </main>
    </div>

    <div class="camera-overlay" id="cameraOverlay">
        <div class="camera-header">
            <div class="camera-title">
                <strong>Leitor ativo</strong>
                <span>Aponte a câmera para o código de barras</span>
            </div>

            <button type="button" class="camera-close" id="btnFecharCamera" aria-label="Fechar câmera">×</button>
        </div>

        <div id="reader"></div>

        <div class="scan-guide">
            <div class="scan-line"></div>
        </div>

        <div class="camera-footer">
            Posicione o código dentro da área destacada. Ao reconhecer, a consulta será aberta automaticamente.
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const inputCodigo = document.getElementById('codigo');
        const btnCamera = document.getElementById('btnCamera');
        const btnLimpar = document.getElementById('btnLimpar');
        const btnFecharCamera = document.getElementById('btnFecharCamera');
        const cameraOverlay = document.getElementById('cameraOverlay');
        const statusBox = document.getElementById('statusBox');

        let html5QrCode = null;
        let cameraAtiva = false;
        let redirecionando = false;
        let timerDigitacao = null;
        let ultimoCodigoLido = '';
        let ultimoTempoLeitura = 0;

        function mostrarStatus(texto, tipo = 'info') {
            statusBox.className = 'status show ' + tipo;
            statusBox.textContent = texto;
        }

        function esconderStatus() {
            statusBox.className = 'status';
            statusBox.textContent = '';
        }

        function normalizarCodigo(valor) {
            return String(valor || '').trim().replace(/\s+/g, '');
        }

        function irParaProduto(codigo) {
            if (redirecionando) return;

            const codigoFinal = normalizarCodigo(codigo);

            if (!codigoFinal || codigoFinal.length < 3) {
                return;
            }

            redirecionando = true;
            window.location.href = 'produto_consulta.php?codigo=' + encodeURIComponent(codigoFinal);
        }

        async function abrirCamera() {
            if (cameraAtiva || redirecionando) return;

            if (typeof Html5Qrcode === 'undefined') {
                mostrarStatus('O leitor não carregou corretamente. Atualize a página.', 'error');
                return;
            }

            esconderStatus();
            cameraOverlay.classList.add('show');

            try {
                html5QrCode = new Html5Qrcode('reader');

                await html5QrCode.start({
                        facingMode: {
                            exact: "environment"
                        }
                    }, {
                        fps: 10,
                        aspectRatio: 1.777,
                        disableFlip: false,
                        rememberLastUsedCamera: true,
                        qrbox: (viewfinderWidth, viewfinderHeight) => {
                            const largura = Math.min(viewfinderWidth * 0.88, 760);
                            const altura = Math.min(viewfinderHeight * 0.22, 180);
                            return {
                                width: largura,
                                height: altura
                            };
                        }
                    },
                    (decodedText) => {
                        const codigo = normalizarCodigo(decodedText);
                        const agora = Date.now();

                        if (!codigo) return;

                        if (codigo === ultimoCodigoLido && (agora - ultimoTempoLeitura) < 2000) {
                            return;
                        }

                        ultimoCodigoLido = codigo;
                        ultimoTempoLeitura = agora;
                        inputCodigo.value = codigo;

                        if (navigator.vibrate) {
                            navigator.vibrate(120);
                        }

                        irParaProduto(codigo);
                    },
                    () => {}
                );

                cameraAtiva = true;
            } catch (erro1) {
                try {
                    html5QrCode = new Html5Qrcode('reader');

                    await html5QrCode.start({
                            facingMode: "environment"
                        }, {
                            fps: 10,
                            aspectRatio: 1.777,
                            disableFlip: false,
                            rememberLastUsedCamera: true,
                            qrbox: (viewfinderWidth, viewfinderHeight) => {
                                const largura = Math.min(viewfinderWidth * 0.88, 760);
                                const altura = Math.min(viewfinderHeight * 0.22, 180);
                                return {
                                    width: largura,
                                    height: altura
                                };
                            }
                        },
                        (decodedText) => {
                            const codigo = normalizarCodigo(decodedText);
                            const agora = Date.now();

                            if (!codigo) return;

                            if (codigo === ultimoCodigoLido && (agora - ultimoTempoLeitura) < 2000) {
                                return;
                            }

                            ultimoCodigoLido = codigo;
                            ultimoTempoLeitura = agora;
                            inputCodigo.value = codigo;

                            if (navigator.vibrate) {
                                navigator.vibrate(120);
                            }

                            irParaProduto(codigo);
                        },
                        () => {}
                    );

                    cameraAtiva = true;
                } catch (erro2) {
                    cameraOverlay.classList.remove('show');
                    mostrarStatus('Não foi possível acessar a câmera deste aparelho.', 'error');
                }
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

        function agendarConsultaAutomatica() {
            if (redirecionando) return;

            clearTimeout(timerDigitacao);

            timerDigitacao = setTimeout(() => {
                const codigo = normalizarCodigo(inputCodigo.value);

                if (codigo.length >= 3) {
                    irParaProduto(codigo);
                }
            }, 900);
        }

        btnCamera.addEventListener('click', abrirCamera);
        btnFecharCamera.addEventListener('click', fecharCamera);

        btnLimpar.addEventListener('click', () => {
            inputCodigo.value = '';
            esconderStatus();
            inputCodigo.focus();
        });

        inputCodigo.addEventListener('input', () => {
            agendarConsultaAutomatica();
        });

        inputCodigo.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(timerDigitacao);
                irParaProduto(inputCodigo.value);
            }
        });

        window.addEventListener('beforeunload', () => {
            if (html5QrCode && cameraAtiva) {
                try {
                    html5QrCode.stop();
                } catch (e) {}
            }
        });

        inputCodigo.focus();
    </script>
</body>

</html>