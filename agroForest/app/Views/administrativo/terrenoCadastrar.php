<?php
$areaTerrenos = 'administrativo';
$paginaAtual = 'terrenos';
$paginaTitulo = 'Cadastrar Coordenadas UTM';
$paginaDescricao = 'Registre dados fictícios do terreno e pontos UTM do polígono.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Voltar para Terrenos';
$linkBotaoAcao = terreno_url('administrativo', 'terrenos');
$tituloPagina = 'Administrativo - Cadastrar Terreno';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <?php require APP_PATH . '/Views/shared/terrenoCadastrarConteudo.php'; ?>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
