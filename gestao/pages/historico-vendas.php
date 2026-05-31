<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

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
  <title>Histórico | L&J Caixa</title>
  <link rel="manifest" href="../manifest.json" />
  <link rel="icon" href="../assets/icons/icon.svg" type="image/svg+xml" />
  <link rel="stylesheet" href="../assets/css/styles.css" />
</head>
<body data-page="historico-vendas" data-prefix="../">
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
      <p class="micro-label dark-text">Histórico</p>
      <h1>Vendas</h1>
    </div>
    <a class="round-btn" href="nova-venda.php">+</a>
  </div>
</header>

<section class="content-pad">
  <label class="search-box">
    <span data-icon="search"></span>
    <input id="salesSearch" type="search" placeholder="Buscar venda, cliente, produto ou operador">
  </label>

  <div class="filter-pills" id="salesFilters">
    <button class="active" data-sales-filter="Hoje">Hoje</button>
    <button data-sales-filter="Semana">Semana</button>
    <button data-sales-filter="Mês">Mês</button>
    <button data-sales-filter="Personalizado">Personalizado</button>
  </div>

  <div class="filter-pills">
    <button data-toast="Filtro aplicado">Todos</button>
    <button data-toast="Filtro PIX aplicado">PIX</button>
    <button data-toast="Filtro Cartão aplicado">Cartão</button>
    <button data-toast="Filtro Dinheiro aplicado">Dinheiro</button>
    <button data-toast="Filtro Fiado aplicado">Fiado</button>
  </div>

  <div id="salesHistoryList"></div>
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