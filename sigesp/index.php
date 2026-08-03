<?php

declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($requestPath === '/sigesp/index.php') {
    header('Location: /sigesp/', true, 302);
    exit;
}

$frontController = __DIR__ . '/sigesporte/public/index.php';

if (!is_file($frontController)) {
    http_response_code(500);
    exit('Aplicação indisponível.');
}

require $frontController;
