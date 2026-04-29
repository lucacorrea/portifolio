<?php
$paginaAtual = 'usuarios';
$paginaTitulo = 'Cadastrar Usuário';
$paginaDescricao = 'Página própria para criar usuário.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Voltar para Usuários';
$linkBotaoAcao = route_url('dono', 'usuarios');
$tituloPagina = 'Dono - Cadastrar Usuário';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="card panel"><div class="panel-header"><div><h2>Dados do usuário</h2><p>Cadastro separado da listagem principal.</p></div></div>
<form class="form-grid" method="POST" action="">
<div class="form-group"><label for="nome">Nome</label><input id="nome" name="nome" type="text" placeholder="Nome completo"></div>
<div class="form-group"><label for="email">E-mail</label><input id="email" name="email" type="email" placeholder="usuario@email.com"></div>
<div class="form-group"><label for="perfil">Perfil</label><select id="perfil" name="perfil"><option>Recepção</option><option>Administrativo</option><option>Dono</option></select></div>
<div class="form-group"><label for="status">Status</label><select id="status" name="status"><option>Ativo</option><option>Inativo</option><option>Em revisão</option></select></div>
<div class="form-actions col-2"><a href="<?= route_url('dono', 'usuarios') ?>" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Salvar Usuário</button></div>
</form></section><?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
