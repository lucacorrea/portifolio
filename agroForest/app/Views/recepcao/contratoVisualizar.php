<?php
$areaContrato = 'recepcao';
$paginaAtual = 'clientes';
$paginaTitulo = 'Visualizar Contrato';
$paginaDescricao = 'Consulta do contrato vinculado ao cliente.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Clientes';
$linkBotaoAcao = route_url('recepcao', 'clientes');
$tituloPagina = 'Recepção - Visualizar Contrato';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <?php require APP_PATH . '/Views/shared/contratoVisualizarConteudo.php'; ?>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
