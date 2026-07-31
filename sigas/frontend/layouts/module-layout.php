<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/support/helpers.php';
$pageDefinition = sigas_frontend_page($pageDefinition);
require __DIR__ . '/head.php';
?>
<body class="frontend-module-page" data-module="<?= sigas_frontend_escape($environmentKey) ?>" data-page="<?= sigas_frontend_escape($pageKey) ?>">
    <div class="module-shell module-shell--<?= sigas_frontend_escape($environment['theme']) ?>" data-frontend-shell>
        <?php require __DIR__ . '/module-sidebar.php'; ?>
        <div class="module-main">
            <?php require __DIR__ . '/module-topbar.php'; ?>
            <main class="module-content frontend-module-content" id="mainContent">
                <?php require dirname(__DIR__) . '/components/page-header.php'; ?>
                <?php require dirname(__DIR__) . '/components/demo-notice.php'; ?>
                <?php require dirname(__DIR__) . '/components/page-content.php'; ?>
                <?php if (!empty($pageCustomContent)): ?><?= $pageCustomContent ?><?php endif; ?>
            </main>
            <?php require __DIR__ . '/footer.php'; ?>
        </div>
        <?php require __DIR__ . '/mobile-navigation.php'; ?>
    </div>
    <?php require dirname(__DIR__) . '/components/detail-modal.php'; ?>
    <?php require dirname(__DIR__) . '/components/confirmation-modal.php'; ?>
    <?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
