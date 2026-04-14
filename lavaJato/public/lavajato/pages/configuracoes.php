<?php
// autoErp/public/lavajato/pages/configuracoes.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo']); // apenas perfis que podem configurar

// Conexão
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) require_once $pathCon;
if (!$pdo instanceof PDO) die('Conexão indisponível.');

require_once __DIR__ . '/../../../lib/util.php';

// Menu ativo p/ sidebar
$menuAtivo = 'lavajato-config';

// ---- Flash via query (?ok=1&msg=... ou ?err=1&msg=...) ----
$ok  = isset($_GET['ok'])  && (int)$_GET['ok']  === 1;
$err = isset($_GET['err']) && (int)$_GET['err'] === 1;
$msg = (string)($_GET['msg'] ?? '');

// ---- CSRF básico (reuso do padrão da lavagem rápida) ----
if (empty($_SESSION['csrf_lavajato_cfg'])) {
    $_SESSION['csrf_lavajato_cfg'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_lavajato_cfg'];

// Empresa (CNPJ “limpo”)
$cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? $_SESSION['empresa_cnpj'] ?? ''));

// Carrega configurações atuais (ou defaults)
$sql = "SELECT utilidades_pct, comissao_lavador_pct, permitir_publico_qr, imprimir_auto, forma_pagamento_padrao, obs
          FROM lavjato_config_peca
         WHERE REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :c
         LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute([':c' => $cnpj]);
$cfg = $st->fetch(PDO::FETCH_ASSOC) ?: [
    'utilidades_pct'         => '0.00',
    'comissao_lavador_pct'   => '0.00',
    'permitir_publico_qr'    => 1,
    'imprimir_auto'          => 0,
    'forma_pagamento_padrao' => 'dinheiro',
    'obs'                    => ''
];

