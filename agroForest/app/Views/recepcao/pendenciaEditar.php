<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Editar Pendência';
$paginaDescricao = 'Página própria para atualizar pendência.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Visualizar Pendência';
$linkBotaoAcao = route_url('recepcao', 'pendenciaVisualizar');
$tituloPagina = 'Recepção - Editar Pendência';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="form-card"><div class="section-header"><h2>Edição da pendência</h2><p>Atualize a situação sem misturar com a listagem.</p></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="protocolo">Protocolo</label><input id="protocolo" name="protocolo" type="text" value="PRT-2026-0419" readonly></div>
<div class="form-group"><label for="cliente">Cliente</label><input id="cliente" name="cliente" type="text" value="Ana Beatriz"></div>
<div class="form-group col-2"><label for="motivo">Motivo</label><input id="motivo" name="motivo" type="text" value="Documento pendente"></div>
<div class="form-group"><label for="situacao">Situação</label><select id="situacao" name="situacao"><option selected>Aguardando cliente</option><option>Revisão interna</option><option>Resolvida</option></select></div>
<div class="form-group"><label for="data">Data</label><input id="data" name="data" type="date" value="2026-04-22"></div>
<div class="form-actions col-2"><a href="<?= route_url('recepcao', 'pendencias') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Atualizar Pendência</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
