<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$topbarSearchPlaceholder = $topbarSearchPlaceholder ?? 'Buscar OS, cliente, orçamento ou peça...';
?>
<header class="topbar">
  <div class="topbar__left">
    <button class="menu-btn" id="menuToggle" type="button" aria-label="Abrir menu">
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
    </button>
    <form class="topbar__search" role="search" onsubmit="return false;">
      <span class="search-icon">⌕</span>
      <input id="globalSearch" type="search" placeholder="<?= htmlspecialchars($topbarSearchPlaceholder) ?>" autocomplete="off">
    </form>
  </div>

  <div class="topbar__right">
    <div class="breadcrumb">K.Yamaguchi / <strong><?= htmlspecialchars($pageTitle) ?></strong></div>
    <div class="user-profile" id="profileBtn" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
      <div class="avatar">KY</div>
      <span class="user-name">Admin</span>
    </div>
    <div id="profileDropdown" class="dropdown hidden">
      <a href="configuracoes.php">Configurações</a>
      <a href="#">Sair</a>
    </div>
  </div>
</header>
