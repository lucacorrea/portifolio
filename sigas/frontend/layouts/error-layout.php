<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/support/helpers.php';
$pageDefinition = ['title' => $errorTitle, 'description' => $errorMessage];
require __DIR__ . '/head.php';
?>
<body class="frontend-error-page">
    <main class="frontend-error-shell">
        <section class="content-card state-panel show" role="alert"><i class="bi bi-compass"></i><h1><?= sigas_frontend_escape($errorTitle) ?></h1><p><?= sigas_frontend_escape($errorMessage) ?></p><a class="btn btn-primary" href="portal.php"><i class="bi bi-grid"></i>Voltar ao portal</a></section>
    </main>
</body>
</html>
