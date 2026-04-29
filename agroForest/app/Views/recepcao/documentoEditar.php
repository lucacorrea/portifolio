<?php
$paginaAtual = 'documentos';
$paginaTitulo = 'Editar Documento';
$paginaDescricao = 'Página própria para ajustar dados do anexo.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Visualizar Documento';
$linkBotaoAcao = route_url('recepcao', 'documentoVisualizar');
$tituloPagina = 'Recepção - Editar Documento';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="form-card"><div class="section-header"><h2>Edição do documento</h2><p>Atualize metadados do arquivo anexado.</p></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="arquivo">Arquivo</label><input id="arquivo" name="arquivo" type="text" value="documento_cliente.pdf"></div>
<div class="form-group"><label for="tipo">Tipo</label><select id="tipo" name="tipo"><option selected>PDF</option><option>Imagem</option><option>Documento</option></select></div>
<div class="form-group"><label for="protocolo">Protocolo</label><input id="protocolo" name="protocolo" type="text" value="PRT-2026-0418"></div>
<div class="form-group"><label for="situacao">Situação</label><select id="situacao" name="situacao"><option selected>Validado</option><option>Pendente</option><option>Em conferência</option></select></div>
<div class="form-actions col-2"><a href="<?= route_url('recepcao', 'documentos') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Atualizar Documento</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
