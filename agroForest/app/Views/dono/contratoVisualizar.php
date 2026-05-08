<?php
$areaContrato = 'dono';
$paginaAtual = 'clientes';
$paginaTitulo = 'Visualizar Contrato';
$paginaDescricao = 'Consulta gerencial do contrato vinculado ao cliente.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Clientes';
$linkBotaoAcao = route_url('dono', 'clientes');
$tituloPagina = 'Dono - Visualizar Contrato';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];

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
