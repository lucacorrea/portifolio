<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/Core/Env.php';

\App\Core\Env::load(dirname(__DIR__) . '/.env');

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return \App\Core\Env::get($key, $default);
    }
}

if (!function_exists('env_first')) {
    function env_first(array $keys, mixed $default = null): mixed
    {
        return \App\Core\Env::first($keys, $default);
    }
}
