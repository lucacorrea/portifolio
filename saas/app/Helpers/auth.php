<?php
declare(strict_types=1);

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return $_SESSION['auth'] ?? null;
    }
}

if (!function_exists('is_logged')) {
    function is_logged(): bool
    {
        return !empty($_SESSION['auth']);
    }
}
