<?php
declare(strict_types=1);

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('public/assets/' . ltrim($path, '/'));
    }
}