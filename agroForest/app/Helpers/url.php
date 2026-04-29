<?php

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require dirname(__DIR__) . '/Config/app.php';
    }

    return $config;
}

function base_url(string $path = ''): string
{
    $base = rtrim(app_config()['base_url'] ?? '', '/');
    $path = ltrim($path, '/');

    return $path ? $base . '/' . $path : $base;
}

function route_url(string $area, string $pagina): string
{
    return match ($area) {
        'recepcao'       => base_url('recepcao/?pagina=' . urlencode($pagina)),
        'administrativo' => base_url('administrativo/?pagina=' . urlencode($pagina)),
        'dono'           => base_url('dono/?pagina=' . urlencode($pagina)),
        default          => base_url('index.php?area=' . urlencode($area) . '&pagina=' . urlencode($pagina)),
    };
}