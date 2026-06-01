<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
$pageId = 'relatorios';
$pageTitle = 'Relatórios';
$activeMenu = '';
require_once __DIR__ . '/layout/header.php';
?>
      
<header class="plain-header">
  
    <div class="statusbar dark">
      <strong data-time>09:41</strong>
      <div class="device-icons" aria-hidden="true">
        <span class="signal"></span>
        <span class="wifi"></span>
        <span class="battery"></span>
      </div>
    </div>
    
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Indicadores</p>
      <h1>Relatórios</h1>
    </div>
    <a class="icon-btn light" href="configuracoes.php">⚙</a>
  </div>
</header>

<section class="content-pad">
  <div class="filter-pills" id="reportFilters">
    <button class="active" data-report-filter="Hoje">Hoje</button>
    <button data-report-filter="Semana">Semana</button>
    <button data-report-filter="Mês">Mês</button>
    <button data-report-filter="Personalizado">Personalizado</button>
  </div>

  <div id="customReportFilter" hidden class="form-card section-gap-small">
    <div class="form-grid">
      <div class="field"><label>Data inicial</label><input id="reportStartDate" type="date"></div>
      <div class="field"><label>Data final</label><input id="reportEndDate" type="date"></div>
    </div>
    <button class="secondary-btn section-gap-small" data-apply-report-filter>Aplicar filtro</button>
  </div>

  <div class="button-row three section-gap-small">
    <button class="secondary-btn" data-download-report-pdf>PDF</button>
    <button class="secondary-btn" data-export-csv>CSV</button>
    <button class="secondary-btn" data-share-report>Enviar</button>
  </div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Resumo financeiro</h2>
      <p>Tabelas e gráficos no início</p>
    </div>
  </div>

  <div class="finance-grid" id="reportFinance"></div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Evolução semanal</h2>
      <p>Vendas por dia</p>
    </div>
  </div>

  <div class="chart-card" id="weeklyBars"></div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Métodos de pagamento</h2>
      <p>Distribuição por forma</p>
    </div>
  </div>

  <div class="payment-card">
    <div class="donut"></div>
    <div class="payment-lines">
      <p><span>Nenhum pagamento registrado</span><strong>0%</strong></p>
    </div>
  </div>

  <div id="reportTables"></div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
