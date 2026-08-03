<?php
declare(strict_types=1);
namespace Sigesp\Core;
final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): string
    {
        $base = dirname(__DIR__, 2) . '/app/Views/';
        extract($data, EXTR_SKIP);
        $viewFile = self::resolve($base, $view);
        ob_start(); require $viewFile; $content = (string) ob_get_clean();
        if ($layout === '') return $content;
        $layoutFile = self::resolve($base, $layout);
        ob_start(); require $layoutFile; return (string) ob_get_clean();
    }
    public static function component(string $component, array $data = []): void
    {
        $base = dirname(__DIR__, 2) . '/app/Views/components/';
        extract($data, EXTR_SKIP);
        require self::resolve($base, $component);
    }
    public static function url(string $path = ''): string { return Url::to($path); }
    public static function asset(string $path): string { return Url::asset($path); }
    public static function e(string|int|float|null $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    private static function resolve(string $base, string $name): string
    {
        if (preg_match('#^[a-z0-9/_-]+$#iD', $name) !== 1 || str_contains($name, '..')) {
            throw new \InvalidArgumentException('Nome de view inválido.');
        }

        $basePath = realpath($base);
        $file = realpath($base . $name . '.php');
        if ($basePath === false || $file === false || !str_starts_with($file, $basePath . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('View não encontrada.');
        }

        return $file;
    }
}
