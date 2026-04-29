<header class="topbar-area">
    <div class="topbar-left">
        <div class="page-heading">
            <span class="page-tag">Sistema de Protocolo</span>
            <h1><?= htmlspecialchars($paginaTitulo ?? 'Dono') ?></h1>
            <p><?= htmlspecialchars($paginaDescricao ?? 'Visão total do sistema.') ?></p>
        </div>
    </div>
    <?php if (!empty($textoBotaoAcao) && !empty($linkBotaoAcao)): ?>
        <div class="topbar-right">
            <div class="topbar-actions">
                <a href="<?= htmlspecialchars($linkBotaoAcao) ?>" class="topbar-btn-primary"><?= htmlspecialchars($textoBotaoAcao) ?></a>
            </div>
        </div>
    <?php endif; ?>
</header>
