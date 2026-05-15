<?php

declare(strict_types=1);

use App\Core\Response;

function resolve_request_path(): array
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    $baseCandidates = [];

    if ($scriptDir !== '' && $scriptDir !== '.') {
        $baseCandidates[] = $scriptDir;
    }

    if (str_ends_with($scriptDir, '/public')) {
        $baseCandidates[] = substr($scriptDir, 0, -7) ?: '/';
    }

    $baseCandidates[] = '';
    $baseCandidates = array_values(array_unique($baseCandidates));

    foreach ($baseCandidates as $basePath) {
        if ($basePath === '' || $basePath === '/') {
            $normalized = $path;
        } elseif ($path === $basePath || str_starts_with($path, $basePath . '/')) {
            $normalized = substr($path, strlen($basePath)) ?: '/';
        } else {
            continue;
        }

        if ($normalized === '' || $normalized === '/index.php') {
            $normalized = '/';
        }

        if (str_starts_with($normalized, '/index.php/')) {
            $normalized = substr($normalized, strlen('/index.php')) ?: '/';
        }

        return [
            'base' => $basePath === '/' ? '' : $basePath,
            'path' => $normalized,
        ];
    }

    return [
        'base' => '',
        'path' => $path === '/index.php' ? '/' : $path,
    ];
}

try {
    $requestPath = resolve_request_path();
    $_SERVER['APP_BASE_PATH'] = $requestPath['base'];

    $router = require dirname(__DIR__) . '/bootstrap/app.php';

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $requestPath['path'];

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
