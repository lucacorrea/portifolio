<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
?>

$pageId = 'venda-detalhes';
$pageTitle = 'Venda';
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
    <a class="back-btn" href="historico-vendas.php">‹</a>
    <div>
      <p class="micro-label dark-text">Detalhes da venda</p>
      <h1 id="saleTitle">Venda</h1>
    </div>
    <span></span>
  </div>
</header>

<section class="content-pad" id="saleDetailContent"></section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>