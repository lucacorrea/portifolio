<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();


$pageId = 'historico-vendas';
$pageTitle = 'Histórico';
$activeMenu = '';
require_once __DIR__ . '/layout/header.php';
?>
      
<header class="plain-header">
  
  
    
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
    <button data-sales-filter="Todos">Todos</button>
  </div>

  <div class="filter-pills" id="salesPaymentFilters">
    <button class="active" data-sales-payment-filter="Todos">Todos</button>
    <button data-sales-payment-filter="PIX">PIX</button>
    <button data-sales-payment-filter="Cartão">Cartão</button>
    <button data-sales-payment-filter="Dinheiro">Dinheiro</button>
    <button data-sales-payment-filter="Fiado">Fiado</button>
  </div>

  <div id="salesHistoryList"></div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