// Nome da empresa para o rodapé
$empresaNome = empresa_nome_logada($pdo) ?: 'Sua Empresa';
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoERP — Lava Jato (Configurações)</title>
    <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
    <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
    <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
    <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
    <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
    <link rel="stylesheet" href="../../assets/css/dark.min.css">
    <link rel="stylesheet" href="../../assets/css/customizer.min.css">
    <link rel="stylesheet" href="../../assets/css/customizer.css">
    <link rel="stylesheet" href="../../assets/css/rtl.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .toast {
            backdrop-filter: saturate(120%) blur(3px);
        }

        .form-select,
        .form-control {
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <?php
    // Sidebar no mesmo padrão do layout da lavagem rápida
    $menuAtivo = 'lavajato-config';
    include __DIR__ . '/../../layouts/sidebar.php';
    ?>

    <main class="main-content">
        <div class="position-relative iq-banner">
            <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
                <div class="container-fluid navbar-inner">
                    <a href="../../dashboard.php" class="navbar-brand">
                        <h4 class="logo-title">AutoERP</h4>
                    </a>
                    <div class="input-group search-input">
                        <span class="input-group-text" id="search-input">
                            <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none"></svg>
                        </span>
                    </div>
                </div>
            </nav>

            <div class="iq-navbar-header" style="height: 150px; margin-bottom: 50px;">
                <div class="container-fluid iq-container">
                    <div class="row">
                        <div class="col-md-12">
                            <h1 class="mb-0">Configurações do Lava Jato</h1>
                            <p>Defina percentuais, opções e padrão de pagamento.</p>
                        </div>
                    </div>
                </div>
                <div class="iq-header-img">
                    <img src="../../assets/images/dashboard/top-header.png" alt="" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">
                </div>
            </div>
        </div>

        <div class="container-fluid content-inner mt-n3 py-0">
            <div class="row">
                <div class="col-12">
                    <div class="card" data-aos="fade-up">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Parâmetros de Operação</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="../actions/configSalvar.php" autocomplete="off" novalidate>
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                                <!-- ===================== FINANCEIRO ===================== -->
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="mb-0">Financeiro</h6>
                                    <span class="text-muted small">Defina percentuais e padrão de pagamento</span>
                                </div>
                                <hr class="mt-1 mb-3">

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="utilidades_pct" class="form-label">% Utilidades (água + luz)</label>
                                        <div class="input-group">
                                            <input
                                                type="number" step="0.01" min="0" max="100"
                                                name="utilidades_pct" id="utilidades_pct"
                                                class="form-control"
                                                value="<?= htmlspecialchars((string)$cfg['utilidades_pct'], ENT_QUOTES, 'UTF-8') ?>"
                                                aria-describedby="utilidadesHelp utilidadesPct">
                                            <span class="input-group-text" id="utilidadesPct">%</span>
                                        </div>
                                        <small id="utilidadesHelp" class="text-muted">
                                            Percentual descontado do total para cobrir água e energia.
                                        </small>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="comissao_lavador_pct" class="form-label">% Comissão do Lavador (opcional)</label>
                                        <div class="input-group">
                                            <input
                                                type="number" step="0.01" min="0" max="100"
                                                name="comissao_lavador_pct" id="comissao_lavador_pct"
                                                class="form-control"
                                                value="<?= htmlspecialchars((string)$cfg['comissao_lavador_pct'], ENT_QUOTES, 'UTF-8') ?>"
                                                aria-describedby="comissaoHelp comissaoPct">
                                            <span class="input-group-text" id="comissaoPct">%</span>
                                        </div>
                                        <small id="comissaoHelp" class="text-muted">
                                            Se usado, calcula comissão sobre o <u>líquido</u> (após utilidades).
                                        </small>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="forma_pagamento_padrao" class="form-label">Forma de Pagamento Padrão</label>
                                        <select name="forma_pagamento_padrao" id="forma_pagamento_padrao" class="form-select">
                                            <?php
                                            $opts = [
                                                'dinheiro' => 'Dinheiro',
                                                'pix'      => 'PIX',
                                                'debito'   => 'Débito',
                                                'credito'  => 'Crédito',
                                                'boleto'   => 'Boleto',
                                                'outro'    => 'Outro',
                                            ];
                                            $atual = (string)($cfg['forma_pagamento_padrao'] ?? 'dinheiro');
                                            foreach ($opts as $k => $rot) {
                                                $sel = ($atual === $k) ? 'selected' : '';
                                                echo "<option value=\"{$k}\" {$sel}>{$rot}</option>";
                                            }
                                            ?>
                                        </select>
                                        <small class="text-muted">Usado como sugestão na Lavagem Rápida.</small>
                                    </div>
                                </div>

                                <!-- Simulador simples -->
                                <div class="alert alert-secondary mt-3 py-2">
                                    <div class="d-flex flex-wrap gap-3 align-items-center">
                                        <div class="small text-muted">Simulação para R$ 100,00:</div>
                                        <div class="small">
                                            <strong>Líquido após utilidades:</strong>
                                            <span id="simLiquido">R$ 100,00</span>
                                        </div>
                                        <div class="small">
                                            <strong>Comissão lavador:</strong>
                                            <span id="simComissao">R$ 0,00</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- ===================== OPÇÕES ===================== -->
                                <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                                    <h6 class="mb-0">Opções</h6>
                                    <span class="text-muted small">Ajustes de conveniência</span>
                                </div>
                                <hr class="mt-1 mb-3">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input" type="checkbox"
                                                id="chkQr" name="permitir_publico_qr" value="1"
                                                <?= !empty($cfg['permitir_publico_qr']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="chkQr">Permitir acesso público via QR</label>
                                        </div>
                                        <small class="text-muted d-block">Exibe tela pública de registro/consulta via QR Code.</small>
                                    </div>

                                </div>

                                <!-- ===================== OBSERVAÇÕES ===================== -->
                                <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                                    <h6 class="mb-0">Observações</h6>
                                    <span class="text-muted small">Informações adicionais</span>
                                </div>
                                <hr class="mt-1 mb-3">

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="obs" class="form-label">Observações</label>
                                        <textarea name="obs" id="obs" rows="2" class="form-control"
                                            placeholder="Ex.: regras internas de repasse, lembretes operacionais..."><?= htmlspecialchars((string)$cfg['obs'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                </div>

                                <div class="mt-4 d-flex gap-2">
                                 
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i> Salvar Configurações
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>

                   
                </div>
            </div>
        </div>

        <footer class="footer">
            <div class="footer-body d-flex justify-content-between align-items-center">
                <div class="left-panel">
                    © <script>
                        document.write(new Date().getFullYear())
                    </script>
                    <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
            </div>
        </footer>
    </main>

    <!-- TOAST (3,4s) — mesmo padrão da lavagem rápida, usa ?ok=1&msg=... ou ?err=1&msg=... -->
    <?php if ($ok || $err): ?>
        <div id="toastMsg" class="toast show align-items-center border-0 position-fixed top-0 end-0 m-3 shadow-lg <?= $ok ? 'bg-success' : 'bg-danger' ?>"
            role="alert" aria-live="assertive" aria-atomic="true"
            style="z-index:2000;min-width:340px;border-radius:12px;overflow:hidden;">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2 text-white fw-semibold">
                    <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> fs-4"></i>
                    <?= htmlspecialchars($msg ?: ($ok ? 'Configurações salvas com sucesso!' : 'Falha ao salvar as configurações.'), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <button id="toastClose" type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
            </div>
            <div class="progress" style="height:3px;">
                <div id="toastProgress" class="progress-bar <?= $ok ? 'bg-success' : 'bg-danger' ?>" style="width:100%"></div>
            </div>
        </div>
    <?php endif; ?>

    <script src="../../assets/js/core/libs.min.js"></script>
    <script src="../../assets/js/core/external.min.js"></script>
    <script src="../../assets/vendor/aos/dist/aos.js"></script>
    <script src="../../assets/js/hope-ui.js" defer></script>
    <script>
        (function() {
            const util = document.getElementById('utilidades_pct');
            const comi = document.getElementById('comissao_lavador_pct');
            const liqEl = document.getElementById('simLiquido');
            const comEl = document.getElementById('simComissao');

            function fmt(v) {
                return v.toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                });
            }

            function calc() {
                const base = 100;
                const u = Math.min(100, Math.max(0, parseFloat(util?.value?.replace(',', '.') || '0')));
                const c = Math.min(100, Math.max(0, parseFloat(comi?.value?.replace(',', '.') || '0')));
                const liquido = base * (1 - (u / 100));
                const comissao = liquido * (c / 100);
                if (liqEl) liqEl.textContent = fmt(liquido);
                if (comEl) comEl.textContent = fmt(comissao);
            }

            util?.addEventListener('input', calc);
            comi?.addEventListener('input', calc);
            calc();
        })();
    </script>

    <script>
        // Toast (auto-hide com barra de progresso), igual ao da lavagem rápida
        document.addEventListener("DOMContentLoaded", function() {
            const toastEl = document.getElementById("toastMsg");
            const progress = document.getElementById("toastProgress");
            if (!toastEl || typeof bootstrap === 'undefined') return;

            const DURATION = 3400;
            const toast = new bootstrap.Toast(toastEl, {
                delay: DURATION,
                autohide: true
            });
            toast.show();

            let width = 100;
            const stepMs = 50,
                step = 100 * stepMs / DURATION;
            const itv = setInterval(() => {
                width = Math.max(0, width - step);
                if (progress) progress.style.width = width + "%";
                if (width <= 0) clearInterval(itv);
            }, stepMs);
        });
    </script>
</body>

</html>