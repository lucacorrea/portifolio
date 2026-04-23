<?php
$paginaAtual = 'configuracoes';
$paginaTitulo = 'Configurações Gerais';
$paginaDescricao = 'Parâmetros globais do sistema.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = '';
$linkBotaoAcao = '#';
$tituloPagina = 'Dono - Configurações Gerais';
$cssPagina = 'assets/css/dono/dono.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="placeholder-card">
            <h2>Configurações Gerais</h2>
            <p>Parâmetros globais do sistema.</p>
            <p>Arquivo base criado para continuar a construção do sistema com organização.</p>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
