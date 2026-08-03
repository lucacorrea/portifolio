<?php
declare(strict_types=1);

$projectRoots = [
    dirname(__DIR__),
    dirname(__DIR__, 2) . '/apps/sigesporte',
    __DIR__ . '/app-private',
];

foreach ($projectRoots as $projectRoot) {
    if (is_file($projectRoot . '/bootstrap/app.php')) {
        require $projectRoot . '/bootstrap/app.php';
    }
}

http_response_code(500);
exit('Aplicação indisponível.');
