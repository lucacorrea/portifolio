<?php
function nav_item(string $href, string $icon, string $label, string $key, string $currentPage): string {
    $active = $key === $currentPage ? ' active' : '';
    return '<a class="nav-item' . $active . '" href="' . h($href) . '">' . ui_icon($icon, 'nav-item-icon') . '<span>' . h($label) . '</span></a>';
}

$nomeLogado = nome_usuario();
$nivelLogado = nivel_usuario();
$iniciais = iniciais_usuario($nomeLogado);
$paginaBusca = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'busca.php';
$valorBusca = $paginaBusca ? trim((string) ($_GET['q'] ?? '')) : '';
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
        <?php if (pode_acessar('dashboard')): ?><?= nav_item('index.php', 'dashboard', 'Dashboard', 'dashboard', $currentPage) ?><?php endif; ?>
        <?php if (pode_acessar('ordens')): ?><?= nav_item('ordens.php', 'os', 'Ordens de Serviço', 'ordens', $currentPage) ?><?php endif; ?>
        <?php if (pode_acessar('orcamentos')): ?><?= nav_item('orcamentos.php', 'budget', 'Orçamentos', 'orcamentos', $currentPage) ?><?php endif; ?>

        <div class="nav-section">Cadastros</div>
        <?php if (pode_acessar('clientes')): ?><?= nav_item('clientes.php', 'clients', 'Clientes', 'clientes', $currentPage) ?><?php endif; ?>
        <?php if (pode_acessar('veiculos')): ?><?= nav_item('veiculos.php', 'vehicle', 'Veículos', 'veiculos', $currentPage) ?><?php endif; ?>
        <?php if (pode_acessar('mecanicos')): ?><?= nav_item('mecanicos.php', 'mechanic', 'Mecânicos', 'mecanicos', $currentPage) ?><?php endif; ?>
        <?php if (pode_acessar('servicos')): ?><?= nav_item('servicos.php', 'service', 'Serviços', 'servicos', $currentPage) ?><?php endif; ?>
        <?php if (pode_acessar('pecas')): ?><?= nav_item('pecas.php', 'parts', 'Peças', 'pecas', $currentPage) ?><?php endif; ?>

        <?php if (pode_acessar('relatorios') || pode_acessar('configuracoes') || pode_acessar('usuarios')): ?>
            <div class="nav-section">Gestão</div>
        <?php endif; ?>
        <?php if (pode_acessar('relatorios')): ?><?= nav_item('relatorios.php', 'report', 'Relatórios', 'relatorios', $currentPage) ?><?php endif; ?>
        <?php if (pode_acessar('configuracoes')): ?><?= nav_item('configuracoes.php', 'settings', 'Configurações', 'configuracoes', $currentPage) ?><?php endif; ?>
        <?php if (pode_acessar('usuarios')): ?><?= nav_item('usuarios.php', 'clients', 'Usuários', 'usuarios', $currentPage) ?><?php endif; ?>
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
            <form class="searchbar" method="get" action="busca.php">
                <?= ui_icon('search') ?>
                <input id="mainSearch" name="q" type="search" value="<?= h($valorBusca) ?>" placeholder="Buscar OS, cliente, veículo ou peça" autocomplete="off">
            </form>
        </div>

        <div class="topbar-right">
            <button class="icon-btn" type="button" aria-label="Notificações">
                <?= ui_icon('bell') ?>
                <span class="notification-dot"></span>
            </button>

            <div class="profile-card">
                <div class="avatar"><?= h($iniciais) ?></div>
                <div class="profile-meta">
                    <strong><?= h($nomeLogado) ?></strong>
                    <span><?= h(ucfirst($nivelLogado)) ?></span>
                </div>
                <form method="post" action="logout.php" style="margin:0">
                    <?= csrf_field() ?>
                    <button class="icon-btn ghost-btn" type="submit" aria-label="Sair" title="Sair" style="width:auto;padding:0 10px;font-size:12px;font-weight:700">Sair</button>
                </form>
            </div>
        </div>
    </header>

    <main class="content">
