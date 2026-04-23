<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Clientes';
$paginaDescricao = 'Cadastro, busca e histórico de clientes atendidos pela recepção.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Recepcao';
$textoBotaoAcao = '';
$linkBotaoAcao = '#';
$tituloPagina = 'Recepcao - Clientes';
$cssPagina = 'assets/css/recepcao/recepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="placeholder-card">
            <h2>Clientes</h2>
            <p>Cadastro, busca e histórico de clientes atendidos pela recepção.</p>
            <p>Arquivo base criado para continuar a construção do sistema com organização.</p>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
