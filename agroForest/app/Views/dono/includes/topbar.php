<header class="topbar-area">
    <div class="topbar-left">
        <div class="page-heading">
            <span class="page-tag">Sistema de Protocolo</span>
            <h1><?= htmlspecialchars($paginaTitulo ?? 'Dono') ?></h1>
            <p><?= htmlspecialchars($paginaDescricao ?? 'Visão total do sistema.') ?></p>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-actions">
            <?php if (!empty($textoBotaoAcao) && !empty($linkBotaoAcao)): ?>
                <a href="<?= htmlspecialchars($linkBotaoAcao) ?>" class="topbar-btn-primary"><?= htmlspecialchars($textoBotaoAcao) ?></a>
            <?php endif; ?>
            <a href="<?= route_url('auth', 'logout') ?>" class="topbar-btn-primary">Sair</a>
        </div>
    </div>
</header>
