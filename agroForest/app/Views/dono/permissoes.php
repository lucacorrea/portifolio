<?php
$paginaAtual = 'permissoes';
$paginaTitulo = 'Permissões';
$paginaDescricao = 'Controle de níveis e permissões por perfil.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = '';
$linkBotaoAcao = '#';
$tituloPagina = 'Dono - Permissões';
$cssPagina = 'assets/css/dono/dono.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="placeholder-card">
            <h2>Permissões</h2>
            <p>Controle de níveis e permissões por perfil.</p>
            <p>Arquivo base criado para continuar a construção do sistema com organização.</p>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
