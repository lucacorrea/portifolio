<?php
$paginaAtual = 'permissoes';
$paginaTitulo = 'Visualizar Permissão';
$paginaDescricao = 'Página própria para consultar perfil de acesso.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$textoBotaoAcao = 'Editar Permissão';
$linkBotaoAcao = route_url('dono', 'permissaoEditar');
$tituloPagina = 'Dono - Visualizar Permissão';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="card panel"><div class="panel-header"><div><h2>Recepção</h2><p>Perfil operacional para abertura e acompanhamento de protocolos.</p></div><a href="<?= route_url('dono', 'permissoes') ?>" class="chip">Voltar</a></div>
<div class="config-grid"><div class="setting-block"><h3>Área</h3><p>Operação.</p></div><div class="setting-block"><h3>Permissões</h3><p>Clientes, protocolos, documentos e pendências.</p></div><div class="setting-block"><h3>Status</h3><p>Ativo.</p></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
