<?php
$pageTitle = 'Relatórios';
$activePage = 'relatorios';
$pageCss = ['reports'];
$pageJs = ['relatorios'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>

  <section class="page-header">
    <div>
      <span class="eyebrow">Análise executiva</span>
      <h1>Relatórios</h1>
      <p>Analise resultados, serviços, orçamentos, peças utilizadas e faturamento por período.</p>
    </div>
    <div class="page-header__actions">
      <button class="btn btn--primary">Exportar PDF</button>
      <button class="btn btn--secondary">Exportar Excel</button>
      <button class="btn btn--secondary">Imprimir</button>
    </div>
  </section>

  <section class="panel report-filter">
    <div class="quick-periods">
      <button class="period-btn is-active">Hoje</button>
      <button class="period-btn">7 dias</button>
      <button class="period-btn">30 dias</button>
      <button class="period-btn">Este mês</button>
      <button class="period-btn">Mês passado</button>
      <button class="period-btn">Este ano</button>
    </div>

    <div class="report-filter__grid">
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
      <button class="btn btn--primary report-filter__button">Gerar relatório</button>
    </div>
  </section>

  <section class="report-summary" id="reportSummary">
    <article class="report-card skeleton"></article>
    <article class="report-card skeleton"></article>
    <article class="report-card skeleton"></article>
    <article class="report-card skeleton"></article>
    <article class="report-card skeleton"></article>
    <article class="report-card skeleton"></article>
  </section>

  <section class="report-grid">
    <article class="panel panel--large">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Faturamento</span>
          <h2>Receita por mês</h2>
        </div>
      </div>
      <div class="chart-shell">
        <canvas id="reportRevenueChart" height="270"></canvas>
      </div>
    </article>

    <article class="panel panel--large">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Operação</span>
          <h2>Serviços mais realizados</h2>
        </div>
      </div>
      <div class="chart-shell">
        <canvas id="servicesChart" height="270"></canvas>
      </div>
    </article>
  </section>

  <section class="report-grid report-grid--secondary">
    <article class="panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Orçamentos</span>
          <h2>Aprovados x Recusados</h2>
        </div>
      </div>
      <div class="donut-wrap">
        <canvas id="budgetChart" width="280" height="220"></canvas>
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
      <div class="responsive-table report-table">
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Cliente</th>
              <th>Serviço</th>
              <th>Status</th>
              <th>Valor</th>
              <th>Nota</th>
            </tr>
          </thead>
          <tbody id="reportTable">
            <tr><td colspan="6">Carregando...</td></tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</main>
<?php include 'includes/footer.php'; ?>
