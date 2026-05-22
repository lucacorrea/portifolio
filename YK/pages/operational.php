<div class="page-body operational-page" data-page="<?= htmlspecialchars($pageKey ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <div class="metrics-grid operational-summary-grid" id="summary-grid">
    <div class="metric-card skeleton" style="height:110px"></div>
    <div class="metric-card skeleton" style="height:110px"></div>
    <div class="metric-card skeleton" style="height:110px"></div>
    <div class="metric-card skeleton" style="height:110px"></div>
  </div>

  <div class="filter-bar" id="filter-bar">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" class="search-input" id="page-search" placeholder="Buscar...">
    </div>
    <div id="dynamic-filters" class="dynamic-filters"></div>
    <button class="btn-filter btn-filter-primary" type="button" onclick="applyPageFilters()">
      <i class="bi bi-funnel"></i> Filtrar
    </button>
    <button class="btn-filter btn-filter-ghost" type="button" onclick="clearPageFilters()">
      <i class="bi bi-x-lg"></i> Limpar
    </button>
  </div>

  <div class="operational-layout" id="operational-layout">
    <section class="panel main-data-panel">
      <div class="panel-header">
        <div class="panel-title" id="main-panel-title">
          <i class="bi bi-table"></i> Registros
        </div>
        <div class="panel-actions" id="main-panel-actions"></div>
      </div>

      <div class="os-table-wrap">
        <table class="os-table data-table">
          <thead id="data-table-head"></thead>
          <tbody id="data-table-body">
            <tr><td><div class="skeleton sk-row"></div></td></tr>
          </tbody>
        </table>
      </div>

      <div class="pagination-bar">
        <span id="table-count-label">—</span>
        <div class="pagination-controls" id="table-pagination"></div>
      </div>
    </section>

    <aside class="side-panels page-side-panels" id="page-side-panels"></aside>
  </div>

  <section id="secondary-content"></section>
</div>
