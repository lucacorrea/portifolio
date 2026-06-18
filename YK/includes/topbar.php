<?php
$primaryActionLabel = $primaryActionLabel ?? 'Nova OS';
$primaryActionIcon = $primaryActionIcon ?? 'bi-plus-lg';
$primaryActionTarget = $primaryActionTarget ?? '#modal-os';
$pageSubtitle = $pageSubtitle ?? 'Gestão de serviços';
?>

<header class="topbar">
  <div class="topbar-left">
    <button class="mobile-menu-btn" type="button" aria-label="Abrir menu" data-sidebar-toggle>
      <i class="bi bi-list"></i>
    </button>
    <div>
      <nav class="breadcrumb-wrap" aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="dashboard.php">Início</a></li>
          <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
      </nav>
      <div class="topbar-heading">
        <span class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></span>
        <span class="topbar-subtitle"><?= htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>
  </div>
  <div class="topbar-right">
    <button class="tb-icon-btn" type="button" title="Notificações">
      <i class="bi bi-bell"></i>
      <span class="notif-dot"></span>
    </button>
    <button class="tb-icon-btn" type="button" title="Ajuda visual">
      <i class="bi bi-question-circle"></i>
    </button>
    <button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="<?= htmlspecialchars($primaryActionTarget, ENT_QUOTES, 'UTF-8') ?>">
      <i class="bi <?= htmlspecialchars($primaryActionIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
      <span><?= htmlspecialchars($primaryActionLabel, ENT_QUOTES, 'UTF-8') ?></span>
    </button>
  </div>
</header>
