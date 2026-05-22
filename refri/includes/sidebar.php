<?php
$activePage = $activePage ?? 'dashboard';
$menu = [
  ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'DB'],
  ['id' => 'clientes', 'label' => 'Clientes', 'href' => 'clientes.php', 'icon' => 'CL'],
  ['id' => 'ordens', 'label' => 'Ordens de Serviço', 'href' => 'ordens-servico.php', 'icon' => 'OS'],
  ['id' => 'orcamentos', 'label' => 'Orçamentos', 'href' => 'orcamentos.php', 'icon' => 'OR'],
  ['id' => 'pecas', 'label' => 'Peças', 'href' => 'pecas.php', 'icon' => 'PC'],
  ['id' => 'servicos', 'label' => 'Tipos de Serviço', 'href' => 'tipos-servico.php', 'icon' => 'SV'],
  ['id' => 'relatorios', 'label' => 'Relatórios', 'href' => 'relatorios.php', 'icon' => 'RP'],
  ['id' => 'notas', 'label' => 'Notas Fiscais', 'href' => 'notas-fiscais.php', 'icon' => 'NF'],
  ['id' => 'configuracoes', 'label' => 'Configurações', 'href' => 'configuracoes.php', 'icon' => 'CF'],
];
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <div class="brand-mark"><span>KY</span></div>
    <div>
      <strong>K.Yamaguchi</strong>
      <span>Service OS</span>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Menu principal">
    <?php foreach ($menu as $item): ?>
      <a class="nav-item <?= $activePage === $item['id'] ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
        <span class="nav-item__icon"><?= htmlspecialchars($item['icon']) ?></span>
        <span><?= htmlspecialchars($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__cta">
    <span class="sidebar__cta-tag">Atalho operacional</span>
    <strong>Nova Ordem de Serviço</strong>
    <p>Abra uma OS com cliente, equipamento, serviço e status técnico.</p>
    <a class="btn btn--primary btn--block" href="ordens-servico.php?action=new">+ Nova OS</a>
  </div>

  <div class="sidebar__user">
    <div class="avatar">KY</div>
    <div>
      <strong>Operador</strong>
      <span>Administrador</span>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
