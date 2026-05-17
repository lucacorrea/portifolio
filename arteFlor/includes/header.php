<?php
require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? SITE_NAME;
$activePage = $activePage ?? 'inicio';
$bodyClass = $bodyClass ?? '';
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
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/pages.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= site_url('index.php') ?>" aria-label="Arte&Flor - início">
      <span class="brand-mark" aria-hidden="true">A&F</span>
      <span class="brand-text">Arte<span>&</span>Flor</span>
    </a>

    <nav class="main-nav" id="mainNav" aria-label="Navegacao principal" data-main-nav>
      <a class="<?= $activePage === 'inicio' ? 'active' : '' ?>" href="<?= site_url('index.php') ?>">Início</a>
      <a class="<?= $activePage === 'catalogo' ? 'active' : '' ?>" href="<?= site_url('catalogo.php') ?>">Catálogo</a>
      <a class="<?= $activePage === 'blog' ? 'active' : '' ?>" href="<?= site_url('blog.php') ?>">Blog</a>
      <a class="<?= $activePage === 'cliente' ? 'active' : '' ?>" href="<?= site_url('cliente.php') ?>">Cliente</a>
      <a href="<?= site_url('admin/login.php') ?>">Admin</a>
    </nav>

    <div class="header-actions">
      <a class="cart-link" href="<?= site_url('carrinho.php') ?>" aria-label="Abrir carrinho">
        <span aria-hidden="true">Carrinho</span>
        <strong data-cart-count>0</strong>
      </a>
      <a class="btn btn-primary header-whatsapp" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, vim pelo site da Arte&Flor.') ?>">WhatsApp</a>
      <button class="menu-toggle" type="button" data-menu-toggle aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
<main>
