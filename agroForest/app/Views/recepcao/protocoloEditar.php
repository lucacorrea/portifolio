<?php
$paginaAtual = 'protocolos';
$paginaTitulo = 'Editar Protocolo';
$paginaDescricao = 'Página própria para alterar dados do protocolo.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Visualizar Protocolo';
$linkBotaoAcao = route_url('recepcao', 'protocoloVisualizar');
$tituloPagina = 'Recepção - Editar Protocolo';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="form-card"><div class="section-header"><h2>Edição do protocolo</h2><p>Atualize informações antes de encaminhar.</p></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="codigo">Protocolo</label><input id="codigo" name="codigo" type="text" value="PRT-2026-0418" readonly></div>
<div class="form-group"><label for="cliente">Cliente</label><input id="cliente" name="cliente" type="text" value="Carlos Henrique"></div>
<div class="form-group"><label for="servico">Serviço</label><select id="servico" name="servico"><option selected>Orçamento</option><option>Análise documental</option><option>Cadastro de serviço</option></select></div>
<div class="form-group"><label for="status">Status</label><select id="status" name="status"><option>Em triagem</option><option selected>Encaminhado</option><option>Pendente</option></select></div>
<div class="form-group col-2"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao" rows="5">Solicitação de orçamento registrada pela recepção.</textarea></div>
<div class="form-actions col-2"><a href="<?= route_url('recepcao', 'protocolos') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Atualizar Protocolo</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
