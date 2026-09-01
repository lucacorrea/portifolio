<?php
function nav_item(string $href, string $icon, string $label, string $key, string $currentPage): string {
    $active = $key === $currentPage ? ' active' : '';
    return '<a class="nav-item' . $active . '" href="' . h($href) . '">' . ui_icon($icon, 'nav-item-icon') . '<span>' . h($label) . '</span></a>';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <a href="index.php" class="brand-mark" aria-label="Bianka Oficina">B</a>
        <div class="brand-meta">
            <strong>Bianka</strong>
            <span>Oficina Mecânica</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?= nav_item('index.php', 'dashboard', 'Dashboard', 'dashboard', $currentPage) ?>
        <?= nav_item('ordens.php', 'os', 'Ordens de Serviço', 'ordens', $currentPage) ?>
        <?= nav_item('orcamentos.php', 'budget', 'Orçamentos', 'orcamentos', $currentPage) ?>

        <div class="nav-section">Cadastros</div>
        <?= nav_item('clientes.php', 'clients', 'Clientes', 'clientes', $currentPage) ?>
        <?= nav_item('veiculos.php', 'vehicle', 'Veículos', 'veiculos', $currentPage) ?>
        <?= nav_item('mecanicos.php', 'mechanic', 'Mecânicos', 'mecanicos', $currentPage) ?>
        <?= nav_item('servicos.php', 'service', 'Serviços', 'servicos', $currentPage) ?>
        <?= nav_item('pecas.php', 'parts', 'Peças', 'pecas', $currentPage) ?>

        <div class="nav-section">Gestão</div>
        <?= nav_item('relatorios.php', 'report', 'Relatórios', 'relatorios', $currentPage) ?>
        <?= nav_item('configuracoes.php', 'settings', 'Configurações', 'configuracoes', $currentPage) ?>
    </nav>

    <div class="sidebar-bottom">
        <button class="theme-switch" id="themeToggle" type="button">
            <?= ui_icon('sun', 'theme-icon') ?>
            <span id="themeLabel">Modo claro</span>
            <span class="theme-toggle-ui"><span></span></span>
        </button>
    </div>
</aside>

<div class="main-area">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="menuToggle" type="button" aria-label="Abrir menu">
                <?= ui_icon('menu') ?>
            </button>
            <label class="searchbar" for="mainSearch">
                <?= ui_icon('search') ?>
                <input id="mainSearch" type="search" placeholder="Buscar OS, cliente, veículo ou peça">
            </label>
        </div>

        <div class="topbar-right">
            <button class="icon-btn" type="button" aria-label="Notificações">
                <?= ui_icon('bell') ?>
                <span class="notification-dot"></span>
            </button>

            <div class="profile-card">
                <div class="avatar">AD</div>
                <div class="profile-meta">
                    <strong>Administrador</strong>
                    <span>Bianka Oficina</span>
                </div>
                <button class="icon-btn ghost-btn" type="button" aria-label="Mais opções">
                    <?= ui_icon('ellipsis') ?>
                </button>
            </div>
        </div>
    </header>

    <main class="content">
