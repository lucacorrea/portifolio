<?php
$pageTitle = 'Peças';
$activePage = 'pecas';
$pageCss = ['tables'];
$pageJs = ['tables'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header"><div><span class="eyebrow">Estoque técnico</span><h1>Peças</h1><p>Controle peças, códigos internos, estoque mínimo, custo, venda e fornecedores.</p></div><div class="page-header__actions"><button class="btn btn--primary" data-modal="peca">+ Nova Peça</button><button class="btn btn--secondary">Entrada de estoque</button></div></section>
  <section class="grid-4 module-summary" id="summaryCards"></section>
  <section class="filter-panel"><div class="filter-grid"><label class="field"><span>Buscar</span><input id="tableSearch" type="search" placeholder="Nome, código, marca..."></label><label class="field"><span>Categoria</span><select id="filterType"><option value="">Todas</option><option>Compressor</option><option>Elétrica</option><option>Filtro</option></select></label><label class="field"><span>Status</span><select id="filterStatus"><option value="">Todos</option><option>Normal</option><option>Estoque baixo</option><option>Sem estoque</option></select></label><label class="field"><span>Fornecedor</span><select><option>Todos</option><option>Refricenter</option><option>Polar Peças</option></select></label><label class="field"><span>Data</span><input type="date"></label><button class="btn btn--primary" id="btnFilter">Filtrar</button><button class="btn btn--secondary" id="btnClear">Limpar</button></div></section>
  <section class="panel"><div class="panel__header"><div><span class="eyebrow">Inventário</span><h2>Peças cadastradas</h2></div></div><div class="responsive-table"><table><thead><tr><th>Peça</th><th>Código</th><th>Categoria</th><th>Estoque</th><th>Custo</th><th>Venda</th><th>Status</th><th>Ações</th></tr></thead><tbody id="tableBody" data-endpoint="pecas"></tbody></table></div><div class="pagination"><span id="tableCount">Carregando...</span><div class="action-strip"><button class="btn btn--secondary btn--sm">Anterior</button><button class="btn btn--secondary btn--sm">Próxima</button></div></div></section>
</main>
<?php include 'includes/footer.php'; ?>
