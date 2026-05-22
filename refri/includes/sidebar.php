<?php
$activePage = $activePage ?? 'dashboard';
$navGroups = [
  'Operação' => [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'DB'],
    ['id' => 'clientes', 'label' => 'Clientes', 'href' => 'clientes.php', 'icon' => 'CL'],
    ['id' => 'ordens', 'label' => 'Ordens de Serviço', 'href' => 'ordens-servico.php', 'icon' => 'OS'],
    ['id' => 'orcamentos', 'label' => 'Orçamentos', 'href' => 'orcamentos.php', 'icon' => 'OR'],
  ],
  'Gestão' => [
    ['id' => 'pecas', 'label' => 'Peças e Estoque', 'href' => 'pecas.php', 'icon' => 'PC'],
    ['id' => 'servicos', 'label' => 'Tipos de Serviço', 'href' => 'tipos-servico.php', 'icon' => 'SV'],
    ['id' => 'relatorios', 'label' => 'Relatórios', 'href' => 'relatorios.php', 'icon' => 'RP'],
    ['id' => 'notas', 'label' => 'Notas Fiscais', 'href' => 'notas-fiscais.php', 'icon' => 'NF'],
    ['id' => 'configuracoes', 'label' => 'Configurações', 'href' => 'configuracoes.php', 'icon' => 'CF'],
  ],
];
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <div class="brand-mark">KY</div>
    <div>
      <strong class="brand-title">K.Yamaguchi</strong>
      <span class="brand-subtitle">Service OS</span>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Menu principal">
    <?php foreach ($navGroups as $group => $items): ?>
      <div class="nav-group"><?= htmlspecialchars($group) ?></div>
      <?php foreach ($items as $item): ?>
        <a class="nav-item <?= $activePage === $item['id'] ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
          <span class="nav-item__icon"><?= htmlspecialchars($item['icon']) ?></span>
          <span><?= htmlspecialchars($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__quick">
    <span>Atalho operacional</span>
    <strong>Nova Ordem de Serviço</strong>
    <p>Abra uma OS com cliente, equipamento, serviço e status técnico.</p>
    <a href="ordens-servico.php?action=new" class="btn btn--primary btn--full">+ Nova OS</a>
  </div>

  <div class="sidebar__user">
    <div class="avatar">OP</div>
    <div>
      <strong class="user-title">Operador</strong>
      <span class="user-role">Administrador técnico</span>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
