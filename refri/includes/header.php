<?php
$pageTitle = $pageTitle ?? 'K.Yamaguchi Service';
$activePage = $activePage ?? 'dashboard';
$pageCss = $pageCss ?? [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> | K.Yamaguchi Service</title>
  <meta name="description" content="Layout premium para sistema de gestão de refrigeração K.Yamaguchi Service.">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="assets/css/base.css">
  <?php foreach ($pageCss as $css): ?>
    <link rel="stylesheet" href="assets/css/<?= htmlspecialchars($css) ?>.css">
  <?php endforeach; ?>
</head>
<body data-page="<?= htmlspecialchars($activePage) ?>">
  <div class="app-shell">
