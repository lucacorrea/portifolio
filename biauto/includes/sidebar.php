<?php
function navItem(string $href, string $icon, string $label, string $page, string $currentPage): string {
    $active = $page === $currentPage ? ' active' : '';
    return '<a class="nav-item'.$active.'" href="'.$href.'"><i class="icon '.$icon.'"></i><span>'.$label.'</span></a>';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-mark">B</div>
        <div>
            <strong>BIANKA</strong>
            <small>Sistema de Oficina</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?= navItem('index.php', 'icon-layout-dashboard', 'Dashboard', 'dashboard', $currentPage) ?>

        <div class="nav-label">CADASTROS</div>
        <?= navItem('clientes.php', 'icon-users', 'Clientes', 'clientes', $currentPage) ?>
        <?= navItem('veiculos.php', 'icon-car-front', 'Veículos', 'veiculos', $currentPage) ?>
        <?= navItem('mecanicos.php', 'icon-wrench', 'Mecânicos', 'mecanicos', $currentPage) ?>
        <?= navItem('servicos.php', 'icon-list-checks', 'Serviços', 'servicos', $currentPage) ?>
        <?= navItem('pecas.php', 'icon-package', 'Peças', 'pecas', $currentPage) ?>

        <div class="nav-label">OPERAÇÃO</div>
        <?= navItem('ordens.php', 'icon-clipboard-list', 'Ordens de Serviço', 'ordens', $currentPage) ?>
        <?= navItem('orcamentos.php', 'icon-file-text', 'Orçamentos', 'orcamentos', $currentPage) ?>

        <div class="nav-label">GESTÃO</div>
        <?= navItem('relatorios.php', 'icon-chart-column', 'Relatórios', 'relatorios', $currentPage) ?>
        <?= navItem('configuracoes.php', 'icon-settings', 'Configurações', 'configuracoes', $currentPage) ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="avatar">AD</div>
            <div class="user-meta">
                <strong>Administrador</strong>
                <span>Oficina Bianka</span>
            </div>
        </div>
    </div>
</aside>

<div class="main-area">
    <header class="topbar">
        <button class="icon-button mobile-only" id="menuToggle" aria-label="Abrir menu">
            <i class="icon icon-menu"></i>
        </button>

        <div class="topbar-title">
            <span><?= htmlspecialchars($pageTitle) ?></span>
        </div>

        <div class="topbar-actions">
            <button class="icon-button" aria-label="Notificações">
                <i class="icon icon-bell"></i>
                <span class="notification-dot"></span>
            </button>
            <div class="profile-chip">
                <div class="avatar small">AD</div>
                <span>Administrador</span>
            </div>
        </div>
    </header>

    <main class="content">
