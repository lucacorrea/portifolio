<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Core\Csrf;
use App\Core\Logger;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrfToken = isset($_POST['_csrf']) && is_string($_POST['_csrf'])
        ? $_POST['_csrf']
        : null;

    if (!Csrf::validateAndConsume($csrfToken, 'create-first-admin')) {
        http_response_code(419);
        Logger::security('Invalid CSRF token on first admin installer.');
        echo 'Requisição inválida.';
        exit;
    }
}

http_response_code(410);
echo 'Instalador movido para a área pública temporária protegida.';
