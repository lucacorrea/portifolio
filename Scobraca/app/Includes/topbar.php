<header class="topbar">
    <div>
        <strong><?= e($pageTitle ?? 'Painel') ?></strong>
        <p><?= e($pageDescription ?? '') ?></p>
    </div>
    <div class="userbox">
        <span><?= e($_SESSION['usuario']['nome'] ?? 'Usuário') ?></span>
        <?php if (!empty($_SESSION['usuario']['empresa_nome'])): ?>
            <small><?= e($_SESSION['usuario']['empresa_nome']) ?></small>
        <?php else: ?>
            <small>Admin da Plataforma</small>
        <?php endif; ?>
    </div>
</header>
