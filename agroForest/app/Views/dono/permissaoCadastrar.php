<?php
$paginaAtual = 'permissoes';
$paginaTitulo = 'Cadastrar Permissão';
$paginaDescricao = 'Página própria para criar perfil de acesso.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Voltar para Permissões';
$linkBotaoAcao = route_url('dono', 'permissoes');
$tituloPagina = 'Dono - Cadastrar Permissão';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="card panel"><div class="panel-header"><div><h2>Dados da permissão</h2><p>Criação separada da listagem.</p></div></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="perfil">Perfil</label><input id="perfil" name="perfil" type="text" placeholder="Nome do perfil"></div>
<div class="form-group"><label for="area">Área</label><select id="area" name="area"><option>Recepção</option><option>Administrativo</option><option>Gestão</option></select></div>
<div class="form-group col-2"><label for="permissoes">Permissões</label><textarea id="permissoes" name="permissoes" rows="4" placeholder="Liste os módulos liberados"></textarea></div>
<div class="form-actions col-2"><a href="<?= route_url('dono', 'permissoes') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Salvar Permissão</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
