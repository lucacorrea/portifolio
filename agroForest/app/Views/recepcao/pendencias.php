<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Pendências';
$paginaDescricao = 'Itens com cadastro incompleto, anexos ausentes e retornos pendentes.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Recepcao';
$textoBotaoAcao = '';
$linkBotaoAcao = '#';
$tituloPagina = 'Recepcao - Pendências';
$cssPagina = 'assets/css/recepcao/recepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>
        <section class="placeholder-card">
            <h2>Pendências</h2>
            <p>Itens com cadastro incompleto, anexos ausentes e retornos pendentes.</p>
            <p>Arquivo base criado para continuar a construção do sistema com organização.</p>
        </section>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
