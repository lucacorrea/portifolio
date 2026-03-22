<?php
declare(strict_types=1);

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = str_replace('\\', '/', dirname($scriptName));
        $baseDir = rtrim($baseDir, '/');

        if ($baseDir === '.' || $baseDir === '/public') {
            $baseDir = '';
        }

        return $baseDir . '/' . ltrim($path, '/');
    }
}