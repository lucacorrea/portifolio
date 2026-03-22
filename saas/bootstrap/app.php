<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path ? '/' . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        extract($data, EXTR_OVERWRITE);

        ob_start();
        require base_path('resources/views/' . $view . '.php');
        return (string) ob_get_clean();
    }
}

require_once base_path('app/Helpers/url.php');
require_once base_path('app/Helpers/asset.php');