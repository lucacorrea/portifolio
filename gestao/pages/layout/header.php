<?php

$prefix = $prefix ?? '../';
$pageId = $pageId ?? '';
$pageTitle = $pageTitle ?? 'Sistema';

/*
|--------------------------------------------------------------------------
| Identidade visual da empresa
|--------------------------------------------------------------------------
|
| A fonte dos dados é exclusivamente a tabela empresas:
|
| - empresas.nome
| - empresas.nome_fantasia
| - empresas.logo
|
| Não existe nome ou imagem fixa como fallback.
|
*/

$layoutCompany = [];

$companyDisplayName = '';
$companyLogoPath = '';
$companyLogoUrl = '';
$companyLogoMime = '';

$layoutUser = \App\Security\Auth::user();

$layoutEmpresaId = (int)(
  $layoutUser['empresa_id']
  ?? 0
);

if ($layoutEmpresaId > 0) {
  try {
    $companyRepository =
      new \App\Repositories\CompanyRepository();

    $layoutCompany =
      $companyRepository->findById(
        $layoutEmpresaId
      ) ?? [];

    /*
        |--------------------------------------------------------------------------
        | Nome exibido
        |--------------------------------------------------------------------------
        |
        | Prioridade:
        |
        | 1. nome_fantasia
        | 2. nome
        |
        */

    $companyFantasyName = trim(
      (string)(
        $layoutCompany['nome_fantasia']
        ?? ''
      )
    );

    $companyLegalName = trim(
      (string)(
        $layoutCompany['nome']
        ?? ''
      )
    );

    $companyDisplayName =
      $companyFantasyName !== ''
      ? $companyFantasyName
      : $companyLegalName;

    /*
        |--------------------------------------------------------------------------
        | Logo enviada por upload
        |--------------------------------------------------------------------------
        |
        | Exemplo salvo no banco:
        |
        | uploads/empresas/1/logo-abc123.webp
        |
        */

    $storedLogo = trim(
      (string)(
        $layoutCompany['logo']
        ?? ''
      )
    );

    if ($storedLogo !== '') {
      $normalizedLogo = ltrim(
        str_replace(
          '\\',
          '/',
          $storedLogo
        ),
        '/'
      );

      /*
             * Cada empresa só pode carregar arquivos
             * existentes dentro da própria pasta.
             */
      $allowedCompanyDirectory =
        'uploads/empresas/'
        . $layoutEmpresaId
        . '/';

      $isSafeLogoPath =
        str_starts_with(
          $normalizedLogo,
          $allowedCompanyDirectory
        )
        && !str_contains(
          $normalizedLogo,
          '../'
        )
        && !str_contains(
          $normalizedLogo,
          '..\\'
        );

      if ($isSafeLogoPath) {
        $absoluteLogoPath =
          BASE_PATH
          . DIRECTORY_SEPARATOR
          . str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $normalizedLogo
          );

        if (is_file($absoluteLogoPath)) {
          $extension = strtolower(
            pathinfo(
              $normalizedLogo,
              PATHINFO_EXTENSION
            )
          );

          $detectedMime = match ($extension) {
            'jpg',
            'jpeg' => 'image/jpeg',

            'png' => 'image/png',

            'webp' => 'image/webp',

            default => '',
          };

          if ($detectedMime !== '') {
            $companyLogoPath =
              $normalizedLogo;

            $companyLogoUrl =
              $prefix
              . $normalizedLogo;

            $companyLogoMime =
              $detectedMime;
          }
        }
      }
    }
  } catch (\Throwable $e) {
    /*
         * O erro é registrado, mas o layout
         * continua carregando sem identidade visual.
         */
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

    <link
      rel="apple-touch-icon"
      href="<?= e($companyLogoUrl) ?>">
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
  data-company-logo="<?= e($companyLogoUrl) ?>">
  <main class="phone-app">
    <section class="screen">