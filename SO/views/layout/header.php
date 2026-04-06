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
    /* HEADER BASE */
    .navbar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
    }

    /* LOGO CENTRAL */
    .center-logo {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
    }

    .center-logo img {
        height: 45px;
    }

    /* HAMBURGUER */
    .menu-toggle {
        font-size: 24px;
        background: none;
        border: none;
        cursor: pointer;
    }

    /* MENU DESKTOP */
    .navbar-menu {
        margin-top: 10px;
    }

    .nav-list {
        display: flex;
        gap: 15px;
        list-style: none;
    }

    /* MOBILE */
    @media (max-width: 768px) {

        .nav-list {
            flex-direction: column;
        }

        .navbar-menu {
            display: none;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .navbar-menu.active {
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

                        <!-- HAMBURGUER (ESQUERDA) -->
                        <button class="menu-toggle" onclick="toggleMenu()">
                            <i id="menuIcon" class="fas fa-bars"></i>
                        </button>

                        <!-- LOGO CENTRAL -->
                        <a href="dashboard.php" class="navbar-brand center-logo">
                            <img src="assets/img/prefeitura.jpg" alt="Prefeitura Municipal">
                        </a>

                        <!-- USUÁRIO (DIREITA) -->
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

                </div>
            </header>

            <script>
                function toggleMenu() {
                    const menu = document.getElementById('navbarMenu');
                    const icon = document.getElementById('menuIcon');

                    menu.classList.toggle('active');

                    // TROCA ÍCONE
                    if (menu.classList.contains('active')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            </script>

            <main class="page-body">
                <div class="container-xl">
                    <div class="page-header no-print">
                        <h2 class="page-title"><?php echo $page_title ?? 'Painel de Controle'; ?></h2>
                    </div>
                <?php endif; ?>