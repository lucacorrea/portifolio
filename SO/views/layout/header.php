<?php include_once __DIR__ . '/../../config/database.php'; ?>
<?php include_once __DIR__ . '/../../config/functions.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | Prefeitura Municipal' : 'SGAO - Sistema de Gestão de Ofícios e Aquisições'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
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
        margin: 0;
        padding: 0;
    }

    .nav-item {
        list-style: none;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        transition: .2s ease;
    }

    /* ITEM ATIVO */
    .nav-item.active .nav-link {
        background: rgba(13, 110, 253, 0.10);
        color: #0d6efd !important;
        font-weight: 700;
    }

    .nav-item.active .nav-link i {
        color: #0d6efd !important;
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

    /* BOTÃO FECHAR INTERNO */
    .menu-close {
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
            overflow-y: auto;
        }

        /* MENU ABERTO */
        .navbar-menu.active {
            left: 0;
        }

        /* BOTÃO X NO TOPO DIREITO */
        .menu-close {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
        }

        .menu-close button {
            background: transparent;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #666;
            padding: 4px 6px;
            line-height: 1;
        }

        .menu-close button:hover {
            color: #dc3545;
        }

        /* LISTA */
        .nav-list {
            flex-direction: column;
            gap: 10px;
        }

        .nav-link {
            padding: 10px 12px;
            border-radius: 10px;
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
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);

            function isActive(array $pages): string
            {
                global $currentPage;
                return in_array($currentPage, $pages, true) ? 'active' : '';
            }
            ?>

            <header class="navbar no-print">
                <div class="container-xl">

                    <div class="navbar-header">

                        <!-- HAMBURGUER (SÓ MOBILE) -->
                        <button class="menu-toggle" type="button" onclick="openMenu()" aria-label="Abrir menu">
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

                        <!-- X INTERNO DO MENU -->
                        <div class="menu-close">
                            <button type="button" onclick="closeMenu()" aria-label="Fechar menu">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <ul class="nav-list">

                            <?php if (isset($_SESSION['user_id'])): ?>
                                <?php $nivel = strtoupper($_SESSION['nivel'] ?? ''); ?>

                                <li class="nav-item <?php echo isActive(['dashboard.php', 'index.php']); ?>">
                                    <a href="dashboard.php" class="nav-link">
                                        <i class="fas fa-chart-line"></i> Dashboard
                                    </a>
                                </li>

                                <?php if (in_array($nivel, ['ADMIN', 'SUPORTE', 'FUNCIONARIO', 'CASA_CIVIL'])): ?>
                                    <li class="nav-item <?php echo isActive(['oficios_novo.php']); ?>">
                                        <a href="oficios_novo.php" class="nav-link">
                                            <i class="fas fa-plus-circle"></i> Nova Solicitação
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if (in_array($nivel, ['ADMIN', 'SUPORTE', 'SECRETARIO', 'SEFAZ'])): ?>
                                    <li class="nav-item <?php echo isActive(['oficios_lista.php', 'analisar_oficio.php', 'gerar_aquisicao.php', 'oficios_visualizar.php', 'oficios_editar.php', 'oficios_lista_sefaz.php', 'atribuir_itens.php']); ?>">
                                        <a href="<?php echo $nivel === 'SEFAZ' ? 'oficios_lista_sefaz.php' : 'oficios_lista.php'; ?>" class="nav-link">
                                            <i class="fas fa-folder-open"></i> Lista de Solicitações
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if (in_array($nivel, ['ADMIN', 'SUPORTE', 'SECRETARIO', 'SEFAZ'])): ?>
                                    <li class="nav-item <?php echo isActive(['aquisicoes_lista.php', 'aquisicao_editar.php', 'aquisicoes_visualizar.php', 'aquisicoes_editar.php']); ?>">
                                        <a href="aquisicoes_lista.php" class="nav-link">
                                            <i class="fas fa-shopping-bag"></i> Aquisições
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if (in_array($nivel, ['ADMIN', 'SUPORTE', 'SECRETARIO'])): ?>
                                    <li class="nav-item <?php echo isActive(['relatorios.php']); ?>">
                                        <a href="relatorios.php" class="nav-link">
                                            <i class="fas fa-file-contract"></i> Relatórios
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($nivel === 'SUPORTE'): ?>
                                    <li class="nav-item <?php echo isActive(['usuarios.php']); ?>">
                                        <a href="usuarios.php" class="nav-link">
                                            <i class="fas fa-users-cog"></i> Gerenciar Usuários
                                        </a>
                                    </li>
                                    <li class="nav-item <?php echo isActive(['configuracoes.php']); ?>">
                                        <a href="configuracoes.php" class="nav-link">
                                            <i class="fas fa-tools"></i> Configurações
                                        </a>
                                    </li>
                                <?php endif; ?>

                            <?php endif; ?>

                            <li class="nav-item">
                                <a href="logout.php" class="nav-link" style="color:red;">
                                    <i class="fas fa-sign-out-alt"></i> Sair
                                </a>
                            </li>

                        </ul>
                    </nav>

                    <!-- OVERLAY -->
                    <div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

                </div>
            </header>

            <script>
                function openMenu() {
                    const menu = document.getElementById('navbarMenu');
                    const overlay = document.getElementById('menuOverlay');
                    const icon = document.getElementById('menuIcon');

                    menu.classList.add('active');
                    overlay.classList.add('active');

                    if (icon) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    }
                }

                function closeMenu() {
                    const menu = document.getElementById('navbarMenu');
                    const overlay = document.getElementById('menuOverlay');
                    const icon = document.getElementById('menuIcon');

                    menu.classList.remove('active');
                    overlay.classList.remove('active');

                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }

                function toggleMenu() {
                    const menu = document.getElementById('navbarMenu');
                    if (menu.classList.contains('active')) {
                        closeMenu();
                    } else {
                        openMenu();
                    }
                }

                /* FECHAR AO CLICAR FORA */
                document.addEventListener('click', function(event) {
                    const menu = document.getElementById('navbarMenu');
                    const button = document.querySelector('.menu-toggle');
                    const overlay = document.getElementById('menuOverlay');

                    if (!menu || !button || !overlay) return;

                    const clicouDentroMenu = menu.contains(event.target);
                    const clicouNoBotao = button.contains(event.target);
                    const clicouNoOverlay = overlay.contains(event.target);

                    if (!clicouDentroMenu && !clicouNoBotao && !clicouNoOverlay && menu.classList.contains('active')) {
                        closeMenu();
                    }
                });

                /* FECHAR AO REDIMENSIONAR PARA DESKTOP */
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        closeMenu();
                    }
                });
            </script>

            <main class="page-body">
                <div class="container-xl">
                    <div class="page-header no-print">
                        <h2 class="page-title"><?php echo $page_title ?? 'Painel de Controle'; ?></h2>
                    </div>
                <?php endif; ?>