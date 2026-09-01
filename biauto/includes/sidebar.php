<?php
function navItem(string $href, string $icon, string $label, string $page, string $currentPage): string {
    $active = $page === $currentPage ? ' active' : '';
    return '<a class="nav-item'.$active.'" href="'.$href.'"><i class="icon '.$icon.'"></i><span>'.$label.'</span></a>';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <a href="index.php" class="brand-symbol" aria-label="Bianka Oficina">B</a>
        <button class="sidebar-collapse" id="sidebarCollapse" type="button" aria-label="Recolher menu">
            <i class="icon icon-panel-left-close"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <?= navItem('index.php', 'icon-house', 'Dashboard', 'dashboard', $currentPage) ?>
        <?= navItem('ordens.php', 'icon-clipboard-list', 'Ordens de Serviço', 'ordens', $currentPage) ?>
        <?= navItem('orcamentos.php', 'icon-file-text', 'Orçamentos', 'orcamentos', $currentPage) ?>

        <div class="nav-divider"></div>
        <div class="nav-label">CADASTROS</div>
        <?= navItem('clientes.php', 'icon-users', 'Clientes', 'clientes', $currentPage) ?>
        <?= navItem('veiculos.php', 'icon-car-front', 'Veículos', 'veiculos', $currentPage) ?>
        <?= navItem('mecanicos.php', 'icon-wrench', 'Mecânicos', 'mecanicos', $currentPage) ?>
        <?= navItem('servicos.php', 'icon-list-checks', 'Serviços', 'servicos', $currentPage) ?>
        <?= navItem('pecas.php', 'icon-package', 'Peças', 'pecas', $currentPage) ?>

        <div class="nav-divider"></div>
        <?= navItem('relatorios.php', 'icon-chart-column', 'Relatórios', 'relatorios', $currentPage) ?>
        <?= navItem('configuracoes.php', 'icon-settings', 'Configurações', 'configuracoes', $currentPage) ?>
    </nav>

    <div class="sidebar-footer">
        <button class="theme-toggle" id="themeToggle" type="button">
            <i class="icon icon-sun" id="themeIcon"></i>
            <span id="themeLabel">Modo claro</span>
            <span class="switch"><span></span></span>
        </button>
    </div>
</aside>

<div class="main-area">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="menuToggle" type="button" aria-label="Abrir menu">
                <i class="icon icon-menu"></i>
            </button>
            <div class="global-search">
                <i class="icon icon-search"></i>
                <input type="search" placeholder="Buscar OS, cliente, veículo..." aria-label="Busca geral">
            </div>
        </div>

        <div class="topbar-actions">
            <button class="top-icon-btn" type="button" aria-label="Notificações">
                <i class="icon icon-bell"></i>
                <span class="notification-dot"></span>
            </button>
            <div class="profile-box">
                <div class="profile-avatar">AD</div>
                <div class="profile-text">
                    <strong>Administrador</strong>
                    <span>Bianka Oficina</span>
                </div>
                <button class="profile-more" type="button" aria-label="Mais opções"><i class="icon icon-ellipsis-vertical"></i></button>
            </div>
        </div>
    </header>

    <main class="content">
