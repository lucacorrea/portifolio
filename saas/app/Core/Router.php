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
        $base = $this->detectBasePath();

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return $this->normalize($path);
    }

    private function detectBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        $scriptDir = rtrim($scriptDir, '/');

        if ($scriptDir === '' || $scriptDir === '.') {
            return '';
        }

        if (str_ends_with($scriptDir, '/public')) {
            $scriptDir = substr($scriptDir, 0, -7);
        }

        return rtrim($scriptDir, '/');
    }

    private function normalize(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}
