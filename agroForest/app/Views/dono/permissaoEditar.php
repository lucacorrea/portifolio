<?php
$paginaAtual = 'permissoes';
$paginaTitulo = 'Editar Permissão';
$paginaDescricao = 'Página própria para atualizar perfil de acesso.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Visualizar Permissão';
$linkBotaoAcao = route_url('dono', 'permissaoVisualizar');
$tituloPagina = 'Dono - Editar Permissão';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="card panel"><div class="panel-header"><div><h2>Edição da permissão</h2><p>Atualize o perfil sem misturar com a listagem.</p></div></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="perfil">Perfil</label><input id="perfil" name="perfil" type="text" value="Recepção"></div>
<div class="form-group"><label for="area">Área</label><select id="area" name="area"><option selected>Operação</option><option>Análise</option><option>Gestão</option></select></div>
<div class="form-group col-2"><label for="permissoes">Permissões</label><textarea id="permissoes" name="permissoes" rows="4">Clientes, protocolos, documentos e pendências.</textarea></div>
<div class="form-actions col-2"><a href="<?= route_url('dono', 'permissoes') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Atualizar Permissão</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
