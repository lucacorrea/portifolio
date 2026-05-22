<?php
$pageTitle = 'Relatórios';
$activePage = 'relatorios';
$pageCss = ['reports'];
$pageJs = ['relatorios'];
$topbarSearchPlaceholder = 'Buscar cliente, técnico, serviço, nota ou status...';
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>

  <section class="page-header">
    <div>
      <span class="eyebrow">Análise executiva</span>
      <h1>Relatórios</h1>
      <p>Acompanhe resultados, serviços, peças utilizadas, orçamentos e faturamento por período.</p>
    </div>
    <div class="page-header__actions">
      <button class="btn btn--primary" id="exportPdf" type="button">Exportar PDF</button>
      <button class="btn btn--secondary" id="exportExcel" type="button">Exportar Excel</button>
      <button class="btn btn--secondary" id="printReport" type="button">Imprimir</button>
    </div>
  </section>

  <section class="panel report-filter">
    <div class="quick-periods">
      <button class="period-btn is-active" type="button">Hoje</button>
      <button class="period-btn" type="button">7 dias</button>
      <button class="period-btn" type="button">30 dias</button>
      <button class="period-btn" type="button">Este mês</button>
      <button class="period-btn" type="button">Mês passado</button>
      <button class="period-btn" type="button">Este ano</button>
    </div>
    <div class="filter-panel__grid">
      <label class="field">
        <span>Data inicial</span>
        <input type="date" value="2026-05-01">
      </label>
      <label class="field">
        <span>Data final</span>
        <input type="date" value="2026-05-22">
      </label>
      <label class="field">
        <span>Cliente</span>
        <select>
          <option>Todos os clientes</option>
          <option>Mercado São José</option>
          <option>Clínica Vida Norte</option>
        </select>
      </label>
      <label class="field">
        <span>Status</span>
        <select>
          <option>Todos os status</option>
          <option>Finalizada</option>
          <option>Em andamento</option>
          <option>Aguardando peça</option>
        </select>
      </label>
      <button class="btn btn--primary" type="button">Gerar</button>
    </div>
  </section>

  <section class="grid-4" id="reportSummary" aria-live="polite"></section>

  <section class="report-grid">
    <article class="panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Faturamento</span>
          <h2>Receita por mês</h2>
        </div>
      </div>
      <div class="report-chart">
        <canvas id="revenueReportChart" height="260"></canvas>
      </div>
    </article>

    <article class="panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Operação</span>
          <h2>Serviços mais realizados</h2>
        </div>
      </div>
      <div class="report-chart">
        <canvas id="servicesReportChart" height="260"></canvas>
      </div>
    </article>
  </section>

  <section class="report-grid report-grid--secondary">
    <article class="panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Orçamentos</span>
          <h2>Aprovados x recusados</h2>
        </div>
      </div>
      <div class="donut-wrap">
        <canvas id="budgetReportChart" width="280" height="220"></canvas>
        <div class="donut-legend" id="budgetLegend"></div>
      </div>
    </article>

    <article class="panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Tabela analítica</span>
          <h2>Resultado detalhado</h2>
        </div>
      </div>
      <div class="responsive-table">
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Cliente</th>
              <th>Tipo</th>
              <th>Serviço</th>
              <th>Status</th>
              <th>Técnico</th>
              <th>Valor</th>
              <th>Pagamento</th>
              <th>Nota</th>
            </tr>
          </thead>
          <tbody id="reportTable">
            <tr><td colspan="9">Carregando relatório...</td></tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>

  <section class="panel">
    <div class="panel__header">
      <div>
        <span class="eyebrow">Peças</span>
        <h2>Consumo e estoque crítico</h2>
      </div>
    </div>
    <div class="responsive-table">
      <table>
        <thead>
          <tr>
            <th>Peça</th>
            <th>Qtd.</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="partsReportTable">
          <tr><td colspan="4">Carregando peças...</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php include 'includes/footer.php'; ?>
