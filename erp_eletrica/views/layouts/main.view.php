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
    <link rel="stylesheet" href="public/css/corporate.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="erp-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-wrapper">
            <!-- Top Navbar -->
            <nav class="top-navbar px-4 shadow-sm border-0 mb-4 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-light me-3 d-lg-none" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title-context">
                        <h5 class="mb-0 fw-bold text-dark"><?= $pageTitle ?? 'Dashboard' ?></h5>
                        <small class="text-muted extra-small d-block"><?= $title ?? 'ERP Elétrica SaaS' ?></small>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end d-none d-md-block border-end pe-3">
                        <div class="fw-bold small text-dark"><?= $_SESSION['usuario_nome'] ?></div>
                        <div class="badge bg-primary bg-opacity-10 text-primary extra-small text-uppercase"><?= $_SESSION['usuario_nivel'] ?></div>
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
                            <li><a class="dropdown-item py-2" href="master.php" <?= ($_SESSION['usuario_nivel'] ?? '') !== 'master' ? 'style="display:none"' : '' ?>><i class="fas fa-crown me-2 text-warning"></i>Painel Master</a></li>
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

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/corporate.js"></script>
    <script src="script.js"></script>
</body>
</html>
