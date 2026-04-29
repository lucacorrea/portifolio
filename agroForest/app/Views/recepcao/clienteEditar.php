<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Editar Cliente';
$paginaDescricao = 'Página própria para atualizar cadastro de cliente.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Visualizar Cliente';
$linkBotaoAcao = route_url('recepcao', 'clienteVisualizar');
$tituloPagina = 'Recepção - Editar Cliente';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="form-card"><div class="section-header"><h2>Edição do cliente</h2><p>Alterações ficam separadas da listagem.</p></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="nome">Nome</label><input id="nome" name="nome" type="text" value="Carlos Henrique"></div>
<div class="form-group"><label for="documento">CPF / CNPJ</label><input id="documento" name="documento" type="text" value="123.456.789-00"></div>
<div class="form-group"><label for="telefone">Telefone</label><input id="telefone" name="telefone" type="text" value="(92) 99999-1020"></div>
<div class="form-group"><label for="status">Status</label><select id="status" name="status"><option selected>Ativo</option><option>Pendente</option></select></div>
<div class="form-group col-2"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="4">Cliente com cadastro conferido pela recepção.</textarea></div>
<div class="form-actions col-2"><a href="<?= route_url('recepcao', 'clientes') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Atualizar Cliente</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
