<?php
$pageTitle = $pageTitle ?? 'K.Yamaguchi Service';
$activePage = $activePage ?? 'dashboard';
$pageCss = $pageCss ?? [];
$pageJs = $pageJs ?? [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F766E">
  <title><?= htmlspecialchars($pageTitle) ?> | K.Yamaguchi Service</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <?php foreach ($pageCss as $css): ?>
    <link rel="stylesheet" href="assets/css/<?= htmlspecialchars($css) ?>.css">
  <?php endforeach; ?>
</head>
<body data-page="<?= htmlspecialchars($activePage) ?>">
<div class="app-shell">
