<?php
$paginaAtual = 'documentos';
$paginaTitulo = 'Visualizar Documento';
$paginaDescricao = 'Página própria para consulta do anexo.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Editar Documento';
$linkBotaoAcao = route_url('recepcao', 'documentoEditar');
$tituloPagina = 'Recepção - Visualizar Documento';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="table-card"><div class="section-header"><div><h2>documento_cliente.pdf</h2><p>PRT-2026-0418 - Carlos Henrique</p></div><a href="<?= route_url('recepcao', 'documentos') ?>" class="chip">Voltar</a></div>
<div class="info-grid"><div class="info-card"><strong>Tipo</strong><p>PDF.</p></div><div class="info-card"><strong>Situação</strong><p>Validado.</p></div><div class="info-card"><strong>Origem</strong><p>Anexado na abertura do protocolo.</p></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
