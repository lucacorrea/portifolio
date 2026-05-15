<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use InvalidArgumentException;

final class Router
{
    private array $routes = [];

    public function get(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->add('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->add('POST', $uri, $action, $middleware);
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $route = $this->routes[$method][$this->normalizeUri($uri)] ?? null;

        if ($route === null) {
            return Response::notFound();
        }

        $middlewareResponse = $this->runMiddleware($route['middleware']);

        if ($middlewareResponse instanceof Response) {
            return $middlewareResponse;
        }

        return $this->callAction($route['action']);
    }

    private function add(string $method, string $uri, callable|array $action, array $middleware): void
    {
        $this->routes[$method][$this->normalizeUri($uri)] = [
            'action' => $action,
            'middleware' => $middleware,
        ];
    }

    private function normalizeUri(string $uri): string
    {
        $path = '/' . trim($uri, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function runMiddleware(array $middleware): ?Response
    {
        foreach ($middleware as $middlewareClass) {
            $instance = is_string($middlewareClass) ? new $middlewareClass() : $middlewareClass;

            if (!$instance instanceof MiddlewareInterface) {
                throw new InvalidArgumentException('Invalid middleware registered.');
            }

            $response = $instance->handle();

            if ($response instanceof Response) {
                return $response;
            }
        }

        return null;
    }

    private function callAction(callable|array $action): mixed
    {
        if (is_array($action) && is_string($action[0])) {
            $controller = new $action[0]();

            return $controller->{$action[1]}();
        }

        return $action();
    }
}

