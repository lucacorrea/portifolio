<?php
if (!isset($paginaTitulo)) {
    $paginaTitulo = 'Painel da Recepção';
}

if (!isset($paginaDescricao)) {
    $paginaDescricao = 'Área operacional da recepção.';
}

if (!isset($usuarioNome)) {
    $usuarioNome = 'Recepcionista';
}

if (!isset($usuarioCargo)) {
    $usuarioCargo = 'Recepção';
}

if (!isset($mostrarBusca)) {
    $mostrarBusca = true;
}

if (!isset($textoBotaoAcao)) {
    $textoBotaoAcao = '+ Novo Protocolo';
}

if (!isset($linkBotaoAcao)) {
    $linkBotaoAcao = 'novo-protocolo.php';
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
                    placeholder="Buscar cliente, protocolo, telefone ou serviço..."
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