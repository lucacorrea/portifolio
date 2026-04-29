<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}
$page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCP 2.0 - Premium Integration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .layout-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 260px;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(15px);
            border-right: 1px solid var(--border);
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .nav-menu {
            margin-top: 3rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex-grow: 1;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(56, 189, 248, 0.1);
            color: var(--primary);
        }
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        .content-area {
            flex-grow: 1;
            padding: 2rem;
            max-width: calc(100vw - 260px);
        }
        .user-footer {
            margin-top: auto;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
</head>
<body>

    <div class="layout-container">
        <aside class="sidebar">
            <div class="logo-group">
                <i class="fas fa-microchip"></i>
                <span>SCP 2.0</span>
            </div>

            <nav class="nav-menu">
                <a href="index.php" class="nav-item <?= $page == 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="prazos.php" class="nav-item <?= $page == 'prazos.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Prazos
                </a>
                <a href="relatorios.php" class="nav-item <?= $page == 'relatorios.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice"></i> Relatórios
                </a>
                <a href="usuarios.php" class="nav-item <?= $page == 'usuarios.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i> Usuários
                </a>
                <a href="configuracoes.php" class="nav-item <?= $page == 'configuracoes.php' ? 'active' : '' ?>">
                    <i class="fas fa-sliders-h"></i> Configurações
                </a>
                <a href="tipos.php" class="nav-item <?= $page == 'tipos.php' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i> Tipos de Ato
                </a>
            </nav>

            <div class="user-footer">
                <img src="https://ui-avatars.com/api/?name=<?= $_SESSION['usuario_nome'] ?>&background=38bdf8&color=fff" style="width: 35px; border-radius: 50%;">
                <div style="font-size: 0.85rem;">
                    <div style="font-weight: 700;"><?= explode(' ', $_SESSION['usuario_nome'])[0] ?></div>
                    <a href="../api.php?acao=logout" style="color: var(--status-urgente); text-decoration: none; font-size: 0.7rem;">Sair</a>
                </div>
            </div>
        </aside>

        <main class="content-area">
