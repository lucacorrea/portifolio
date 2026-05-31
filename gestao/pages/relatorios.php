<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/security/auth.php';
requireLogin();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1" />
  <meta name="theme-color" content="#1657A7" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-title" content="L&J Caixa" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <title>Relatórios | L&J Caixa</title>
  <link rel="manifest" href="../manifest.json" />
  <link rel="icon" href="../assets/icons/icon.svg" type="image/svg+xml" />
  <link rel="stylesheet" href="../assets/css/styles.css" />
</head>
<body data-page="relatorios" data-prefix="../">
  <main class="phone-app">
    <section class="screen">
      
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
      <div class="field"><label>Data inicial</label><input type="date" value="2026-05-01"></div>
      <div class="field"><label>Data final</label><input type="date" value="2026-05-28"></div>
    </div>
    <button class="secondary-btn section-gap-small" data-toast="Filtro aplicado">Aplicar filtro</button>
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
      <p><span><span class="dot pix"></span>PIX</span><strong>44%</strong></p>
      <p><span><span class="dot card"></span>Cartão</span><strong>42%</strong></p>
      <p><span><span class="dot cash"></span>Dinheiro</span><strong>14%</strong></p>
    </div>
  </div>

  <div id="reportTables"></div>
</section>

    </section>
    <nav class="bottom-nav" aria-label="Navegação principal">
<a class="" href="../index.php"><svg viewBox="0 0 24 24"><path d="M4 11.5 12 5l8 6.5V20H4z"/></svg><span>Início</span></a>
<a class="" href="nova-venda.php"><svg viewBox="0 0 24 24"><path d="M6 5h12v14H6z"/><path d="M9 9h6"/><path d="M9 13h4"/></svg><span>Vendas</span></a>
<a class="center-action" href="nova-venda.php" aria-label="Nova venda"><strong>+</strong></a>
<a class="" href="produtos.php"><svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg><span>Produtos</span></a>
<a class="active" href="configuracoes.php"><svg viewBox="0 0 24 24"><path d="M12 5v.01"/><path d="M12 12v.01"/><path d="M12 19v.01"/></svg><span>Mais</span></a>
</nav>
    <div class="modal-backdrop" id="modalBackdrop" hidden>
      <section class="modal-card" id="modalCard" role="dialog" aria-modal="true"></section>
    </div>
    <div class="toast" id="toast"></div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script src="../assets/js/data.js"></script>
  <script src="../assets/js/app.js"></script>
</body>
</html>