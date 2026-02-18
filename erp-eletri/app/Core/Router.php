<?php

namespace App\Core;

class Router
{
    private $routes = [];

    public function add($method, $path, $handler)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function get($path, $handler)
    {
        $this->add('GET', $path, $handler);
    }

    public function post($path, $handler)
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch()
    {
        $url = $_GET['url'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->match($route['path'], $url, $params)) {
                $handler = $route['handler'];
                
                // If handler is an array [Controller, method]
                if (is_array($handler)) {
                    $controllerName = $handler[0];
                    $actionName = $handler[1];
                    
                    $controller = new $controllerName();
                    call_user_func_array([$controller, $actionName], $params);
                    return;
                }
                
                // If handler is a closure
                if (is_callable($handler)) {
                    call_user_func_array($handler, $params);
                    return;
                }
            }
        }

        // 404 Not Found
        http_response_code(404);
        echo "404 - Page not found";
    }

    private function match($routePath, $requestUrl, &$params)
    {
        // Convert route path to regex
        // /user/{id} -> #^/user/([^/]+)$#
        $pattern = preg_replace('/\{([a-zA-Z0-9]+)\}/', '([^/]+)', $routePath);
        $pattern = "#^" . $pattern . "$#";

        if (preg_match($pattern, $requestUrl, $matches)) {
            array_shift($matches); // Remove full match
            $params = $matches;
            return true;
        }

        return false;
    }
}
