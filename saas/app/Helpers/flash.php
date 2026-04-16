<?php
declare(strict_types=1);

if (!function_exists('flash_set')) {
    function flash_set(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }
}

if (!function_exists('flash_get')) {
    function flash_get(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;

        if (isset($_SESSION['_flash'][$key])) {
            unset($_SESSION['_flash'][$key]);
        }

        return $message;
    }
}