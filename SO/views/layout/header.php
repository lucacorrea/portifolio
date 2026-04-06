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
</head>

<style>
    /* BASE HEADER */
    .navbar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
    }

    /* LOGO */
    .navbar-brand img {
        height: 50px;
    }

    /* MENU DESKTOP */
    .nav-list {
        display: flex;
        gap: 15px;
        list-style: none;
    }

    /* ESCONDER HAMBURGUER NO DESKTOP */
    .menu-toggle {
        display: none;
        font-size: 24px;
        background: none;
        border: none;
        cursor: pointer;
    }

    /* OVERLAY */
    .menu-overlay {
        display: none;
    }

    /* ================= MOBILE ================= */
    @media (max-width: 768px) {

        /* MOSTRA HAMBURGUER */
        .menu-toggle {
            display: block;
            z-index: 1001;
        }

        /* CENTRALIZA LOGO */
        .navbar-brand {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        /* ESCONDE MENU NORMAL */
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

        /* MENU ABERTO */
        .navbar-menu.active {
            left: 0;
        }

        /* LISTA */
        .nav-list {
            flex-direction: column;
            gap: 10px;
        }

        /* OVERLAY ESCURO */
        .menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1000;
        }

        .menu-overlay.active {
            display: block;
        }
    }
</style>

<body>
    <div class="page-wrapper">
        <?php if (isset($_SESSION['user_id']) || isset($_SESSION['secretaria_id'])): ?>
            <header class="navbar no-print">
                <div class="container-xl">

                    <div class="navbar-header">

                        <!-- HAMBURGUER (SÓ MOBILE) -->
                        <button class="menu-toggle" onclick="toggleMenu()">
                            <i id="menuIcon" class="fas fa-bars"></i>
                        </button>

                        <!-- LOGO -->
                        <a href="dashboard.php" class="navbar-brand">
                            <img src="assets/img/prefeitura.jpg" alt="Prefeitura Municipal">
                        </a>

                        <!-- USUÁRIO -->
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

                                <li class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
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
                    <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>

                </div>
            </header>

            <script>
                function abrirMenu() {
                    const menu = document.getElementById('navbarMenu');
                    const overlay = document.getElementById('menuOverlay');
                    const icon = document.getElementById('menuIcon');

                    menu.classList.add('active');
                    overlay.classList.add('active');

                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }

                function fecharMenu() {
                    const menu = document.getElementById('navbarMenu');
                    const overlay = document.getElementById('menuOverlay');
                    const icon = document.getElementById('menuIcon');

                    menu.classList.remove('active');
                    overlay.classList.remove('active');

                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }

                function toggleMenu() {
                    const menu = document.getElementById('navbarMenu');

                    if (menu.classList.contains('active')) {
                        fecharMenu();
                    } else {
                        abrirMenu();
                    }
                }

                /* FECHAR AO CLICAR FORA */
                document.addEventListener('click', function(event) {
                    const menu = document.getElementById('navbarMenu');
                    const button = document.querySelector('.menu-toggle');

                    const clicouDentroMenu = menu.contains(event.target);
                    const clicouNoBotao = button.contains(event.target);

                    if (!clicouDentroMenu && !clicouNoBotao && menu.classList.contains('active')) {
                        fecharMenu();
                    }
                });

                /* OPCIONAL: FECHAR COM ESC */
                document.addEventListener('keydown', function(event) {
                    if (event.key === "Escape") {
                        fecharMenu();
                    }
                });
            </script>

            <main class="page-body">
                <div class="container-xl">
                    <div class="page-header no-print">
                        <h2 class="page-title"><?php echo $page_title ?? 'Painel de Controle'; ?></h2>
                    </div>
                <?php endif; ?>