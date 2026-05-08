<?php
$areaContrato = 'administrativo';
$paginaAtual = 'clientes';
$paginaTitulo = 'Visualizar Contrato';
$paginaDescricao = 'Consulta administrativa do contrato vinculado ao cliente.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Clientes';
$linkBotaoAcao = route_url('administrativo', 'clientes');
$tituloPagina = 'Administrativo - Visualizar Contrato';
$cssPagina = 'assets/css/administrativo/styleadm.css';

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
