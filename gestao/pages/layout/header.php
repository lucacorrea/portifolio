<?php

$prefix = $prefix ?? '../';
$pageId = $pageId ?? '';
$pageTitle = $pageTitle ?? 'Sistema';

$layoutUser = \App\Security\Auth::user();
$layoutEmpresaId = (int)(
  $layoutUser['empresa_id']
  ?? 0
);

$companyBrand = [
  'company_id' => $layoutEmpresaId,
  'name' => '',
  'legal_name' => '',
  'logo_path' => '',
  'logo_url' => '',
  'logo_mime' => '',
  'initials' => '',
  'has_logo' => false,
];
$companyDisplayName = '';
$companyLogoUrl = '';
$companyLogoMime = '';
$companyInitials = '';
$companyManifestVersion = '';
$companyAppleTouchIconUrl = '';

if ($layoutEmpresaId > 0) {
  try {
    $brandService = new \App\Services\CompanyBrandService();
    $companyBrand = $brandService->getForCompany($layoutEmpresaId, $prefix);
    $companyDisplayName = (string)$companyBrand['name'];
    $companyLogoUrl = (string)$companyBrand['logo_url'];
    $companyLogoMime = (string)$companyBrand['logo_mime'];
    $companyInitials = (string)$companyBrand['initials'];

    $pwaManifestService = new \App\Services\PwaManifestService($brandService);
    $pwaSettings = $pwaManifestService->appSettingsForCompany($layoutEmpresaId);
    $companyManifestVersion = (string)$pwaSettings['version'];

    $pwaIconService = new \App\Services\PwaIconService();
    if (!empty($companyBrand['has_logo']) && extension_loaded('gd')) {
      $pwaIconService->iconsForBrand($companyBrand);
      $appleTouchIconPath = $pwaIconService->appleTouchIconPath($companyBrand);
      $companyAppleTouchIconUrl = $appleTouchIconPath !== ''
        ? (rtrim($prefix, '/') === '' ? $appleTouchIconPath : rtrim($prefix, '/') . '/' . $appleTouchIconPath)
        : '';
    }
  } catch (\Throwable $e) {
    log_app_exception($e);
  }
}

/*
|--------------------------------------------------------------------------
| Título da página
|--------------------------------------------------------------------------
|
| Quando a empresa estiver carregada:
|
| Dashboard | Nome da empresa
|
| Quando não estiver:
|
| Dashboard
|
*/

$documentTitle = $pageTitle;

if ($companyDisplayName !== '') {
  $documentTitle .=
    ' | '
    . $companyDisplayName;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">

  <meta
    name="theme-color"
    content="#1657A7">

  <meta
    name="mobile-web-app-capable"
    content="yes">

  <meta
    name="apple-mobile-web-app-capable"
    content="yes">

  <?php if ($companyDisplayName !== ''): ?>
    <meta
      name="apple-mobile-web-app-title"
      content="<?= e($companyDisplayName) ?>">
  <?php endif; ?>

  <meta
    name="apple-mobile-web-app-status-bar-style"
    content="black-translucent">

  <meta
    name="csrf-token"
    content="<?= e(\App\Security\Csrf::token()) ?>">

  <title><?= e($documentTitle) ?></title>

  <?php if ($companyLogoUrl !== ''): ?>
    <link
      rel="icon"
      href="<?= e($companyLogoUrl) ?>"
      type="<?= e($companyLogoMime) ?>">
  <?php endif; ?>

  <?php if ($companyAppleTouchIconUrl !== ''): ?>
    <link
      rel="apple-touch-icon"
      href="<?= e($companyAppleTouchIconUrl) ?>">
  <?php endif; ?>

  <?php if ($layoutEmpresaId > 0): ?>
    <link
      rel="manifest"
      href="<?= e($prefix) ?>manifest.php?v=<?= e($companyManifestVersion) ?>"
      crossorigin="use-credentials">
  <?php endif; ?>

  <link
    rel="stylesheet"
    href="<?= e($prefix) ?>assets/css/main.css">
</head>

<body
  data-page="<?= e($pageId) ?>"
  data-prefix="<?= e($prefix) ?>"
  data-company-id="<?= $layoutEmpresaId ?>"
  data-company-name="<?= e($companyDisplayName) ?>"
  data-company-logo="<?= e($companyLogoUrl) ?>"
  data-company-initials="<?= e($companyInitials) ?>">
  <main class="phone-app">
    <section class="screen">
