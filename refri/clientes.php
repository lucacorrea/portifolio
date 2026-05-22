<?php
$pageTitle = 'Clientes';
$activePage = 'clientes';
$pageCss = ['tables'];
$pageJs = ['tables'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header"><div><span class="eyebrow">Base de atendimento</span><h1>Clientes</h1><p>Gerencie dados de contato, endereços, histórico de OS e orçamentos por cliente.</p></div><div class="page-header__actions"><button class="btn btn--primary" data-modal="cliente">+ Novo Cliente</button><button class="btn btn--secondary">Importar</button></div></section>
  <section class="grid-4 module-summary" id="summaryCards"></section>
  <section class="filter-panel"><div class="filter-grid"><label class="field"><span>Buscar</span><input id="tableSearch" type="search" placeholder="Nome, CPF/CNPJ, telefone..."></label><label class="field"><span>Tipo</span><select id="filterType"><option value="">Todos</option><option>Pessoa Jurídica</option><option>Pessoa Física</option></select></label><label class="field"><span>Status</span><select id="filterStatus"><option value="">Todos</option><option>Ativo</option><option>Inativo</option></select></label><label class="field"><span>Data inicial</span><input type="date"></label><label class="field"><span>Data final</span><input type="date"></label><button class="btn btn--primary" id="btnFilter">Filtrar</button><button class="btn btn--secondary" id="btnClear">Limpar</button></div></section>
  <section class="panel"><div class="panel__header"><div><span class="eyebrow">Listagem</span><h2>Clientes cadastrados</h2></div><div class="action-strip"><button class="btn btn--secondary btn--sm">Exportar</button></div></div><div class="responsive-table"><table><thead><tr><th>Cliente</th><th>Contato</th><th>Tipo</th><th>Cidade</th><th>OS ativa</th><th>Status</th><th>Ações</th></tr></thead><tbody id="tableBody" data-endpoint="clientes"></tbody></table></div><div class="pagination"><span id="tableCount">Carregando...</span><div class="action-strip"><button class="btn btn--secondary btn--sm">Anterior</button><button class="btn btn--secondary btn--sm">Próxima</button></div></div></section>
</main>
<?php include 'includes/footer.php'; ?>
