<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $viewPath = self::path($view);

        if (!is_file($viewPath)) {
            throw new RuntimeException("View [{$view}] was not found.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout === null) {
            return (string) $content;
        }

        $layoutPath = self::path($layout);

        if (!is_file($layoutPath)) {
            throw new RuntimeException("Layout [{$layout}] was not found.");
        }

        ob_start();
        require $layoutPath;

        return (string) ob_get_clean();
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function path(string $view): string
    {
        return BASE_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
    }
}

