<?php
$pageTitle = 'Padrão de Tabelas';
$activePage = 'clientes';
$pageCss = ['tables'];
$pageJs = ['tables'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header"><div><span class="eyebrow">Modelo reutilizável</span><h1>Página padrão de tabelas</h1><p>Modelo base para listagens administrativas. Use este padrão em clientes, OS, orçamentos, peças, serviços, notas e usuários.</p></div><div class="page-header__actions"><a class="btn btn--primary" href="clientes.php">Ver clientes</a><a class="btn btn--secondary" href="ordens-servico.php">Ver OS</a></div></section>
  <section class="grid-4 module-summary" id="summaryCards"></section>
  <section class="filter-panel"><div class="filter-grid"><label class="field"><span>Buscar</span><input id="tableSearch" type="search" placeholder="Busque na listagem..."></label><label class="field"><span>Tipo</span><select id="filterType"><option value="">Todos</option><option>Pessoa Jurídica</option><option>Pessoa Física</option></select></label><label class="field"><span>Status</span><select id="filterStatus"><option value="">Todos</option><option>Ativo</option><option>Inativo</option></select></label><label class="field"><span>Data inicial</span><input type="date"></label><label class="field"><span>Data final</span><input type="date"></label><button class="btn btn--primary" id="btnFilter">Filtrar</button><button class="btn btn--secondary" id="btnClear">Limpar</button></div></section>
  <section class="panel"><div class="panel__header"><div><span class="eyebrow">Tabela premium</span><h2>Exemplo com clientes</h2></div><div class="action-strip"><button class="btn btn--secondary btn--sm">Exportar</button><button class="btn btn--secondary btn--sm">Imprimir</button></div></div><div class="responsive-table"><table><thead><tr><th>Cliente</th><th>Contato</th><th>Tipo</th><th>Cidade</th><th>OS ativa</th><th>Status</th><th>Ações</th></tr></thead><tbody id="tableBody" data-endpoint="clientes"></tbody></table></div><div class="pagination"><span id="tableCount">Carregando...</span><div class="action-strip"><button class="btn btn--secondary btn--sm">Anterior</button><button class="btn btn--secondary btn--sm">Próxima</button></div></div></section>
</main>
<?php include 'includes/footer.php'; ?>
