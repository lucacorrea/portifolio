<?php
$navItems = [
  ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'DB'],
  ['id' => 'clientes', 'label' => 'Clientes', 'href' => 'tabelas.php?tipo=clientes', 'icon' => 'CL'],
  ['id' => 'os', 'label' => 'Ordens de Serviço', 'href' => 'tabelas.php?tipo=os', 'icon' => 'OS'],
  ['id' => 'orcamentos', 'label' => 'Orçamentos', 'href' => 'tabelas.php?tipo=orcamentos', 'icon' => 'OR'],
  ['id' => 'pecas', 'label' => 'Peças', 'href' => 'tabelas.php?tipo=pecas', 'icon' => 'PC'],
  ['id' => 'servicos', 'label' => 'Tipos de Serviço', 'href' => 'tabelas.php?tipo=servicos', 'icon' => 'SV'],
  ['id' => 'relatorios', 'label' => 'Relatórios', 'href' => 'relatorios.php', 'icon' => 'RP'],
  ['id' => 'notas', 'label' => 'Notas Fiscais', 'href' => 'tabelas.php?tipo=notas', 'icon' => 'NF'],
  ['id' => 'config', 'label' => 'Configurações', 'href' => '#', 'icon' => 'CF'],
];
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <div class="brand-mark">
      <span class="brand-mark__snow">KY</span>
    </div>
    <div>
      <strong>K.Yamaguchi</strong>
      <span>Service OS</span>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Menu principal">
    <?php foreach ($navItems as $item): ?>
      <a class="nav-item <?= $activePage === $item['id'] ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
        <span class="nav-item__icon"><?= $item['icon'] ?></span>
        <span><?= htmlspecialchars($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__cta">
    <span class="eyebrow">Atalho rápido</span>
    <strong>Nova Ordem de Serviço</strong>
    <p>Abra uma OS em menos de 1 minuto.</p>
    <a href="tabelas.php?tipo=os&acao=nova" class="btn btn--primary btn--full">+ Nova OS</a>
  </div>

  <div class="sidebar__user">
    <div class="avatar">KY</div>
    <div>
      <strong>Operador</strong>
      <span>Administrador</span>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
