<?php
$role = strtolower(trim((string)($_SESSION['user_role'] ?? '')));
$perfilRestrito = in_array($role, ['estagiario', 'comum'], true);
$isEstagiario = $role === 'estagiario';
$homeHref = $isEstagiario ? 'pessoasCadastradas.php' : 'dashboard.php';
?>
<div id="sidebar" class="active">
  <div class="sidebar-wrapper active">
    <div class="sidebar-header"><div class="d-flex justify-content-between align-items-center"><div class="logo"><a href="<?= $homeHref ?>"><img src="assets/images/logo/logo_pmc_2025.jpg" alt="Logo" style="height:48px"></a></div><div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div></div></div>
    <div class="sidebar-menu"><ul class="menu">
      <?php if (!$isEstagiario): ?><li class="sidebar-item"><a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a></li><?php endif; ?>
      <li class="sidebar-item has-sub active"><a href="#" class="sidebar-link"><i class="bi bi-person-lines-fill"></i><span>Solicitantes</span></a><ul class="submenu active"><li class="submenu-item active"><a href="pessoasCadastradas.php">Cadastrados</a></li><li class="submenu-item"><a href="cadastrarSolicitante.php">Novo Cadastro</a></li></ul></li>
      <?php if (!$perfilRestrito && ($role === 'prefeito' || $role === 'secretario')): ?>
      <li class="sidebar-item has-sub"><a href="#" class="sidebar-link"><i class="bi bi-person-fill"></i><span>Usuários</span></a><ul class="submenu"><li class="submenu-item"><a href="usuariosPermitidos.php">Permitidos</a></li><li class="submenu-item"><a href="usuariosNaoPermitidos.php">Não Permitidos</a></li></ul></li>
      <?php endif; ?>
      <?php if (!$perfilRestrito): ?>
      <li class="sidebar-item"><a href="../../gpsemas/index.php" class="sidebar-link"><i class="bi bi-map-fill"></i><span>Rastreamento</span></a></li>
      <?php endif; ?>
      <?php if (!$perfilRestrito && $role === 'secretario'): ?><li class="sidebar-item"><a href="../admin/index.php" class="sidebar-link" target="_blank" rel="noopener"><i class="bi bi-shield-lock-fill"></i><span>Administrador</span></a></li><?php endif; ?>
      <?php if (function_exists('auth_is_wifi_config_owner') && auth_is_wifi_config_owner()): ?><li class="sidebar-item"><a href="configRedeEstagiario.php" class="sidebar-link"><i class="bi bi-wifi"></i><span>Config. Rede</span></a></li><?php endif; ?>
      <li class="sidebar-item"><a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
    </ul></div>
  </div>
</div>
