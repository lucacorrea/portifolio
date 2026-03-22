<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'SaaS') ?></title>

    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/layout/sidebar.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/layout/topbar.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/layout/responsive.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/components/cards.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/components/buttons.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/components/alerts.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/pages/dashboard.css')) ?>">
</head>
<body>
<div class="app-shell">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <?php require base_path('resources/views/partials/sidebar.php'); ?>

    <main class="app-main">
        <?php require base_path('resources/views/partials/topbar.php'); ?>

        <div class="app-content">
            <?php require base_path('resources/views/' . $contentView . '.php'); ?>
        </div>
    </main>
</div>

<script src="<?= htmlspecialchars(asset('js/app.js')) ?>"></script>
<script src="<?= htmlspecialchars(asset('js/sidebar.js')) ?>"></script>
</body>
</html>
