<?php

declare(strict_types=1);

$frontController = __DIR__ . '/sigesporte/public/index.php';

if (!is_file($frontController)) {
    http_response_code(500);
    exit('Aplicação indisponível.');
}

require $frontController;
