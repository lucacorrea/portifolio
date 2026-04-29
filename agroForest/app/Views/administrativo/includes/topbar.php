<?php
if (!isset($paginaTitulo)) {
    $paginaTitulo = 'Painel Administrativo';
}

if (!isset($paginaDescricao)) {
    $paginaDescricao = 'Área de análise e orçamentos.';
}

if (!isset($usuarioNome)) {
    $usuarioNome = 'Administrador';
}

if (!isset($usuarioCargo)) {
    $usuarioCargo = 'Administrativo';
}

if (!isset($textoBotaoAcao)) {
    $textoBotaoAcao = 'Novo Orçamento';
}

if (!isset($linkBotaoAcao)) {
    $linkBotaoAcao = route_url('administrativo', 'orcamentos');
}

if (!isset($mostrarBusca)) {
    $mostrarBusca = true;
}
?>

<header class="topbar-area">
    <div class="topbar-left">
        <div class="page-heading">
            <span class="page-tag">Sistema de Protocolo</span>
            <h1><?= htmlspecialchars($paginaTitulo) ?></h1>
            <p><?= htmlspecialchars($paginaDescricao) ?></p>
        </div>
    </div>

    <div class="topbar-right">
        <?php if ($mostrarBusca): ?>
            <form class="topbar-search" action="" method="GET">
                <span class="search-icon">🔎</span>
                <input
                    type="text"
                    name="q"
                    placeholder="Buscar protocolo, cliente, documento ou orçamento..."
                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                >
            </form>
        <?php endif; ?>

        <div class="topbar-actions">
            <div class="topbar-user-card">
                <div class="user-avatar">
                    <?= strtoupper(substr($usuarioNome, 0, 1)) ?>
                </div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($usuarioNome) ?></strong>
                    <small><?= htmlspecialchars($usuarioCargo) ?></small>
                </div>
            </div>

            <a href="<?= htmlspecialchars($linkBotaoAcao) ?>" class="topbar-btn-primary">
                <?= htmlspecialchars($textoBotaoAcao) ?>
            </a>
        </div>
    </div>
</header>