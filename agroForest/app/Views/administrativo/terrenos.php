<?php
$areaTerrenos = 'administrativo';
$paginaAtual = 'terrenos';
$paginaTitulo = 'Terrenos';
$paginaDescricao = 'Cadastro e consulta de terrenos com coordenadas UTM.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Cadastrar UTM';
$linkBotaoAcao = terreno_url('administrativo', 'terrenoCadastrar');
$tituloPagina = 'Administrativo - Terrenos';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <?php require APP_PATH . '/Views/shared/terrenosListagemConteudo.php'; ?>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
