<?php include_once __DIR__ . '/../../config/database.php'; ?>
<?php include_once __DIR__ . '/../../config/functions.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | Prefeitura Municipal' : 'SGAO - Sistema de Gestão de Ofícios e Aquisições'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .navbar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .navbar-brand img {
            height: 50px;
        }

        .nav-list {
            display: flex;
            gap: 15px;
            list-style: none;
        }

        /* DESKTOP */
        .menu-toggle {
            display: none;
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
        }

        .menu-overlay {
            display: none;
        }

        /* MOBILE */
        @media (max-width: 768px) {

            .menu-toggle {
                display: block;
                z-index: 1003;
            }

            .navbar-brand {
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
            }

            .navbar-menu {
                position: fixed;
                top: 0;
                left: -260px;
                width: 250px;
                height: 100%;
                background: #fff;
                box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
                padding: 20px;
                transition: 0.3s;
                z-index: 1002;
            }

            .navbar-menu.active {
                left: 0;
            }

            .nav-list {
                flex-direction: column;
                gap: 10px;
            }

            .menu-overlay {
                position: fixed;
                top: 0;
                right: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.4);
                z-index: 1001;
            }

            .menu-overlay.active {
                display: block;
            }
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <?php if (isset($_SESSION['user_id']) || isset($_SESSION['secretaria_id'])): ?>

            <header class="navbar no-print">
                <div class="container-xl">

                    <div class="navbar-header">

                        <!-- HAMBURGUER -->
                        <button class="menu-toggle" id="menuButton">
                            <i id="menuIcon" class="fas fa-bars"></i>
                        </button>

                        <!-- LOGO -->
                        <a href="dashboard.php" class="navbar-brand">
                            <img src="assets/img/prefeitura.jpg" alt="Prefeitura Municipal">
                        </a>

                        <!-- USER -->
                        <div class="navbar-user">
                            <div class="user-meta">
                                <div class="user-name"><?php echo $_SESSION['user_nome'] ?? $_SESSION['secretaria_nome']; ?></div>
                                <div class="user-role"><?php echo $_SESSION['nivel'] ?? 'SECRETARIA'; ?></div>
                            </div>
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>

                    </div>

                    <!-- MENU -->
                    <nav class="navbar-menu" id="navbarMenu">
                        <ul class="nav-list">

                            <?php if (isset($_SESSION['user_id'])): ?>

                                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                                <li class="nav-item"><a href="oficios_novo.php" class="nav-link"><i class="fas fa-plus-circle"></i> Novo Ofício</a></li>
                                <li class="nav-item"><a href="oficios_lista.php" class="nav-link"><i class="fas fa-folder-open"></i> Lista de Ofícios</a></li>
                                <li class="nav-item"><a href="aquisicoes_lista.php" class="nav-link"><i class="fas fa-shopping-bag"></i> Aquisições</a></li>

                                <?php if ($_SESSION['nivel'] === 'ADMIN' || $_SESSION['nivel'] === 'SUPORTE'): ?>
                                    <li class="nav-item"><a href="relatorios.php" class="nav-link"><i class="fas fa-file-contract"></i> Relatórios</a></li>
                                <?php endif; ?>

                                <?php if ($_SESSION['nivel'] === 'SUPORTE'): ?>
                                    <li class="nav-item"><a href="configuracoes.php" class="nav-link"><i class="fas fa-tools"></i> Configurações</a></li>
                                <?php endif; ?>

                            <?php else: ?>

                                <li class="nav-item"><a href="acompanhamento.php" class="nav-link"><i class="fas fa-tasks"></i> Acompanhamento</a></li>
                                <li class="nav-item"><a href="confirmar_entrega.php" class="nav-link"><i class="fas fa-truck-loading"></i> Confirmar Entrega</a></li>

                            <?php endif; ?>

                            <li class="nav-item">
                                <a href="logout.php" class="nav-link" style="color:red;">
                                    <i class="fas fa-sign-out-alt"></i> Sair
                                </a>
                            </li>

                        </ul>
                    </nav>

                    <!-- OVERLAY -->
                    <div class="menu-overlay" id="menuOverlay"></div>

                </div>
            </header>

            <script>
                const menu = document.getElementById('navbarMenu');
                const overlay = document.getElementById('menuOverlay');
                const button = document.getElementById('menuButton');
                const icon = document.getElementById('menuIcon');

                function abrirMenu() {
                    menu.classList.add('active');
                    overlay.classList.add('active');
                    icon.classList.replace('fa-bars', 'fa-times');
                }

                function fecharMenu() {
                    menu.classList.remove('active');
                    overlay.classList.remove('active');
                    icon.classList.replace('fa-times', 'fa-bars');
                }

                function toggleMenu(e) {
                    e.stopPropagation(); // evita conflito com clique global
                    menu.classList.contains('active') ? fecharMenu() : abrirMenu();
                }

                button.addEventListener('click', toggleMenu);
                overlay.addEventListener('click', fecharMenu);

                document.addEventListener('click', function(e) {
                    if (!menu.contains(e.target) && !button.contains(e.target)) {
                        fecharMenu();
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === "Escape") fecharMenu();
                });
            </script>

            <main class="page-body">
                <div class="container-xl">
                    <div class="page-header no-print">
                        <h2 class="page-title"><?php echo $page_title ?? 'Painel de Controle'; ?></h2>
                    </div>

                <?php endif; ?>