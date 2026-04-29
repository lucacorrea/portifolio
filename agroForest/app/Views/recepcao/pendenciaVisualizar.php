<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Visualizar Pendência';
$paginaDescricao = 'Página própria para consulta da pendência.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Editar Pendência';
$linkBotaoAcao = route_url('recepcao', 'pendenciaEditar');
$tituloPagina = 'Recepção - Visualizar Pendência';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="table-card"><div class="section-header"><div><h2>PRT-2026-0419</h2><p>Ana Beatriz - Documento pendente</p></div><a href="<?= route_url('recepcao', 'pendencias') ?>" class="chip">Voltar</a></div>
<div class="info-grid"><div class="info-card"><strong>Situação</strong><p>Aguardando cliente.</p></div><div class="info-card"><strong>Data</strong><p>22/04/2026.</p></div><div class="info-card"><strong>Próxima ação</strong><p>Solicitar reenvio do documento pendente.</p></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
