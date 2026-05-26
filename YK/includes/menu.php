<?php
$activePage = $activePage ?? 'dashboard';
$navGroups = [
  'Principal' => [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'href' => 'dashboard.php', 'badge' => 'sb-total'],
    ['key' => 'ordens', 'label' => 'Ordens de Serviço', 'icon' => 'bi-card-list', 'href' => 'ordens-servico.php'],
    ['key' => 'clientes', 'label' => 'Clientes', 'icon' => 'bi-people', 'href' => 'clientes.php'],
    ['key' => 'tecnicos', 'label' => 'Técnicos', 'icon' => 'bi-person-badge', 'href' => 'tecnicos.php'],
  ],
  'Operacional' => [
    ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'bi-calendar3', 'href' => 'agenda.php'],
    ['key' => 'pecas', 'label' => 'Peças / Estoque', 'icon' => 'bi-box-seam', 'href' => 'pecas.php'],
    ['key' => 'servicos', 'label' => 'Serviços', 'icon' => 'bi-tools', 'href' => 'servicos.php'],
    ['key' => 'orcamentos', 'label' => 'Orçamentos', 'icon' => 'bi-file-earmark-text', 'href' => 'orcamentos.php'],
    ['key' => 'faturamento', 'label' => 'Notas / Faturamento', 'icon' => 'bi-receipt', 'href' => 'faturamento.php'],
  ],
  'Gestão' => [
    ['key' => 'relatorios', 'label' => 'Relatórios', 'icon' => 'bi-bar-chart-line', 'href' => 'relatorios.php'],
    ['key' => 'configuracoes', 'label' => 'Configurações', 'icon' => 'bi-gear', 'href' => 'configuracoes.php'],
  ],
];
?>

<aside class="os-sidebar">
  <a class="sidebar-brand" href="dashboard.php">
    <div class="brand-icon"><i class="bi bi-tools"></i></div>
    <div>
      <div class="brand-name">OSmais</div>
      <div class="brand-tag">Sistema de OS</div>
    </div>
  </a>

  <?php foreach ($navGroups as $section => $items): ?>
    <?php if ($section === 'Gestão'): ?><div class="sidebar-divider"></div><?php endif; ?>
    <div class="sidebar-section<?= $section !== 'Principal' ? ' sidebar-section-spaced' : '' ?>"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="sidebar-nav">
      <?php foreach ($items as $item): ?>
        <?php $isActive = $activePage === $item['key']; ?>
        <a class="nav-link-os<?= $isActive ? ' active' : '' ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
          <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
          <?php if (!empty($item['badge'])): ?><span class="badge-count" id="<?= htmlspecialchars($item['badge'], ENT_QUOTES, 'UTF-8') ?>">—</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endforeach; ?>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">AD</div>
      <div class="user-info">
        <div class="user-name">Admin</div>
        <div class="user-role">Administrador</div>
      </div>
      <i class="bi bi-chevron-expand"></i>
    </div>
  </div>
</aside>
