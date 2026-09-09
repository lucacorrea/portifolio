<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/demo.php';
$pageTitle = $pageTitle ?? 'JL Comandas';
$currentPage = $currentPage ?? '';
$hideChrome = $hideChrome ?? false;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111827">
    <title><?= e($pageTitle) ?> · JL Soluções Tecnológicas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=2">
</head>
<body class="<?= $hideChrome ? 'auth-body' : '' ?>">
<?php if (!$hideChrome): ?>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="app-main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="icon-btn mobile-only" data-sidebar-toggle aria-label="Abrir menu">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div class="topbar-title">
                    <div class="eyebrow">JL Soluções Tecnológicas</div>
                    <strong><?= e($pageTitle) ?></strong>
                </div>
            </div>

            <div class="topbar-right">
                <div class="unit-chip desktop-only">
                    <span class="unit-icon"><svg viewBox="0 0 24 24"><path d="M4 21V7l8-4 8 4v14M8 10h8M8 14h8M9 21v-4h6v4"/></svg></span>
                    <div><small>Operando em</small><strong><?= e($demoUser['unidade']) ?></strong></div>
                    <span class="chevron">⌄</span>
                </div>

                <a class="quick-add desktop-only" href="comanda.php">
                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    <span>Nova comanda</span>
                </a>

                <a class="icon-btn notification-btn" href="notificacoes.php" aria-label="Notificações">
                    <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    <span class="badge-dot"></span>
                </a>

                <a class="user-chip" href="perfil.php">
                    <span class="avatar">CA</span>
                    <div><strong><?= e($demoUser['nome']) ?></strong><small><?= e($demoUser['perfil']) ?></small></div>
                    <span class="chevron desktop-only">⌄</span>
                </a>
            </div>
        </header>
        <main class="content">
<?php endif; ?>
