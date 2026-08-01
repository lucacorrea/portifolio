<?php declare(strict_types=1); require_once __DIR__ . '/admin-guard.php'; require_once __DIR__ . '/ui.php'; ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= admin_h($pageTitle ?? 'Administração') ?> · Flux Empresas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= asset_url('assets/css/dashboard.css') ?>" rel="stylesheet"><link href="<?= asset_url('assets/css/admin-platform.css') ?>" rel="stylesheet">
  <?php if (!empty($pageChart)): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script><?php endif; ?>
</head>
<body class="admin-platform"><div class="admin-layout"><?php require __DIR__ . '/menu.php'; ?><main class="admin-main"><?php require __DIR__ . '/topbar.php'; ?>
<?php foreach ($session->consumeFlashMessages() as $flash): ?><div class="alert alert-<?= admin_h($flash['type']) ?> admin-flash" role="<?= $flash['type'] === 'danger' ? 'alert' : 'status' ?>"><?= admin_h($flash['message']) ?></div><?php endforeach; ?>
<section class="admin-content"><?php ($adminContent)(); ?></section></main></div>
<dialog class="row-actions-dialog" id="row-actions-dialog" aria-labelledby="row-actions-dialog-title" aria-describedby="row-actions-dialog-description"><div class="row-actions-dialog-content"><div class="row-actions-dialog-header"><div><span class="row-actions-dialog-eyebrow">Ações do registro</span><h2 id="row-actions-dialog-title">Escolha uma ação</h2></div><button class="row-actions-dialog-close" type="button" data-row-actions-close aria-label="Fechar ações"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><p class="row-actions-dialog-description" id="row-actions-dialog-description">Selecione o que deseja fazer com este registro.</p><div class="row-actions-menu-host" data-row-actions-menu-host></div></div></dialog>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script><script src="<?= asset_url('assets/js/fluxempresa-app.js') ?>" defer></script><script src="<?= asset_url('assets/js/admin-platform.js') ?>" defer></script>
<?php if (!empty($pageScriptData)): ?><script>window.adminPlatformData=<?= json_encode($pageScriptData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script><?php endif; ?>
</body></html>
