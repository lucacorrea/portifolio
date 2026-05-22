<header class="topbar">
  <div class="topbar-left">
    <span class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></span>
    <span class="topbar-sep">/</span>
    <span class="topbar-subtitle"><?= htmlspecialchars($pageSubtitle ?? 'Visão geral do sistema', ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <div class="topbar-right">
    <div class="tb-icon-btn" title="Atualizar" onclick="window.handleTopbarRefresh ? handleTopbarRefresh() : (window.loadAll ? loadAll() : location.reload())">
      <i class="bi bi-arrow-clockwise"></i>
    </div>
    <div class="tb-icon-btn" title="Notificações">
      <i class="bi bi-bell"></i>
      <span class="notif-dot"></span>
    </div>
    <div class="tb-icon-btn" title="Tela cheia" onclick="toggleFullscreen()">
      <i class="bi bi-fullscreen"></i>
    </div>
    <button class="btn-new-os" type="button" title="<?= htmlspecialchars($primaryActionLabel ?? 'Nova OS', ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($primaryActionLabel ?? 'Nova OS', ENT_QUOTES, 'UTF-8') ?>" onclick="<?= htmlspecialchars($primaryActionHandler ?? 'openModal()', ENT_QUOTES, 'UTF-8') ?>">
      <i class="bi <?= htmlspecialchars($primaryActionIcon ?? 'bi-plus-lg', ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars($primaryActionLabel ?? 'Nova OS', ENT_QUOTES, 'UTF-8') ?>
    </button>
  </div>
</header>
