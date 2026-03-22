<?php
declare(strict_types=1);

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/public/index.php';
        $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($baseDir === '' || $baseDir === '.') {
            $baseDir = '';
        }

        return $baseDir . '/' . ltrim($path, '/');
    }
}