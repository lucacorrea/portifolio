<?php
$paginaAtual = 'usuarios';
$paginaTitulo = 'Editar Usuário';
$paginaDescricao = 'Página própria para atualizar usuário.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Visualizar Usuário';
$linkBotaoAcao = route_url('dono', 'usuarioVisualizar');
$tituloPagina = 'Dono - Editar Usuário';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="card panel"><div class="panel-header"><div><h2>Edição do usuário</h2><p>Atualize perfil, status e dados de acesso.</p></div></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="nome">Nome</label><input id="nome" name="nome" type="text" value="Maria Souza"></div>
<div class="form-group"><label for="email">E-mail</label><input id="email" name="email" type="email" value="maria@agroforest.com"></div>
<div class="form-group"><label for="perfil">Perfil</label><select id="perfil" name="perfil"><option selected>Recepção</option><option>Administrativo</option><option>Dono</option></select></div>
<div class="form-group"><label for="status">Status</label><select id="status" name="status"><option selected>Ativo</option><option>Inativo</option><option>Em revisão</option></select></div>
<div class="form-actions col-2"><a href="<?= route_url('dono', 'usuarios') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Atualizar Usuário</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
