<?php
declare(strict_types=1);

if (!function_exists('base_url_path')) {
    function base_url_path(): string
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
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = base_url_path();
        $path = '/' . ltrim($path, '/');

        if ($path === '/') {
            return ($base !== '' ? $base : '') . '/';
        }

        return ($base !== '' ? $base : '') . $path;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}
