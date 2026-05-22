<?php
$pageTitle = 'Tipos de Serviço';
$activePage = 'servicos';
$pageCss = ['tables'];
$pageJs = ['tables'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header"><div><span class="eyebrow">Catálogo técnico</span><h1>Tipos de Serviço</h1><p>Padronize serviços, valores base, tempo médio e categorias para OS e orçamentos.</p></div><div class="page-header__actions"><button class="btn btn--primary" data-modal="servico">+ Novo Serviço</button></div></section>
  <section class="grid-4 module-summary" id="summaryCards"></section>
  <section class="filter-panel"><div class="filter-grid"><label class="field"><span>Buscar</span><input id="tableSearch" type="search" placeholder="Nome ou categoria..."></label><label class="field"><span>Categoria</span><select id="filterType"><option value="">Todas</option><option>Instalação</option><option>Manutenção</option><option>Visita técnica</option></select></label><label class="field"><span>Status</span><select id="filterStatus"><option value="">Todos</option><option>Ativo</option><option>Inativo</option></select></label><label class="field"><span>Tempo</span><select><option>Todos</option><option>Até 2h</option><option>Acima de 2h</option></select></label><label class="field"><span>Data</span><input type="date"></label><button class="btn btn--primary" id="btnFilter">Filtrar</button><button class="btn btn--secondary" id="btnClear">Limpar</button></div></section>
  <section class="panel"><div class="panel__header"><div><span class="eyebrow">Serviços padrão</span><h2>Catálogo de serviços</h2></div></div><div class="responsive-table"><table><thead><tr><th>Serviço</th><th>Categoria</th><th>Valor base</th><th>Tempo médio</th><th>Status</th><th>Ações</th></tr></thead><tbody id="tableBody" data-endpoint="servicos"></tbody></table></div><div class="pagination"><span id="tableCount">Carregando...</span><div class="action-strip"><button class="btn btn--secondary btn--sm">Anterior</button><button class="btn btn--secondary btn--sm">Próxima</button></div></div></section>
</main>
<?php include 'includes/footer.php'; ?>
