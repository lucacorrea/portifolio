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
  <section class="page-header"><div><span class="eyebrow">Análise executiva</span><h1>Relatórios</h1><p>Analise resultados, serviços executados, orçamentos, peças utilizadas, faturamento e notas pendentes.</p></div><div class="page-header__actions"><button class="btn btn--primary" id="exportPdf">Exportar PDF</button><button class="btn btn--secondary" id="exportExcel">Exportar Excel</button><button class="btn btn--secondary" onclick="window.print()">Imprimir</button></div></section>
  <section class="panel report-filter"><div class="quick-periods"><button class="period-btn is-active">Hoje</button><button class="period-btn">7 dias</button><button class="period-btn">30 dias</button><button class="period-btn">Este mês</button><button class="period-btn">Mês passado</button><button class="period-btn">Este ano</button></div><div class="report-filter__grid"><label class="field"><span>Data inicial</span><input type="date" value="2026-05-01"></label><label class="field"><span>Data final</span><input type="date" value="2026-05-22"></label><label class="field"><span>Cliente</span><select><option>Todos</option><option>Mercado São José</option><option>Clínica Vida Norte</option></select></label><label class="field"><span>Status</span><select><option>Todos</option><option>Finalizada</option><option>Em andamento</option><option>Aguardando peça</option></select></label><label class="field"><span>Técnico</span><select><option>Todos</option><option>Carlos</option><option>Rafael</option></select></label><label class="field"><span>Pagamento</span><select><option>Todos</option><option>Pago</option><option>Pendente</option></select></label><button class="btn btn--primary">Gerar</button></div></section>
  <section class="report-summary" id="reportSummary"></section>
  <section class="report-grid"><article class="panel"><div class="panel__header"><div><span class="eyebrow">Receita</span><h2>Faturamento por mês</h2></div></div><div class="chart-shell"><canvas id="revenueReportChart"></canvas></div></article><article class="panel"><div class="panel__header"><div><span class="eyebrow">Serviços</span><h2>Mais realizados</h2></div></div><div class="chart-shell"><canvas id="servicesReportChart"></canvas></div></article></section>
  <section class="report-grid report-grid--balanced"><article class="panel"><div class="panel__header"><div><span class="eyebrow">Orçamentos</span><h2>Aprovados x recusados</h2></div></div><div class="chart-shell"><canvas id="budgetReportChart"></canvas></div></article><article class="panel"><div class="panel__header"><div><span class="eyebrow">Peças</span><h2>Mais utilizadas</h2></div></div><div class="responsive-table"><table><thead><tr><th>Peça</th><th>Qtd.</th><th>Total</th><th>Status</th></tr></thead><tbody id="partsReportTable"></tbody></table></div></article></section>
  <section class="panel"><div class="panel__header"><div><span class="eyebrow">Tabela analítica</span><h2>Resultado detalhado</h2></div></div><div class="responsive-table"><table><thead><tr><th>Data</th><th>Cliente</th><th>Tipo</th><th>Serviço</th><th>Status</th><th>Técnico</th><th>Valor</th><th>Pagamento</th><th>Nota</th></tr></thead><tbody id="reportTable"><tr><td colspan="9">Carregando...</td></tr></tbody></table></div></section>
</main>
<?php include 'includes/footer.php'; ?>
