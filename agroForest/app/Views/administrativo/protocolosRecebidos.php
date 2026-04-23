<?php
$paginaAtual = 'protocolosRecebidos';
$paginaTitulo = 'Protocolos Recebidos';
$paginaDescricao = 'Fila de protocolos enviados pela recepção.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = '';
$linkBotaoAcao = '#';
$tituloPagina = 'Administrativo - Protocolos Recebidos';
$cssPagina = 'assets/css/administrativo/administrativo.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="placeholder-card">
            <h2>Protocolos Recebidos</h2>
            <p>Fila de protocolos enviados pela recepção.</p>
            <p>Arquivo base criado para continuar a construção do sistema com organização.</p>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
