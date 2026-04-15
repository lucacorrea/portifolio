<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tático GPS - Conectar WhatsApp</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="../assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
        html, body { height: 100%; }
        .layout-menu { height: 100vh !important; position: sticky; top: 0; overflow: hidden; }
        .layout-menu .menu-inner { height: calc(100vh - 90px); overflow-y: auto !important; padding-bottom: 2rem; }
        
        #qrcode-container {
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }
        #qrcode-container img {
            max-width: 280px;
            height: auto;
        }
        .status-dot {
            height: 12px;
            width: 12px;
            background-color: #bbb;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-online { background-color: #28a745; }
        .status-offline { background-color: #dc3545; }
        .status-connecting { background-color: #ffc107; }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php $paginaAtiva = 'whatsapp'; ?>
            <?php require_once __DIR__ . '/includes/menu.php'; ?>

            <div class="layout-page">
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card bg-primary text-white shadow-none border-0">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-md me-3">
                                                <span class="avatar-initial rounded bg-white text-primary">
                                                    <i class="bx bxl-whatsapp fs-3"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h4 class="mb-0 text-white">Conexão WhatsApp</h4>
                                                <p class="mb-0 opacity-75">Conecte seu número para ativar o chatbot do sistema.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- Coluna Esquerda: Status e Instruções -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header d-flex align-items-center justify-content-between pb-0">
                                        <div class="card-title mb-0">
                                            <h5 class="m-0 me-2">Status do Sistema</h5>
                                            <small class="text-muted">Monitoramento em tempo real</small>
                                        </div>
                                    </div>
                                    <div class="card-body mt-4">
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="status-dot status-offline" id="status-dot"></div>
                                            <span class="fw-bold" id="status-text">Desconectado</span>
                                        </div>
                                        
                                        <div class="p-3 bg-light rounded mb-4">
                                            <h6 class="mb-2">Instruções para conexão:</h6>
                                            <ol class="ps-3 mb-0">
                                                <li class="mb-2">Abra o WhatsApp no seu celular</li>
                                                <li class="mb-2">Toque em <b>Menu</b> ou <b>Configurações</b> e selecione <b>Dispositivos Conectados</b></li>
                                                <li class="mb-2">Toque em <b>Conectar um dispositivo</b></li>
                                                <li class="mb-0">Aponte seu celular para esta tela para capturar o código</li>
                                            </ol>
                                        </div>

                                        <button class="btn btn-outline-danger btn-sm" id="btn-logout" style="display: none;">
                                            <i class="bx bx-log-out me-1"></i> Desconectar WhatsApp
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Coluna Direita: QR Code -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header pb-0">
                                        <h5 class="mb-4">Escaneie o QR Code</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <div id="qrcode-container">
                                            <div class="text-center p-5" id="qr-placeholder">
                                                <div class="spinner-border text-primary mb-3" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                <p class="text-muted">Aguardando QR Code...</p>
                                            </div>
                                            <img id="qrcode-img" src="" alt="QR Code" style="display: none;">
                                        </div>
                                        <p class="mt-3 text-muted small">
                                            <i class="bx bx-info-circle me-1"></i> 
                                            O código é atualizado automaticamente a cada 30 segundos.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php require_once __DIR__ . '/includes/footer.php'; ?>
                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
        // Lógica de simulação de status e QR Code
        // Em um sistema real, isso faria chamadas AJAX para o backend Bridge (Node.js)
        
        function updateStatus() {
            // Placeholder: Em produção, chamar PHP que chama Node.js
            $.get('php/whatsapp_api.php?action=status', function(response) {
                const data = JSON.parse(response);
                
                if(data.connected) {
                    $('#status-dot').removeClass('status-offline status-connecting').addClass('status-online');
                    $('#status-text').text('Conectado (' + data.number + ')');
                    $('#qrcode-container').hide();
                    $('#btn-logout').show();
                } else {
                    $('#status-dot').removeClass('status-online status-connecting').addClass('status-offline');
                    $('#status-text').text('Desconectado');
                    $('#qrcode-container').show();
                    $('#btn-logout').hide();
                    fetchQRCode();
                }
            }).fail(function() {
                // Simulação p/ demonstração inicial se o PHP não existir
                console.log('API não encontrada, usando modo demonstração');
            });
        }

        function fetchQRCode() {
            // Placeholder: Em produção, carregar imagem real do Bridge
            // $('#qrcode-img').attr('src', 'php/whatsapp_api.php?action=qrcode&t=' + new Date().getTime()).show();
            // $('#qr-placeholder').hide();
        }

        // Poll status every 5 seconds
        // setInterval(updateStatus, 5000);
        // updateStatus();
    </script>
</body>
</html>
