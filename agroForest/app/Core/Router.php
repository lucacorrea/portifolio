<?php
class Router
{
    public static function resolve(string $area, string $pagina): ?string
    {
        $map = require dirname(__DIR__, 2) . '/routes/web.php';
        return $map[$area][$pagina] ?? null;
    }
}
