<?php
$adminTitle = 'Cupons';
$activeAdmin = 'cupons';
require_once __DIR__ . '/../includes/coupons.php';
$adminUser = require_admin();

$filters = [
    'search' => coupon_clean_text($_GET['search'] ?? '', 120),
    'status' => coupon_clean_text($_GET['status'] ?? '', 40),
    'tipo' => coupon_clean_text($_GET['tipo'] ?? '', 40),
    'canal' => coupon_clean_text($_GET['canal'] ?? '', 40),
];
$cupons = coupon_list($filters);
$stats = coupon_stats();
$successMessages = [
    'saved' => 'Cupom salvo no banco.',
    'status' => 'Status do cupom atualizado.',
];
$errorMessages = [
    'csrf' => 'Sessão expirada. Recarregue a página e tente novamente.',
    'invalid' => 'Cupom ou status inválido.',
    'status' => 'Não foi possível atualizar o status do cupom.',
];
$successKey = is_string($_GET['success'] ?? null) ? (string) $_GET['success'] : '';
$errorKey = is_string($_GET['error'] ?? null) ? (string) $_GET['error'] : '';

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Promoções</span>
    <h1>Cupons</h1>
    <p>Campanhas reais com tipos de desconto, validade, usos e ações seguras.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/cupom-form.php') ?>">Cadastrar cupom</a></div>
</section>

<?php if ($successKey !== '' && isset($successMessages[$successKey])): ?>
  <div class="admin-alert-card admin-alert-success" role="status"><strong>Sucesso</strong><?= e($successMessages[$successKey]) ?></div>
<?php endif; ?>
<?php if ($errorKey !== '' && isset($errorMessages[$errorKey])): ?>
  <div class="admin-alert-card admin-alert-danger" role="alert"><strong>Atenção</strong><?= e($errorMessages[$errorKey]) ?></div>
<?php endif; ?>

<form class="admin-command-bar" method="get" action="<?= site_url('admin/cupons.php') ?>">
  <label class="admin-field"><span>Buscar</span><input name="search" value="<?= e($filters['search']) ?>" placeholder="Código ou campanha"></label>
  <label class="admin-field"><span>Status</span><select name="status"><option value="">Todos</option><?php foreach (coupon_status_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <label class="admin-field"><span>Tipo</span><select name="tipo"><option value="">Todos</option><?php foreach (coupon_type_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['tipo'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <label class="admin-field"><span>Canal</span><select name="canal"><option value="">Todos</option><?php foreach (coupon_channel_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['canal'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <button class="btn btn-soft" type="submit">Filtrar</button>
  <a class="btn btn-outline" href="<?= site_url('admin/cupons.php') ?>">Limpar</a>
</form>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Ativos</span><strong><?= (int) $stats['ativos'] ?></strong><small><?= (int) $stats['total'] ?> campanha(s)</small></article>
  <article class="admin-kpi-card"><span>Usos</span><strong><?= (int) $stats['usos'] ?></strong><small>Registrados no banco</small></article>
  <article class="admin-kpi-card"><span>Desconto médio</span><strong><?= number_format((float) $stats['desconto_medio_percentual'], 0, ',', '.') ?>%</strong><small>Campanhas percentuais</small></article>
  <article class="admin-kpi-card"><span>Maior campanha</span><strong><?= e((string) $stats['maior_campanha']) ?></strong><small>Mais usada</small></article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Cupom</th><th>Campanha</th><th>Tipo</th><th>Valor</th><th>Validade</th><th>Status</th><th>Usos</th><th>Ações</th></tr></thead>
    <tbody>
      <?php if (empty($cupons)): ?>
        <tr><td colspan="8"><div class="admin-empty-row"><strong>Nenhum cupom encontrado</strong><span>Cadastre um cupom ou ajuste os filtros.</span></div></td></tr>
      <?php endif; ?>
      <?php foreach ($cupons as $cupom): ?>
        <tr>
          <td><strong><?= e((string) $cupom['codigo']) ?></strong><small><?= e(coupon_channel_options()[(string) $cupom['canal']] ?? (string) $cupom['canal']) ?></small></td>
          <td><?= e((string) $cupom['campanha']) ?></td>
          <td><?= e(coupon_type_options()[(string) $cupom['tipo_desconto']] ?? (string) $cupom['tipo_desconto']) ?></td>
          <td><?= e(coupon_value_label($cupom)) ?></td>
          <td><?= !empty($cupom['validade_em']) ? e(date('d/m/Y', strtotime((string) $cupom['validade_em']))) : 'Sem validade' ?></td>
          <td><span class="<?= e(coupon_badge_class((string) $cupom['status'])) ?>"><?= e(coupon_status_options()[(string) $cupom['status']] ?? (string) $cupom['status']) ?></span></td>
          <td><?= (int) $cupom['usos_realizados'] ?></td>
          <td>
            <div class="admin-table-actions">
              <a href="<?= site_url('admin/cupom-form.php?id=' . (int) $cupom['id']) ?>">Editar</a>
              <form method="post" action="<?= site_url('admin/actions/cupom-status.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $cupom['id'] ?>">
                <input type="hidden" name="status" value="<?= $cupom['status'] === 'ativo' ? 'pausado' : 'ativo' ?>">
                <button type="submit"><?= $cupom['status'] === 'ativo' ? 'Pausar' : 'Ativar' ?></button>
              </form>
              <form method="post" action="<?= site_url('admin/actions/cupom-status.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $cupom['id'] ?>">
                <input type="hidden" name="status" value="encerrado">
                <button type="submit">Encerrar</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
