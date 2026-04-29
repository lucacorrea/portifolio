<?php
$paginaAtual = 'pendencias';
$paginaTitulo = 'Cadastrar Pendência';
$paginaDescricao = 'Página própria para registrar pendência.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Voltar para Pendências';
$linkBotaoAcao = route_url('recepcao', 'pendencias');
$tituloPagina = 'Recepção - Cadastrar Pendência';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="form-card"><div class="section-header"><h2>Nova pendência</h2><p>Registre o que impede a continuidade do atendimento.</p></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="protocolo">Protocolo</label><input id="protocolo" name="protocolo" type="text" placeholder="PRT-2026-0000"></div>
<div class="form-group"><label for="cliente">Cliente</label><input id="cliente" name="cliente" type="text" placeholder="Nome do cliente"></div>
<div class="form-group col-2"><label for="motivo">Motivo</label><input id="motivo" name="motivo" type="text" placeholder="Resumo da pendência"></div>
<div class="form-group"><label for="situacao">Situação</label><select id="situacao" name="situacao"><option>Aguardando cliente</option><option>Revisão interna</option><option>Prioridade alta</option></select></div>
<div class="form-group"><label for="data">Data</label><input id="data" name="data" type="date"></div>
<div class="form-actions col-2"><a href="<?= route_url('recepcao', 'pendencias') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Salvar Pendência</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
