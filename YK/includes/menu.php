<?php
$activePage = $activePage ?? 'dashboard';
$navGroups = [
  'Principal' => [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'href' => 'dashboard.php'],
    ['key' => 'ordens', 'label' => 'Ordens de Serviço', 'icon' => 'bi-clipboard2-check', 'href' => 'ordens-servico.php'],
    ['key' => 'orcamentos', 'label' => 'Orçamentos', 'icon' => 'bi-file-earmark-text', 'href' => 'orcamentos.php'],
    ['key' => 'clientes', 'label' => 'Clientes', 'icon' => 'bi-people', 'href' => 'clientes.php'],
    ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'bi-calendar3', 'href' => 'agenda.php'],
    ['key' => 'painel-semanal', 'label' => 'Serviços da Semana', 'icon' => 'bi-calendar-week', 'href' => 'painel-semanal.php'],
  ],
  'Operacional' => [
    ['key' => 'pecas', 'label' => 'Produtos / Peças', 'icon' => 'bi-box-seam', 'href' => 'pecas.php'],
    ['key' => 'servicos', 'label' => 'Serviços', 'icon' => 'bi-tools', 'href' => 'servicos.php'],
    ['key' => 'funcionarios', 'label' => 'Funcionários', 'icon' => 'bi-person-badge', 'href' => 'tecnicos.php'],
    ['key' => 'fornecedores', 'label' => 'Fornecedores', 'icon' => 'bi-building-check', 'href' => 'fornecedores.php'],
    ['key' => 'transportadoras', 'label' => 'Transportadoras', 'icon' => 'bi-truck', 'href' => 'transportadoras.php'],
  ],
  'Financeiro e Fiscal' => [
    ['key' => 'caixa', 'label' => 'Caixa', 'icon' => 'bi-cash-coin', 'href' => 'caixa.php'],
    ['key' => 'faturamento', 'label' => 'Notas e Faturamento', 'icon' => 'bi-receipt-cutoff', 'href' => 'faturamento.php'],
    ['key' => 'recibos', 'label' => 'Recibos e Boletos', 'icon' => 'bi-journal-check', 'href' => 'faturamento.php#recibos'],
  ],
  'Gestão' => [
    ['key' => 'relatorios', 'label' => 'Relatórios', 'icon' => 'bi-bar-chart-line', 'href' => 'relatorios.php'],
    ['key' => 'configuracoes', 'label' => 'Configurações', 'icon' => 'bi-sliders', 'href' => 'configuracoes.php'],
  ],
];
?>

<aside class="os-sidebar" id="app-sidebar">
  <a class="sidebar-brand" href="dashboard.php" aria-label="K. Yamaguchi Refrigeração">
    <div class="brand-icon"><i class="bi bi-snow2"></i></div>
    <div>
      <div class="brand-name">K. Yamaguchi</div>
      <div class="brand-tag">Gestão de Serviços</div>
    </div>
  </a>

  <?php foreach ($navGroups as $section => $items): ?>
    <div class="sidebar-section"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="sidebar-nav" aria-label="<?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?>">
      <?php foreach ($items as $item): ?>
        <?php $isActive = $activePage === $item['key'] || ($activePage === 'faturamento' && $item['key'] === 'recibos'); ?>
        <a class="nav-link-os<?= $isActive ? ' active' : '' ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
          <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
          <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endforeach; ?>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">KY</div>
      <div class="user-info">
        <div class="user-name">Atendimento</div>
        <div class="user-role">Painel visual</div>
      </div>
    </div>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
