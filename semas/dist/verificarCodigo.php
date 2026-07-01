<?php

declare(strict_types=1);

// CONEXÃO (ajuste o caminho conforme seu projeto)
require_once __DIR__ . '/assets/conexao.php';

// (opcional) timezone
date_default_timezone_set('America/Manaus');

// Pega e-mail via GET
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// Busca o último token para esse e-mail (a página usa o expires_at para o cronômetro)
$expiresAt = null;
try {
    if ($email !== '') {
        $stmt = $pdo->prepare("
            SELECT id, codigo, used, created_at, expires_at
            FROM senha_tokens
            WHERE email = :email
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $tok = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tok) {
            $expiresAt = $tok['expires_at']; // 'Y-m-d H:i:s'
        }
    }
} catch (Throwable $e) {
    // se der erro, deixa sem expiresAt; JS trata como zerado
}
?>
<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Recuperar Senha - Coari Meu Lar</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <!-- Library / Plugin Css Build -->
    <link rel="stylesheet" href="../dist/assets/css/core/libs.min.css">

    <!-- Hope Ui Design System Css -->
    <link rel="stylesheet" href="../dist/assets/css/hope-ui.min.css?v=4.0.0">

    <!-- Custom Css -->
    <link rel="stylesheet" href="../dist/assets/css/custom.min.css?v=4.0.0">

    <!-- Dark Css -->
    <link rel="stylesheet" href="../dist/assets/css/dark.min.css">

    <!-- Customizer Css -->
    <link rel="stylesheet" href="../dist/assets/css/customizer.min.css">

    <!-- RTL Css -->
    <link rel="stylesheet" href="../dist/assets/css/rtl.min.css">

    <style>
        .countdown {
            font-weight: 700;
            font-size: 1.05rem;
        }

        .countdown.text-danger {
            color: #dc3545 !important;
        }

        .btn-disabled {
            pointer-events: none;
            opacity: .6;
        }
    </style>
</head>

