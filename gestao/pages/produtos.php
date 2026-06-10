<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
$pageId = 'produtos';
$pageTitle = 'Produtos';
$activeMenu = 'produtos';
require_once __DIR__ . '/layout/header.php';
?>
      
<header class="plain-header">
  
    
    
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Produtos e estoque</p>
      <h1>Produtos</h1>
    </div>
    <a class="round-btn" href="produto-form.php">+</a>
  </div>
</header>

<section class="content-pad">
  <label class="search-box">
    <span data-icon="search"></span>
    <input id="productSearch" type="search" placeholder="Buscar por nome, SKU, lote ou categoria">
  </label>

  <div class="filter-pills" id="productFilters">
    <button class="active" data-filter="Todos">Todos</button>
    <button data-filter="Estoque baixo">Estoque baixo</button>
    <button data-filter="Perto da validade">Perto da validade</button>
    <button data-filter="Vencidos">Vencidos</button>
  </div>

  <div id="productsList"></div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>