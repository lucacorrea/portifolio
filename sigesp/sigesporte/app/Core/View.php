<?php
declare(strict_types=1);
namespace Sigesp\Core;
final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): string
    {
        $base = dirname(__DIR__, 2) . '/app/Views/';
        extract($data, EXTR_SKIP);
        ob_start(); require $base . $view . '.php'; $content = (string) ob_get_clean();
        if ($layout === '') return $content;
        ob_start(); require $base . $layout . '.php'; return (string) ob_get_clean();
    }
    public static function component(string $component, array $data = []): void
    {
        $base = dirname(__DIR__, 2) . '/app/Views/components/';
        extract($data, EXTR_SKIP);
        require $base . $component . '.php';
    }
    public static function url(string $path = ''): string { return Url::to($path); }
    public static function asset(string $path): string { return Url::asset($path); }
    public static function e(string|int|float|null $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
