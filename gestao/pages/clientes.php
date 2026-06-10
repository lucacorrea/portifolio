<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
$pageId = 'clientes';
$pageTitle = 'Clientes';
$activeMenu = 'mais';
require_once __DIR__ . '/layout/header.php';
?>
      
<header class="plain-header">
  
   
    
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Clientes e contas</p>
      <h1>Clientes</h1>
    </div>
    <button class="round-btn" data-new-client>+</button>
  </div>
</header>

<section class="content-pad">
  <label class="search-box">
    <span data-icon="search"></span>
    <input id="clientSearch" type="search" placeholder="Buscar por nome ou telefone">
  </label>

  <div class="filter-pills" id="clientFilters">
    <button class="active" data-client-filter="Todos">Todos</button>
    <button data-client-filter="Em dia">Em dia</button>
    <button data-client-filter="Devendo">Devendo</button>
    <button data-client-filter="Atrasados">Atrasados</button>
  </div>

  <div id="clientsList"></div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
