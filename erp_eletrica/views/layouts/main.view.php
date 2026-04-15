<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'ERP Elétrica' ?> - Core Corporate</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Corporate UI -->
    <link rel="stylesheet" href="public/css/corporate.css?v=10.6">
    <link rel="stylesheet" href="style.css?v=10.6">
    
    <script>
        // Critical: Apply sidebar state before rendering to avoid flash
        if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth >= 992) {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="erp-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-wrapper">
            <!-- Top Navbar -->
            <nav class="top-navbar px-4 border-0 mb-4 sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-light me-3" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title-context">
                        <h5 class="mb-0 fw-bold text-dark"><?= $pageTitle ?? 'Dashboard' ?></h5>
                        <small class="text-muted extra-small d-block"><?= $title ?? 'ERP Elétrica SaaS' ?></small>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end d-none d-md-block border-end pe-3">
                        <div class="fw-bold small erp-user-name"><?= $_SESSION['usuario_nome'] ?></div>
                        <div class="badge erp-user-badge extra-small text-uppercase"><?= $_SESSION['usuario_nivel'] ?></div>
                    </div>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                                <?= strtoupper(substr($_SESSION['usuario_nome'], 0, 1)) ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2">
                            <li class="px-3 py-2 border-bottom mb-2">
                                <div class="fw-bold small"><?= $_SESSION['usuario_nome'] ?></div>
                                <div class="text-muted extra-small"><?= $_SESSION['usuario_email'] ?? 'Acesso Nível ' . $_SESSION['usuario_nivel'] ?></div>
                            </li>
                            <li><a class="dropdown-item py-2" href="configuracoes.php"><i class="fas fa-cog me-2 text-muted"></i>Ajustes do Perfil</a></li>
                            <li><hr class="dropdown-divider opacity-50"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair com Segurança</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Content -->
            <main class="content-body fade-up">
                <?php if ($flash = getFlash()): ?>
                    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show shadow-sm border-0 mb-4">
                        <i class="fas fa-circle-info me-2"></i> <?= $flash['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?= $content ?>
            </main>

            <!-- Global Footer Bar -->
            <footer class="erp-footer py-3 bg-white border-top shadow-sm mt-auto">
                <div class="container-fluid d-flex justify-content-between align-items-center">
                    <div class="fw-medium text-nowrap extra-small">
                        ERP Elétrica &copy; <?= date('Y') ?>
                    </div>
                    <div class="text-end extra-small">
                        Desenvolvido por <strong>L&J Soluções</strong>
                    </div>
                </div>
            </footer>
        </div>
        <!-- Sidebar Mobile Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
    </div>

    <!-- Loader -->
    <div id="globalLoader" class="loader-wrapper">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Standard Bootstrap Zoom Modal (Guaranteed Compatibility) -->
    <div class="modal fade" id="erp-image-zoom-modal" tabindex="-1" aria-hidden="true" style="z-index: 10001;">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content bg-transparent border-0 shadow-none">
                <div class="modal-body p-0 text-center position-relative">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close" style="z-index: 10002;"></button>
                    <img id="erp-zoom-image-content" src="" class="img-fluid rounded shadow-lg" style="max-height: 90vh; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>

    <!-- ERP Offline Bridge: Session Data Injection -->
    <script>
        window.__ERP_SESSION = {
            usuario_id: <?= json_encode($_SESSION['usuario_id'] ?? null) ?>,
            usuario_nome: <?= json_encode($_SESSION['usuario_nome'] ?? 'Desconhecido') ?>,
            filial_id: <?= json_encode($_SESSION['filial_id'] ?? 1) ?>,
            is_matriz: <?= json_encode($_SESSION['is_matriz'] ?? false) ?>,
            usuario_nivel: <?= json_encode($_SESSION['usuario_nivel'] ?? 'operador') ?>
        };
    </script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ERP Offline Bridge (MUST load before other scripts to intercept fetch) -->
    <script src="public/js/offline-bridge.js?v=<?= time() ?>"></script>
    <script src="public/js/corporate.js?v=<?= time() ?>"></script>
    <script src="script.js?v=<?= time() ?>"></script>
    
134:     <!-- Service Worker: Limpeza nuclear + registro v5 -->
135:     <script>
136:         (async function() {
137:             if (!('serviceWorker' in navigator)) return;
138:             
139:             try {
140:                 // PASSO 1: Desregistrar TODOS os service workers antigos
141:                 const registrations = await navigator.serviceWorker.getRegistrations();
142:                 for (const reg of registrations) {
143:                     await reg.unregister();
144:                     console.log('[ERP] SW antigo removido:', reg.scope);
145:                 }
146: 
147:                 // PASSO 2: Limpar TODOS os caches
148:                 if ('caches' in window) {
149:                     const names = await caches.keys();
150:                     for (const name of names) {
151:                         await caches.delete(name);
152:                     }
153:                     if (names.length > 0) console.log('[ERP] Caches limpos:', names.length);
154:                 }
155: 
156:                 // PASSO 3: Registrar o novo SW v5
157:                 const reg = await navigator.serviceWorker.register('sw.js');
158:                 console.log('[ERP] SW v5 registrado:', reg.scope);
159:                 
160:                 // Forçar ativação imediata
161:                 if (reg.waiting) {
162:                     reg.waiting.postMessage({ type: 'SKIP_WAITING' });
163:                 }
164:                 reg.addEventListener('updatefound', () => {
165:                     const newSW = reg.installing;
166:                     if (newSW) {
167:                         newSW.addEventListener('statechange', () => {
168:                             if (newSW.state === 'activated') {
169:                                 console.log('[ERP] SW v5 ativado com sucesso!');
170:                             }
171:                         });
172:                     }
173:                 });
174:             } catch (err) {
175:                 console.warn('[ERP] Erro no setup do SW:', err);
176:             }
177:         })();
178:     </script>

</body>
</html>
