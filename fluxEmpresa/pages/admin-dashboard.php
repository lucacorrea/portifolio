<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$cards = [
    ['Usuários', 'Gerencie acessos e contas internas.', 'usuarios.php', 'bi-person-gear'],
    ['Perfis de acesso', 'Defina permissões e responsabilidades.', 'perfis-acesso.php', 'bi-shield-lock'],
    ['Configurações', 'Acesse os parâmetros gerais do Flux.', 'configuracoes.php', 'bi-sliders'],
    ['Configuração fiscal', 'Gerencie a área fiscal protegida.', 'configuracoes-fiscais.php', 'bi-receipt-cutoff'],
    ['Relatórios', 'Consulte resultados operacionais e financeiros.', 'relatorios.php', 'bi-bar-chart-line'],
    ['Painel operacional', 'Acesse o painel principal do sistema.', 'dashboard.php', 'bi-grid-1x2'],
];
?>

<div class="page-body dashboard-page">
  <?php metric_grid([
      ['Acesso', 'Total', 'bi-shield-check', '#2563EB', 'perfil de suporte'],
      ['Área', 'Administrativa', 'bi-buildings', '#0EA5E9', 'plataforma Flux Empresas'],
      ['Sessão', 'Ativa', 'bi-person-check', '#16A34A', $currentUser->name()],
  ]); ?>

  <section class="quick-actions panel">
    <div class="panel-header">
      <div class="panel-title"><i class="bi bi-speedometer2"></i> Acessos administrativos</div>
    </div>
    <div class="quick-grid">
      <?php foreach ($cards as [$label, $description, $href, $icon]): ?>
      <a class="quick-action text-decoration-none" href="<?= h($href) ?>">
        <i class="bi <?= h($icon) ?>"></i>
        <span><?= h($label) ?></span>
        <small><?= h($description) ?></small>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-header">
      <div class="panel-title"><i class="bi bi-info-circle"></i> Área exclusiva do suporte</div>
    </div>
    <p class="mb-0 text-muted">Use esta área para administrar a plataforma. Os módulos operacionais continuam disponíveis pelo menu lateral quando necessários.</p>
  </section>
</div>
