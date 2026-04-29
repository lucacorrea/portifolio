<?php
$paginaAtual = 'clientes';
$paginaTitulo = 'Visualizar Cliente';
$paginaDescricao = 'Página própria para consulta do cadastro.';
$usuarioNome = 'Maria Souza';
$usuarioCargo = 'Recepção';
$textoBotaoAcao = 'Editar Cliente';
$linkBotaoAcao = route_url('recepcao', 'clienteEditar');
$tituloPagina = 'Recepção - Visualizar Cliente';
$cssPagina = 'assets/css/recepcao/styleRecepcao.css';
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="layout"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="content"><?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="table-card"><div class="section-header"><div><h2>Carlos Henrique</h2><p>CPF 123.456.789-00 - (92) 99999-1020</p></div><a href="<?= route_url('recepcao', 'clientes') ?>" class="chip">Voltar</a></div>
<div class="info-grid"><div class="info-card"><strong>Último atendimento</strong><p>22/04/2026, protocolo PRT-2026-0418.</p></div><div class="info-card"><strong>Situação</strong><p>Cadastro ativo e pronto para novos protocolos.</p></div><div class="info-card"><strong>Observações</strong><p>Cliente com dados básicos conferidos.</p></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?></main></div><?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
