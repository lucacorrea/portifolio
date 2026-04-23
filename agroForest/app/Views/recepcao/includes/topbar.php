<header class="topbar-area">
    <div class="topbar-left">
        <div class="page-heading">
            <span class="page-tag">Sistema de Protocolo</span>
            <h1><?= htmlspecialchars($paginaTitulo ?? 'Recepção') ?></h1>
            <p><?= htmlspecialchars($paginaDescricao ?? 'Área operacional da recepção.') ?></p>
        </div>
    </div>

    <div class="topbar-right">
        <?php if (($mostrarBusca ?? true) === true): ?>
            <form class="topbar-search" action="" method="GET">
                <input type="hidden" name="area" value="recepcao">
                <input type="hidden" name="pagina" value="<?= htmlspecialchars($paginaAtual ?? 'dashboard') ?>">
                <span class="search-icon">🔎</span>
                <input type="text" name="q" placeholder="Buscar cliente, protocolo, telefone ou serviço..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </form>
        <?php endif; ?>

        <div class="topbar-actions">
            <div class="topbar-user-card">
                <div class="user-avatar"><?= strtoupper(substr(($usuarioNome ?? 'U'), 0, 1)) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($usuarioNome ?? 'Usuário Demo') ?></strong>
                    <small><?= htmlspecialchars($usuarioCargo ?? 'Recepção') ?></small>
                </div>
            </div>

            <?php if (!empty($textoBotaoAcao ?? '')): ?>
                <a href="<?= htmlspecialchars($linkBotaoAcao ?? '#') ?>" class="topbar-btn-primary"><?= htmlspecialchars($textoBotaoAcao) ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>
