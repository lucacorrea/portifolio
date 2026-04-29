<?php
$paginaAtual = 'protocolos';
$paginaTitulo = 'Visualizar Protocolo';
$paginaDescricao = 'Página própria para consulta do protocolo.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Editar Protocolo';
$linkBotaoAcao = route_url('recepcao', 'protocoloEditar');
$tituloPagina = 'Recepção - Visualizar Protocolo';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="table-card"><div class="section-header"><div><h2>PRT-2026-0418</h2><p>Carlos Henrique - Orçamento - 22/04/2026</p></div><a href="<?= route_url('recepcao', 'protocolos') ?>" class="chip">Voltar</a></div>
<div class="info-grid"><div class="info-card"><strong>Status</strong><p>Encaminhado ao administrativo.</p></div><div class="info-card"><strong>Recepcionista</strong><p>Maria Souza.</p></div><div class="info-card"><strong>Descrição</strong><p>Solicitação de orçamento registrada com documentos anexados.</p></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
