<?php
declare(strict_types=1);

if (!function_exists('base_url')) {
    function base_url(): string
    {
        $base = (string)($GLOBALS['app_config']['base_path'] ?? '');
        return rtrim($base, '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = base_url();
        $path = '/' . ltrim($path, '/');

        if ($path === '/') {
            return $base . '/';
        }

        return $base . $path;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}