<?php
declare(strict_types=1);

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $base = '/agroForest/public';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('route_url')) {
    function route_url(string $area, string $pagina = 'dashboard'): string
    {
        return match ($area) {
            'recepcao'      => base_url('recepcao/?pagina=' . urlencode($pagina)),
            'administrativo'=> base_url('administrativo/?pagina=' . urlencode($pagina)),
            default         => base_url('?pagina=' . urlencode($pagina)),
        };
    }
}