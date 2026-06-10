<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();


$pageId = 'comprovante';
$pageTitle = 'Comprovante';
$activeMenu = '';
require_once __DIR__ . '/layout/header.php';
?>
      
<header class="plain-header">
  
   
    
  <div class="page-title-row">
    <a class="back-btn" href="historico-vendas.php">‹</a>
    <div>
      <p class="micro-label dark-text">Comprovante</p>
      <h1 id="receiptTitle">Venda</h1>
    </div>
    <span></span>
  </div>
</header>

<section class="content-pad" id="receiptContentWrap"></section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>