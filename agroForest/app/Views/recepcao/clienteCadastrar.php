<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Cadastrar Cliente';
$paginaDescricao = 'Página própria para criar cadastro de cliente.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Voltar para Clientes';
$linkBotaoAcao = route_url('recepcao', 'clientes');
$tituloPagina = 'Recepção - Cadastrar Cliente';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="form-card"><div class="section-header"><h2>Dados do cliente</h2><p>Use esta página apenas para cadastro.</p></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="nome">Nome</label><input id="nome" name="nome" type="text" placeholder="Nome completo"></div>
<div class="form-group"><label for="documento">CPF / CNPJ</label><input id="documento" name="documento" type="text" placeholder="000.000.000-00"></div>
<div class="form-group"><label for="telefone">Telefone</label><input id="telefone" name="telefone" type="text" placeholder="(00) 00000-0000"></div>
<div class="form-group"><label for="email">E-mail</label><input id="email" name="email" type="email" placeholder="cliente@email.com"></div>
<div class="form-group col-2"><label for="endereco">Endereço</label><input id="endereco" name="endereco" type="text" placeholder="Rua, número, bairro e cidade"></div>
<div class="form-actions col-2"><a href="<?= route_url('recepcao', 'clientes') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Salvar Cliente</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
