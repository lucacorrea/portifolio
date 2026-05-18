<?php
require_once __DIR__ . '/helpers.php';
$pageTitle = $pageTitle ?? SITE_NAME;
$activePage = $activePage ?? 'inicio';
$pageScripts = $pageScripts ?? [];
$base = base_url();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= SITE_NAME ?></title>
  <meta name="description" content="<?= e(SITE_DESCRIPTION) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800;900&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= $base ?>index.php" aria-label="Arte&Flor - Início">
      <span class="brand-icon" aria-hidden="true">A&F</span>
      <span>Arte<span>&</span>Flor</span>
    </a>
    <nav class="main-nav" id="main-nav" aria-label="Navegação principal">
      <a class="<?= $activePage === 'inicio' ? 'active' : '' ?>" href="<?= $base ?>index.php">Início</a>
      <a class="<?= $activePage === 'catalogo' ? 'active' : '' ?>" href="<?= $base ?>catalogo.php">Catálogo</a>
      <a class="<?= $activePage === 'blog' ? 'active' : '' ?>" href="<?= $base ?>blog.php">Blog</a>
      <a class="<?= $activePage === 'cliente' ? 'active' : '' ?>" href="<?= $base ?>cliente.php">Área do cliente</a>
      <a href="<?= $base ?>carrinho.php">Carrinho <span class="cart-count" data-cart-count>0</span></a>
      <a href="<?= $base ?>admin/login.php">Admin</a>
    </nav>
    <a class="btn btn-outline header-support" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, preciso de atendimento da Arte&Flor.') ?>">Atendimento</a>
    <button class="menu-toggle" type="button" data-menu-toggle aria-controls="main-nav" aria-expanded="false" aria-label="Abrir menu">
      <span class="menu-toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>
      <span class="menu-toggle-text" data-menu-toggle-text>Menu</span>
    </button>
  </div>
</header>
<main>
