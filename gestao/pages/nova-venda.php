<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
$pageId = 'nova-venda';
$pageTitle = 'Nova Venda';
$activeMenu = 'vendas';
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
      <p class="micro-label dark-text">Frente de caixa</p>
      <h1>Nova venda</h1>
    </div>
    <button class="round-btn" data-reset-sale>+</button>
  </div>
</header>

<section class="content-pad">
  <div class="stepper" id="saleStepper"></div>
  <div id="saleWizard"></div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>