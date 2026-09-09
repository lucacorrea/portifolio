<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/demo.php';
$pageTitle = $pageTitle ?? 'L&J Comandas';
$currentPage = $currentPage ?? '';
$hideChrome = $hideChrome ?? false;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d28d9">
    <title><?= e($pageTitle) ?> · L&J Comandas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<body class="<?= $hideChrome ? 'auth-body' : '' ?>">
<?php if (!$hideChrome): ?>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="app-main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="icon-btn mobile-only" data-sidebar-toggle aria-label="Abrir menu">☰</button>
                <div>
                    <strong><?= e($pageTitle) ?></strong>
                    <small><?= e($demoUser['empresa']) ?> · <?= e($demoUser['unidade']) ?></small>
                </div>
            </div>
            <div class="topbar-right">
                <a class="icon-btn" href="notificacoes.php" aria-label="Notificações">🔔<span class="badge-dot"></span></a>
                <div class="user-chip">
                    <span class="avatar">CA</span>
                    <div><strong><?= e($demoUser['nome']) ?></strong><small><?= e($demoUser['perfil']) ?></small></div>
                </div>
            </div>
        </header>
        <main class="content">
<?php endif; ?>
