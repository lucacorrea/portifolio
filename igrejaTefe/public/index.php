<?php

declare(strict_types=1);

use App\Core\Response;

try {
    $router = require dirname(__DIR__) . '/bootstrap/app.php';

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    $response = $router->dispatch($method, $uri);

    if ($response instanceof Response) {
        $response->send();
        return;
    }

    echo (string) $response;
} catch (Throwable $exception) {
    $debug = function_exists('env') ? env('APP_DEBUG', false) : false;
    http_response_code(500);

    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }

    echo 'Ocorreu um erro inesperado. Tente novamente em instantes.';
}
