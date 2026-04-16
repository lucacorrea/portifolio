<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path   = $this->extractPath($uri);

        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo '404 - Página não encontrada.';
            exit;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $action] = $handler;

            if (!class_exists($controllerClass)) {
                http_response_code(500);
                echo 'Controller não encontrado.';
                exit;
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $action)) {
                http_response_code(500);
                echo 'Action não encontrada.';
                exit;
            }

            $controller->{$action}();
            return;
        }

        if (is_callable($handler)) {
            $handler();
            return;
        }

        http_response_code(500);
        echo 'Rota inválida.';
        exit;
    }

    private function extractPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $basePath = rtrim((string)($GLOBALS['app_config']['base_path'] ?? ''), '/');

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
            if ($path === '' || $path === false) {
                $path = '/';
            }
        }

        return $this->normalize($path);
    }

    private function normalize(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}