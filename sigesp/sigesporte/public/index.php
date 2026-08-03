<?php
declare(strict_types=1);

$projectRoots = [
    dirname(__DIR__),
    dirname(__DIR__, 2) . '/apps/sigesporte',
    __DIR__ . '/app-private',
];
$isProjectRoot = static fn (string $path): bool => is_file($path . '/bootstrap/app.php')
    && is_file($path . '/app/Core/Application.php')
    && is_file($path . '/routes/web.php');

foreach ($projectRoots as $projectRoot) {
    if ($isProjectRoot($projectRoot)) {
        require $projectRoot . '/bootstrap/app.php';
    }
}

http_response_code(500);
exit('Aplicação indisponível.');
