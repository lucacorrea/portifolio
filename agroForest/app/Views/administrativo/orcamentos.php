<?php
$paginaAtual = 'orcamentos';
$paginaTitulo = 'Orçamentos';
$paginaDescricao = 'Criação e gestão dos orçamentos da área administrativa.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = '';
$linkBotaoAcao = '#';
$tituloPagina = 'Administrativo - Orçamentos';
$cssPagina = 'assets/css/administrativo/administrativo.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="placeholder-card">
            <h2>Orçamentos</h2>
            <p>Criação e gestão dos orçamentos da área administrativa.</p>
            <p>Arquivo base criado para continuar a construção do sistema com organização.</p>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
