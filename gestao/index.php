<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
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
  <title>Dashboard | L&J Caixa</title>
  <link rel="manifest" href="manifest.json" />
  <link rel="icon" href="assets/icons/icon.svg" type="image/svg+xml" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body data-page="dashboard" data-prefix="">
  <main class="phone-app">
    <section class="screen">
      
<header class="blue-area">
  
    <div class="statusbar ">
      <strong data-time>09:41</strong>
      <div class="device-icons" aria-hidden="true">
        <span class="signal"></span>
        <span class="wifi"></span>
        <span class="battery"></span>
      </div>
    </div>
    
  <div class="balance-row">
    <div>
      <p class="micro-label">Vendido hoje</p>
      <h1 id="todayTotal">R$ 0,00</h1>
      <span class="subtle-light">L&J Soluções Tech • <span id="todaySalesCount">0</span> vendas</span>
    </div>
    <a class="avatar-btn" href="pages/configuracoes.php" aria-label="Configurações">
      <img src="assets/icons/icon.svg" alt="L&J" />
    </a>
  </div>

  <nav class="quick-menu" aria-label="Ações rápidas">
    <a href="pages/nova-venda.php"><i data-icon="receipt"></i><span>Venda</span></a>
    <a href="pages/produtos.php"><i data-icon="product"></i><span>Produtos</span></a>
    <a href="pages/clientes.php"><i data-icon="user"></i><span>Clientes</span></a>
    <a href="pages/relatorios.php"><i data-icon="report"></i><span>Relatórios</span></a>
    <a href="pages/historico-vendas.php"><i data-icon="box"></i><span>Histórico</span></a>
  </nav>
</header>

<section class="white-sheet">
  <div class="sheet-title">
    <div>
      <h2>Resumo financeiro</h2>
      <p>Recebimentos e lucro estimado</p>
    </div>
  </div>

  <div class="finance-grid" id="dashboardFinance"></div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Relatório diário</h2>
      <p>Resumo operacional do dia</p>
    </div>
  </div>

  <article class="summary-card" id="dailyReport"></article>

  <div class="sheet-title section-gap">
    <div>
      <h2>Produtos perto da validade</h2>
      <p>Alertas dos próximos dias</p>
    </div>
    <a class="small-link" href="pages/produtos.php">Ver</a>
  </div>

  <div class="list-card" id="expiringProducts"></div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Últimas vendas</h2>
      <p>Movimentações recentes do caixa</p>
    </div>
    <a class="small-link" href="pages/historico-vendas.php">Ver tudo</a>
  </div>

  <div class="list-card" id="latestSales"></div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Produtos em destaque</h2>
      <p>Mais vendidos hoje</p>
    </div>
  </div>

  <div class="list-card" id="featuredProducts"></div>
</section>

    </section>
    <nav class="bottom-nav" aria-label="Navegação principal">
<a class="active" href="../index.php"><svg viewBox="0 0 24 24"><path d="M4 11.5 12 5l8 6.5V20H4z"/></svg><span>Início</span></a>
<a class="" href="pages/nova-venda.php"><svg viewBox="0 0 24 24"><path d="M6 5h12v14H6z"/><path d="M9 9h6"/><path d="M9 13h4"/></svg><span>Vendas</span></a>
<a class="center-action" href="pages/nova-venda.php" aria-label="Nova venda"><strong>+</strong></a>
<a class="" href="pages/produtos.php"><svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg><span>Produtos</span></a>
<a class="" href="pages/configuracoes.php"><svg viewBox="0 0 24 24"><path d="M12 5v.01"/><path d="M12 12v.01"/><path d="M12 19v.01"/></svg><span>Mais</span></a>
</nav>
    <div class="modal-backdrop" id="modalBackdrop" hidden>
      <section class="modal-card" id="modalCard" role="dialog" aria-modal="true"></section>
    </div>
    <div class="toast" id="toast"></div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>