<?php
$areaTerrenos = 'dono';
$paginaAtual = 'terrenos';
$paginaTitulo = 'Terrenos';
$paginaDescricao = 'Visão gerencial dos terrenos e coordenadas UTM dos clientes.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Cadastrar UTM';
$linkBotaoAcao = terreno_url('dono', 'terrenoCadastrar');
$tituloPagina = 'Dono - Terrenos';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];

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
