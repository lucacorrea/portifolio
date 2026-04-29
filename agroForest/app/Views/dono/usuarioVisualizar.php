<?php
$paginaAtual = 'usuarios';
$paginaTitulo = 'Visualizar Usuário';
$paginaDescricao = 'Página própria para consultar usuário.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Editar Usuário';
$linkBotaoAcao = route_url('dono', 'usuarioEditar');
$tituloPagina = 'Dono - Visualizar Usuário';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="card panel"><div class="panel-header"><div><h2>Maria Souza</h2><p>maria@agroforest.com - Recepção</p></div><a href="<?= route_url('dono', 'usuarios') ?>" class="chip">Voltar</a></div>
<div class="config-grid"><div class="setting-block"><h3>Status</h3><p>Usuária ativa.</p></div><div class="setting-block"><h3>Permissões</h3><p>Acesso a clientes, protocolos, documentos e pendências da recepção.</p></div><div class="setting-block"><h3>Último acesso</h3><p>29/04/2026 às 09:30.</p></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
