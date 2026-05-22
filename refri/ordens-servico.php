<?php
$pageTitle = 'Ordens de Serviço';
$activePage = 'ordens';
$pageCss = ['tables'];
$pageJs = ['tables'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header"><div><span class="eyebrow">Execução técnica</span><h1>Ordens de Serviço</h1><p>Acompanhe solicitações, técnicos, equipamentos, peças utilizadas, pagamentos e finalização.</p></div><div class="page-header__actions"><button class="btn btn--primary" data-modal="os">+ Nova OS</button><a class="btn btn--secondary" href="orcamentos.php">Ver Orçamentos</a></div></section>
  <section class="grid-4 module-summary" id="summaryCards"></section>
  <section class="filter-panel"><div class="filter-grid"><label class="field"><span>Buscar</span><input id="tableSearch" type="search" placeholder="Número, cliente, técnico..."></label><label class="field"><span>Status</span><select id="filterStatus"><option value="">Todos</option><option>Aberta</option><option>Agendada</option><option>Em andamento</option><option>Aguardando peça</option><option>Finalizada</option><option>Cancelada</option></select></label><label class="field"><span>Técnico</span><select><option>Todos</option><option>Carlos</option><option>Rafael</option><option>Paulo</option></select></label><label class="field"><span>Data inicial</span><input type="date"></label><label class="field"><span>Data final</span><input type="date"></label><button class="btn btn--primary" id="btnFilter">Filtrar</button><button class="btn btn--secondary" id="btnClear">Limpar</button></div></section>
  <section class="panel"><div class="panel__header"><div><span class="eyebrow">Fila técnica</span><h2>OS em controle</h2></div><div class="action-strip"><button class="btn btn--secondary btn--sm">PDF geral</button><button class="btn btn--secondary btn--sm">Excel</button></div></div><div class="responsive-table"><table><thead><tr><th>OS</th><th>Cliente</th><th>Serviço</th><th>Equipamento</th><th>Status</th><th>Técnico</th><th>Valor</th><th>Ações</th></tr></thead><tbody id="tableBody" data-endpoint="os"></tbody></table></div><div class="pagination"><span id="tableCount">Carregando...</span><div class="action-strip"><button class="btn btn--secondary btn--sm">Anterior</button><button class="btn btn--secondary btn--sm">Próxima</button></div></div></section>
</main>
<?php include 'includes/footer.php'; ?>
