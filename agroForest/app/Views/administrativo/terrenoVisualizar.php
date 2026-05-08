<?php
$areaTerrenos = 'administrativo';
$paginaAtual = 'terrenos';
$paginaTitulo = 'Visualizar Terreno';
$paginaDescricao = 'Todas as informações fictícias do terreno do cliente.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Terrenos';
$linkBotaoAcao = terreno_url('administrativo', 'terrenos');
$tituloPagina = 'Administrativo - Visualizar Terreno';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <?php require APP_PATH . '/Views/shared/terrenoVisualizarConteudo.php'; ?>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
