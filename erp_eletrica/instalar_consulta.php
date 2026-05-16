<?php
// instalar_consulta.php - Página dedicada para instalação do App de Consulta
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar Consulta de Preços - ERP Elétrica</title>
    
    <!-- PWA Support -->
    <link rel="manifest" href="manifest.json?v=2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="logo_sistema_erp_eletrica.PNG?v=2">
    <link rel="icon" type="image/png" href="logo_sistema_erp_eletrica.PNG?v=2">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #294f87;
            --success: #1f9d55;
            --bg: #f0f4f8;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #18365f 0%, #294f87 100%);
            color: #fff;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .container {
            text-align: center;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        .app-icon {
            width: 120px;
            height: 120px;
            background: #fff;
            border-radius: 28px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        .app-icon img {
            width: 100%;
            height: 100%;
            border-radius: 28px;
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 900;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        p {
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: 35px;
            line-height: 1.5;
        }
        .btn-install {
            background: var(--success);
            color: #fff;
            border: none;
            width: 100%;
            padding: 18px;
            border-radius: 18px;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 10px 25px rgba(31, 157, 85, 0.4);
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        .btn-install:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(31, 157, 85, 0.5);
        }
        .btn-install:active {
            transform: scale(0.97);
        }
        .ios-hint {
            display: none;
            margin-top: 30px;
            padding: 20px;
            background: rgba(0,0,0,0.2);
            border-radius: 20px;
            text-align: left;
            font-size: 0.9rem;
        }
        .ios-hint i {
            color: #ffcc00;
            margin-right: 8px;
        }
        .step {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="app-icon">
            <img src="logo_sistema_erp_eletrica.PNG?v=2" alt="Icon">
        </div>
        <h1>App Consulta</h1>
        <p>Tenha o leitor de preços da Elétrica sempre à mão na sua tela inicial.</p>
        
        <button id="btnInstall" class="btn-install">
            <i class="fas fa-cloud-download-alt"></i> INSTALAR APP
        </button>

        <div id="iosHint" class="ios-hint">
            <div style="font-weight: bold; margin-bottom: 10px; text-align: center;">
                <i class="fab fa-apple"></i> INSTALAR NO IPHONE
            </div>
            <div class="step">1. Toque no ícone <i class="fas fa-share-square"></i> (Compartilhar)</div>
            <div class="step">2. Role e toque em "Adicionar à Tela de Início"</div>
            <div class="step">3. Toque em "Adicionar" no topo</div>
        </div>

        <div style="margin-top: 25px;">
            <a href="consulta_produto.php" style="color: #fff; opacity: 0.6; text-decoration: none; font-size: 0.85rem;">
                Abrir sem instalar <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js');
            });
        }

        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
        const btnInstall = document.getElementById('btnInstall');
        const iosHint = document.getElementById('iosHint');

        if (isIOS && !isStandalone) {
            btnInstall.style.display = 'none';
            iosHint.style.display = 'block';
        }

        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
        });

        btnInstall.addEventListener('click', async () => {
            if (!deferredPrompt) {
                alert('O seu navegador já instalou o app ou não suporta instalação direta. Use o menu do navegador para "Instalar App".');
                return;
            }
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                btnInstall.innerText = 'INSTALADO!';
                btnInstall.disabled = true;
            }
            deferredPrompt = null;
        });
    </script>
</body>
</html>
