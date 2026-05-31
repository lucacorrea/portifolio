<?php
$prefix = $prefix ?? '../';
$pageId = $pageId ?? '';
$pageTitle = $pageTitle ?? 'Sistema';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1" />
  <meta name="theme-color" content="#1657A7" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-title" content="L&J Caixa" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <meta name="csrf-token" content="<?= e(\App\Security\Csrf::token()) ?>" />
  <title><?= e($pageTitle) ?> | L&J Caixa</title>
  <link rel="manifest" href="<?= $prefix ?>manifest.json" />
  <link rel="icon" href="<?= $prefix ?>assets/icons/icon.svg" type="image/svg+xml" />
  <link rel="stylesheet" href="<?= $prefix ?>assets/css/main.css" />
</head>
<body data-page="<?= e($pageId) ?>" data-prefix="<?= $prefix ?>">
  <main class="phone-app">
    <section class="screen">
