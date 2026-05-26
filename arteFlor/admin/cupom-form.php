<?php
$adminTitle = 'Cadastro de cupom';
$activeAdmin = 'cupom-form';
require_once __DIR__ . '/../includes/coupons.php';
$adminUser = require_admin();

$couponId = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$coupon = $couponId > 0 ? coupon_find($couponId) : null;
$error = '';

if ($couponId > 0 && !$coupon && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(404);
    $error = 'Cupom não encontrado.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            coupon_save_from_request($_POST);
            header('Location: ' . site_url('admin/cupons.php?success=saved'));
            exit;
        } catch (Throwable $exception) {
            error_log('[ArteFlor][coupon-save] ' . $exception->getMessage());
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Não foi possível salvar o cupom. Verifique os dados e tente novamente.';
            $coupon = array_merge($coupon ?? [], $_POST);
        }
    }
}

$isEditing = !empty($coupon['id']);
$field = fn(string $key, mixed $default = ''): mixed => $_POST[$key] ?? $coupon[$key] ?? $default;
$checked = function (string $key, bool $default = false) use ($coupon): string {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        return isset($_POST[$key]) ? 'checked' : '';
    }
    if ($coupon && array_key_exists($key, $coupon)) {
        return !empty($coupon[$key]) ? 'checked' : '';
    }

    return $default ? 'checked' : '';
};
$codePreview = coupon_normalize_code((string) $field('codigo', 'FLOR15')) ?: 'FLOR15';
$typePreview = (string) $field('tipo_desconto', 'percentual');
$valuePreview = coupon_normalize_decimal($field('valor_desconto', 15));

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Promoções</span>
    <h1><?= $isEditing ? 'Editar cupom' : 'Cadastrar cupom' ?></h1>
    <p>Crie campanhas reais com tipo de desconto, validade, regras de uso e aplicação por canal.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/cupons.php') ?>">Voltar para cupons</a>
    <button class="btn btn-primary" type="submit" form="couponForm">Salvar cupom</button>
  </div>
</section>

<?php if ($error !== ''): ?>
  <div class="admin-alert-card admin-alert-danger" role="alert">
    <strong>Erro ao salvar</strong>
    <?= e($error) ?>
  </div>
<?php endif; ?>

<form id="couponForm" class="admin-form-shell" method="post" action="<?= site_url('admin/cupom-form.php' . ($isEditing ? '?id=' . (int) $coupon['id'] : '')) ?>">
  <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int) ($coupon['id'] ?? 0) ?>">

  <section class="admin-form-card">
    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Campanha</strong><p>Dados principais do cupom.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Código</span><input name="codigo" value="<?= e((string) $field('codigo')) ?>" placeholder="FLOR15" required></label>
        <label class="admin-field"><span>Nome da campanha</span><input name="campanha" value="<?= e((string) $field('campanha')) ?>" placeholder="Primeira compra" required></label>
        <label class="admin-field"><span>Tipo de desconto</span><select name="tipo_desconto"><?php foreach (coupon_type_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) $field('tipo_desconto', 'percentual') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field"><span>Valor</span><input name="valor_desconto" type="number" min="0" step="0.01" value="<?= e((string) $field('valor_desconto', '')) ?>" placeholder="15"></label>
        <label class="admin-field"><span>Início</span><input name="inicio_em" type="date" value="<?= !empty($field('inicio_em')) ? e(substr((string) $field('inicio_em'), 0, 10)) : '' ?>"></label>
        <label class="admin-field"><span>Validade</span><input name="validade_em" type="date" value="<?= !empty($field('validade_em')) ? e(substr((string) $field('validade_em'), 0, 10)) : '' ?>"></label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Regras de uso</strong><p>Limites e aplicação comercial.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field"><span>Uso máximo</span><input name="uso_maximo" type="number" min="0" value="<?= e((string) $field('uso_maximo', '')) ?>" placeholder="100"></label>
        <label class="admin-field"><span>Valor mínimo</span><input name="valor_minimo_pedido" type="number" min="0" step="0.01" value="<?= e((string) $field('valor_minimo_pedido', '0.00')) ?>" placeholder="120.00"></label>
        <label class="admin-field"><span>Status</span><select name="status"><?php foreach (coupon_status_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) $field('status', 'ativo') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field"><span>Canal</span><select name="canal"><?php foreach (coupon_channel_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) $field('canal', 'todos') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      </div>
    </div>
  </section>

  <aside class="admin-form-card admin-side-card">
    <div class="coupon-preview">
      <span><?= e($codePreview) ?></span>
      <strong><?= $typePreview === 'percentual' ? e(number_format($valuePreview, 0, ',', '.') . '% OFF') : e(coupon_value_label(['tipo_desconto' => $typePreview, 'valor_desconto' => $valuePreview])) ?></strong>
      <p><?= e(coupon_channel_options()[(string) $field('canal', 'todos')] ?? 'Todos') ?></p>
    </div>
    <div class="admin-check-list">
      <label><input name="exibir_checkout" type="checkbox" value="1" <?= $checked('exibir_checkout', true) ?>> Exibir no checkout</label>
      <label><input name="aplicar_catalogo" type="checkbox" value="1" <?= $checked('aplicar_catalogo', true) ?>> Aplicar no catálogo</label>
      <label><input name="limitar_por_categoria" type="checkbox" value="1" <?= $checked('limitar_por_categoria') ?>> Limitar por categoria</label>
    </div>
    <?php if ($isEditing): ?>
      <div class="admin-metric-list">
        <div class="admin-metric-row"><span>Usos</span><strong><?= (int) ($coupon['usos_realizados'] ?? 0) ?></strong></div>
        <div class="admin-metric-row"><span>Atualizado</span><strong><?= !empty($coupon['atualizado_em']) ? e(date('d/m', strtotime((string) $coupon['atualizado_em']))) : '-' ?></strong></div>
      </div>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit">Salvar cupom</button>
  </aside>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
