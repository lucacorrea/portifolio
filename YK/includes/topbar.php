<?php
$primaryActionLabel = $primaryActionLabel ?? 'Nova OS';
$primaryActionIcon = $primaryActionIcon ?? 'bi-plus-lg';
$primaryActionTarget = $primaryActionTarget ?? '#modal-os';
$pageSubtitle = $pageSubtitle ?? 'Gestão de serviços';
$showPrimaryAction = !isset($primaryActionPermission) || $authorization->can((string) $primaryActionPermission);
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
    <button class="tb-icon-btn" type="button" title="Notificações" aria-label="Notificações">
      <i class="bi bi-bell"></i>
      <span class="notif-dot"></span>
    </button>
    <button class="tb-icon-btn" type="button" title="Ajuda visual" aria-label="Ajuda visual">
      <i class="bi bi-question-circle"></i>
    </button>
    <?php if ($showPrimaryAction): ?>
      <?php if (isset($primaryActionHref) && is_string($primaryActionHref) && $primaryActionHref !== ''): ?>
      <a class="btn-new-os" href="<?= htmlspecialchars($primaryActionHref, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi <?= htmlspecialchars($primaryActionIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
        <span><?= htmlspecialchars($primaryActionLabel, ENT_QUOTES, 'UTF-8') ?></span>
      </a>
      <?php else: ?>
      <button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="<?= htmlspecialchars($primaryActionTarget, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi <?= htmlspecialchars($primaryActionIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
        <span><?= htmlspecialchars($primaryActionLabel, ENT_QUOTES, 'UTF-8') ?></span>
      </button>
      <?php endif; ?>
    <?php endif; ?>
    <div class="dropdown">
      <button class="tb-icon-btn user-topbar-btn" type="button" data-bs-toggle="dropdown" aria-label="Menu do usuário">
        <span class="user-avatar small"><?= htmlspecialchars($currentUser->initials(), ENT_QUOTES, 'UTF-8') ?></span>
      </button>
      <div class="dropdown-menu dropdown-menu-end user-menu">
        <div class="px-3 py-2">
          <strong><?= htmlspecialchars($currentUser->name(), ENT_QUOTES, 'UTF-8') ?></strong>
          <div class="text-muted small"><?= htmlspecialchars($currentUser->profileName(), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="dropdown-divider"></div>
        <form method="post" action="actions/logout.php" class="px-2">
          <?= $csrf->field() ?>
          <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right"></i> Sair</button>
        </form>
      </div>
    </div>
  </div>
</header>
