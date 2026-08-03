<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Router
{
    private array $routes = [];
    public function get(string $path, callable|array $action): void { $this->add('GET', $path, $action); }
    public function post(string $path, callable|array $action): void { $this->add('POST', $path, $action); }
    public function put(string $path, callable|array $action): void { $this->add('PUT', $path, $action); }
    private function add(string $method, string $path, callable|array $action): void { $this->routes[$method][] = [$path, $action]; }
    public function dispatch(Request $request): Response
    {
        foreach ($this->routes[$request->method] ?? [] as [$path, $action]) {
            $pattern = '#^' . preg_replace('#\\{([a-z_]+)\\}#', '(?P<$1>[^/]+)', $path) . '$#';
            if (preg_match($pattern, $request->path, $matches)) {
                $args = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $result = is_array($action) ? (new $action[0]())->{$action[1]}($request, ...$args) : $action($request, ...$args);
                return $result instanceof Response ? $result : new Response((string) $result);
            }
        }
        return new Response(View::render('errors/404', ['title' => 'Página não encontrada']), 404);
    }
}