<body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">
    <!-- loader Start -->
    <div id="loading">
        <div class="loader simple-loader">
            <div class="loader-body">
            </div>
        </div>
    </div>
    <!-- loader END -->

    <div class="wrapper">
        <section class="login-content">
            <div class="row m-0 align-items-center bg-white">
                <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
                    <!-- ajuste o caminho da imagem se necessário -->
                    <img src="./assets/images/auth/02.png" class="img-fluid gradient-main animated-scaleX" alt="images">
                </div>
                <div class="col-md-6 p-0">
                    <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
                        <div class="card-body">
                            <a href="#" class="navbar-brand d-flex align-items-center mb-3">
                                <!--Logo start-->
                                <div class="logo-main" style="margin: 0 auto !important;">
                                    <div class="logo-normal">
                                        <img src="../dist/assets/images/logo/prefeitura.png" alt="Prefeitura Logo"
                                            style="width:250px;height:240px;" />
                                    </div>
                                    <div class="logo-mini">
                                        <img src="../dist/assets/images/logo/prefeitura.png" alt="Prefeitura Logo"
                                            style="width:20px;height:20px;" />
                                    </div>
                                </div>
                                <!--logo End-->
                            </a>

                            <h2 class="mb-2" style="margin-top: -60px;">Verificar Código</h2>
                            <p>Enviamos um código de 6 dígitos para <strong><?php echo htmlspecialchars($email ?: '—'); ?></strong>. Ele expira em 3 minutos.</p>

                            <!-- Form de verificação -->
                            <form action="./auth/verificarCodigoPost.php" method="POST" class="mb-3" id="formVerificar">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="floating-label form-group">
                                            <label for="codigo" class="form-label">Código</label>
                                            <input type="text"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                maxlength="6"
                                                class="form-control"
                                                id="codigo"
                                                name="codigo"
                                                placeholder="000000"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mb-3">
                                    <button type="submit" class="btn btn-primary col-12 col-md-12">Verificar</button>
                                </div>
                            </form>

                            <hr>

                            <!-- ===== Contador acima do "Não recebeu?" ===== -->
                            <div class="mb-2">
                                <span>Tempo restante: </span>
                                <span id="countdown" class="countdown">—</span>
                            </div>

                            <!-- Texto "Não recebeu?" -->
                            <div class="mb-2">Não recebeu? Reenviar código</div>

                            <!-- Botão Reenviar abaixo do texto -->
                            <form action="./auth/reenviarCodigo.php" method="POST" id="formReenviar" class="mb-1">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <button type="submit" id="btnReenviar" class="btn btn-outline-primary btn-sm btn-disabled" disabled>Reenviar</button>
                            </form>
                            <small class="text-muted d-block mt-1">O botão será habilitado quando o tempo expirar.</small>

                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <!-- Library Bundle Script -->
    <script src="../dist/assets/js/core/libs.min.js"></script>

    <!-- External Library Bundle Script -->
    <script src="../dist/assets/js/core/external.min.js"></script>

    <!-- Widgetchart Script -->
    <script src="../dist/assets/js/charts/widgetcharts.js"></script>

    <!-- mapchart Script -->
    <script src="../dist/assets/js/charts/vectore-chart.js"></script>
    <script src="../dist/assets/js/charts/dashboard.js"></script>

    <!-- fslightbox Script -->
    <script src="../dist/assets/js/plugins/fslightbox.js"></script>

    <!-- Settings Script -->
    <script src="../dist/assets/js/plugins/setting.js"></script>

    <!-- Slider-tab Script -->
    <script src="../dist/assets/js/plugins/slider-tabs.js"></script>

    <!-- App Script -->
    <script src="../dist/assets/js/hope-ui.js" defer></script>

    <script>
        // ======= Countdown persistente por e-mail (localStorage) =======
        const email = <?php echo json_encode($email); ?>;
        const serverExpiresAt = <?php echo json_encode($expiresAt); ?>; // 'Y-m-d H:i:s' ou null

        // Converte 'Y-m-d H:i:s' em epoch ms (interpreta como local time)
        function toEpochMs(ymdhis) {
            if (!ymdhis) return null;
            const t = ymdhis.replace(' ', 'T');
            const dt = new Date(t);
            if (isNaN(dt)) return null;
            return dt.getTime();
        }

        const LS_KEY_EXP = 'reset_exp_' + (email || 'anon');
        const btnReenviar = document.getElementById('btnReenviar');
        const countdownEl = document.getElementById('countdown');

        // Sincroniza localStorage com expires_at do servidor (se houver)
        const serverExpMs = toEpochMs(serverExpiresAt);
        if (serverExpMs) {
            localStorage.setItem(LS_KEY_EXP, String(serverExpMs));
        } else {
            // Se não há token no servidor, libera o reenviar imediatamente
            localStorage.removeItem(LS_KEY_EXP);
        }

        function updateCountdown() {
            const stored = localStorage.getItem(LS_KEY_EXP);
            const expMs = stored ? parseInt(stored, 10) : 0;
            const now = Date.now();
            let remaining = Math.max(0, Math.floor((expMs - now) / 1000)); // em segundos

            if (!expMs || remaining <= 0) {
                countdownEl.textContent = '00:00';
                countdownEl.classList.remove('text-danger');
                // habilita reenviar
                btnReenviar.disabled = false;
                btnReenviar.classList.remove('btn-disabled');
                return;
            }

            const mm = String(Math.floor(remaining / 60)).padStart(2, '0');
            const ss = String(remaining % 60).padStart(2, '0');
            countdownEl.textContent = `${mm}:${ss}`;

            // Fica vermelho a partir de 30s
            if (remaining <= 30) {
                countdownEl.classList.add('text-danger');
            } else {
                countdownEl.classList.remove('text-danger');
            }

            // enquanto não expirar, não pode reenviar
            btnReenviar.disabled = true;
            btnReenviar.classList.add('btn-disabled');

            requestAnimationFrame(updateCountdown);
        }

        updateCountdown();
    </script>

</body>

</html>