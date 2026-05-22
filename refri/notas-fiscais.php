<?php
$pageTitle = 'Notas Fiscais';
$activePage = 'notas';
$pageCss = ['tables'];
$pageJs = ['tables'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header"><div><span class="eyebrow">Controle fiscal</span><h1>Notas Fiscais</h1><p>Acompanhe notas vinculadas a OS e orçamentos. A emissão real deve ser integrada depois com certificado e provedor fiscal.</p></div><div class="page-header__actions"><button class="btn btn--primary">Nova Nota</button><button class="btn btn--secondary">Configurar Fiscal</button></div></section>
  <section class="grid-4 module-summary" id="summaryCards"></section>
  <section class="filter-panel"><div class="filter-grid"><label class="field"><span>Buscar</span><input id="tableSearch" type="search" placeholder="Cliente, chave, número..."></label><label class="field"><span>Status</span><select id="filterStatus"><option value="">Todos</option><option>Não emitida</option><option>Pendente</option><option>Emitida</option><option>Rejeitada</option><option>Cancelada</option></select></label><label class="field"><span>Tipo</span><select><option>Todos</option><option>NFS-e</option><option>NF-e</option></select></label><label class="field"><span>Data inicial</span><input type="date"></label><label class="field"><span>Data final</span><input type="date"></label><button class="btn btn--primary" id="btnFilter">Filtrar</button><button class="btn btn--secondary" id="btnClear">Limpar</button></div></section>
  <section class="panel"><div class="panel__header"><div><span class="eyebrow">Fiscal</span><h2>Notas e documentos</h2></div></div><div class="responsive-table"><table><thead><tr><th>Nota</th><th>Cliente</th><th>OS</th><th>Tipo</th><th>Status</th><th>Valor</th><th>Data</th><th>Ações</th></tr></thead><tbody id="tableBody" data-endpoint="notas"></tbody></table></div><div class="pagination"><span id="tableCount">Carregando...</span><div class="action-strip"><button class="btn btn--secondary btn--sm">Anterior</button><button class="btn btn--secondary btn--sm">Próxima</button></div></div></section>
</main>
<?php include 'includes/footer.php'; ?>
