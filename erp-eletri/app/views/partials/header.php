<!-- app/views/partials/header.php -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Elétrica - Sistema de Gestão</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3><i class="bi bi-lightning-charge-fill text-warning"></i> ERP ELÉTRICA</h3>
                <small class="text-muted" style="color: #adb5bd !important;">Gestão Técnica</small>
            </div>

            <ul class="list-unstyled components">
                <li class="<?php echo (isset($view) && $view == 'home/index') ? 'active' : ''; ?>">
                    <a href="?url=home/index"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
                </li>
                
                <li class="border-top my-2 border-secondary"></li>
                <small class="px-3 text-uppercase text-muted" style="font-size: 0.75rem;">Operacional</small>

                <li>
                    <a href="?url=prevenda/index"><i class="bi bi-cart3 me-2"></i> Pré-Venda / Balcão</a>
                </li>
                <li>
                    <a href="?url=caixa/index"><i class="bi bi-cash-coin me-2"></i> Caixa / PDV</a>
                </li>
                <li>
                    <a href="?url=clientes/index"><i class="bi bi-people me-2"></i> Clientes</a>
                </li>

                <li class="border-top my-2 border-secondary"></li>
                <small class="px-3 text-uppercase text-muted" style="font-size: 0.75rem;">Estoque e Produtos</small>

                <li>
                    <a href="?url=produtos/index"><i class="bi bi-box-seam me-2"></i> Produtos</a>
                </li>
                <li>
                    <a href="?url=estoque/movimentacao"><i class="bi bi-arrow-left-right me-2"></i> Movimentações</a>
                </li>

                <li class="border-top my-2 border-secondary"></li>
                <small class="px-3 text-uppercase text-muted" style="font-size: 0.75rem;">Gestão</small>
                
                <li>
                    <a href="?url=relatorios/index"><i class="bi bi-file-earmark-bar-graph me-2"></i> Relatórios</a>
                </li>
                 <li>
                    <a href="?url=filiais/index"><i class="bi bi-building me-2"></i> Filiais</a>
                </li>
                <li>
                    <a href="?url=usuarios/index"><i class="bi bi-person-badge me-2"></i> Usuários</a>
                </li>
                
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="main-header">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list"></i>
                </button>

                <!-- Search -->
                <div class="mx-4 d-none d-md-block flex-grow-1">
                     <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control bg-light border-start-0" placeholder="Busca rápida (Produtos, Clientes, Pedidos)...">
                    </div>
                </div>

                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                         <a href="#" class="text-secondary position-relative" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-5"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5rem;">
                                3
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationsDropdown">
                            <li><h6 class="dropdown-header">Notificações</h6></li>
                            <li><a class="dropdown-item small" href="#">Estoque baixo: Cabo 2.5mm</a></li>
                            <li><a class="dropdown-item small" href="#">Nova venda: #1234</a></li>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle fs-5 me-2"></i>
                            <strong><?php echo $_SESSION['user_nome'] ?? 'Usuário'; ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser1">
                            <li><a class="dropdown-item" href="#">Configurações</a></li>
                            <li><a class="dropdown-item" href="#">Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="?url=login/logout">Sair</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="main-content">
                <!-- Content Starts Here -->
