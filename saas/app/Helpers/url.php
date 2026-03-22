<?php
declare(strict_types=1);

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = '/saas';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}
