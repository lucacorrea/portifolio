<?php
$paginaAtual = 'dashboard';
$paginaTitulo = 'Dashboard do Dono';
$paginaDescricao = 'Visão consolidada do sistema, usuários e resultados.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = '';
$linkBotaoAcao = '#';
$tituloPagina = 'Dono - Dashboard do Dono';
$cssPagina = 'assets/css/dono/dono.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="placeholder-card">
            <h2>Dashboard do Dono</h2>
            <p>Visão consolidada do sistema, usuários e resultados.</p>
            <p>Arquivo base criado para continuar a construção do sistema com organização.</p>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
