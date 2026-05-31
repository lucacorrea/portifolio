<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
?>

$pageId = 'historico-vendas';
$pageTitle = 'Histórico';
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

<?php require_once __DIR__ . '/layout/footer.php'; ?>