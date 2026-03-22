<header class="topbar">
    <div class="topbar-left">
        <button class="topbar-mobile-toggle" id="sidebarToggle" type="button">☰</button>

        <div class="topbar-title-wrap">
            <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            <p>Gestão central do escritório contábil</p>
        </div>
    </div>

    <div class="topbar-actions">
        <a class="btn btn-soft" href="#empresas-pendentes">Ir para pendências</a>
        <a class="btn btn-secondary" href="<?= htmlspecialchars(url('logout')) ?>">Sair</a>
        <div class="topbar-avatar">
            <?= htmlspecialchars(substr((string)(($user['nome'] ?? 'U')), 0, 1)) ?>
        </div>
    </div>
</header>
