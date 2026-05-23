<?php
require_once __DIR__ . '/auth.php';
$adminUser = require_admin();
$adminTitle = $adminTitle ?? 'Painel';
$activeAdmin = $activeAdmin ?? '';
$pageScripts = $pageScripts ?? [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($adminTitle) ?> | <?= SITE_NAME ?></title>
  <meta name="description" content="<?= e(SITE_DESCRIPTION) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url() ?>assets/css/adminStyle.css">
</head>
<body class="admin-premium-body">
<div class="admin-shell">
  <?php require __DIR__ . '/admin-sidebar.php'; ?>
  <main class="admin-main">
