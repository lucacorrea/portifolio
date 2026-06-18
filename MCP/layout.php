<?php
require_once __DIR__ . '/bootstrap.php';

function nav_class(string $active, string $key): string
{
    return $active === $key ? 'nav-link active' : 'nav-link';
}

function render_layout_start(string $active, string $title, string $subtitle, string $helpText): void
{
    $user = current_user();
    $perfilLabel = ($user['perfil'] ?? '') === 'suporte' ? 'Suporte' : 'Normal';
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - Controle Juridico</title>
    <link rel="stylesheet" href="assets/css/app.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php" aria-label="Inicio">
        <span class="brand-mark"><i class="fa-solid fa-shield-halved"></i></span>
        <span>
            <strong>SCP Pessoal</strong>
            <small>Controle Juridico</small>
        </span>
    </a>

    <nav class="main-nav" aria-label="Menu principal">
        <a class="<?= nav_class($active, 'dashboard') ?>" href="index.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a class="<?= nav_class($active, 'cadastro') ?>" href="cadastro.php"><i class="fa-solid fa-file-circle-plus"></i> Novo</a>
        <a class="<?= nav_class($active, 'prazos') ?>" href="prazos.php"><i class="fa-solid fa-clock"></i> Prazos</a>
        <a class="<?= nav_class($active, 'relatorios') ?>" href="relatorios.php"><i class="fa-solid fa-chart-line"></i> Relatorios</a>
        <?php if (is_suporte()): ?>
            <a class="<?= nav_class($active, 'usuarios') ?>" href="usuarios.php"><i class="fa-solid fa-users-gear"></i> Usuarios</a>
            <a class="<?= nav_class($active, 'configuracoes') ?>" href="configuracoes.php"><i class="fa-solid fa-sliders"></i> Config.</a>
        <?php endif; ?>
    </nav>

    <div class="session-box">
        <div class="session-user">
            <span><?= e($user['nome'] ?? '') ?></span>
            <small><?= e($perfilLabel) ?></small>
        </div>
        <a class="icon-button danger" href="api.php?acao=logout" title="Sair" aria-label="Sair">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </a>
    </div>
</header>

<main class="page-shell">
    <header class="page-header">
        <div>
            <p class="eyebrow">Sistema pessoal de controle juridico</p>
            <h1><?= e($title) ?></h1>
            <p><?= e($subtitle) ?></p>
        </div>
        <div class="page-actions">
            <div class="help-widget">
                <button class="icon-button help-trigger" type="button" title="Ajuda da pagina" aria-label="Ajuda da pagina">
                    <i class="fa-solid fa-circle-question"></i>
                </button>
                <div class="help-popover" role="status">
                    <strong>Ajuda rapida</strong>
                    <p><?= e($helpText) ?></p>
                </div>
            </div>
        </div>
    </header>
    <?php
}

function render_layout_end(): void
{
    ?>
</main>
<div class="modal-backdrop" id="process-modal" hidden>
    <section class="modal-box">
        <header class="modal-header">
            <h2>Detalhes do Processo</h2>
            <button class="icon-button" type="button" data-close-modal title="Fechar" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>
        <div id="process-modal-body" class="modal-body"></div>
    </section>
</div>
<div class="modal-backdrop" id="payment-modal" hidden>
    <section class="modal-box small">
        <header class="modal-header">
            <h2>Registrar Pagamento</h2>
            <button class="icon-button" type="button" data-close-payment-modal title="Fechar" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>
        <form id="payment-form" class="modal-body">
            <input type="hidden" id="payment-process-id">
            <p class="modal-intro" id="payment-process-label">Informe os dados do pagamento.</p>
            <label class="form-field">
                <span>Valor do processo</span>
                <input type="text" id="payment-valor" inputmode="decimal" required placeholder="0,00">
            </label>
            <label class="form-field">
                <span>Porcentagem cobrada (%)</span>
                <input type="text" id="payment-percentual" inputmode="decimal" required placeholder="0">
            </label>
            <label class="form-field">
                <span>Valor a receber</span>
                <input type="text" id="payment-total" readonly value="R$ 0,00">
            </label>
            <footer class="modal-actions">
                <button class="btn ghost" type="button" data-close-payment-modal>Cancelar</button>
                <button class="btn primary" type="submit">
                    <i class="fa-solid fa-money-bill-wave"></i>
                    Registrar
                </button>
            </footer>
        </form>
    </section>
</div>
<div id="toast-root" class="toast-root" aria-live="polite"></div>
<script>
    window.APP_USER = <?= json_encode(current_user(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/app.js?v=2"></script>
</body>
</html>
    <?php
}
