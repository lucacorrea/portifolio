<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();
$pageId = 'configuracoes';
$pageTitle = 'Configurações';
$activeMenu = 'mais';
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
      <p class="micro-label dark-text">Administração</p>
      <h1>Configurações</h1>
    </div>
    <a class="icon-btn light" href="../index.php">×</a>
  </div>
</header>

<section class="content-pad">
  <div class="settings-grid" id="settingsGrid"></div><a class="danger-btn section-gap" href="../logout.php">Sair do sistema</a>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>