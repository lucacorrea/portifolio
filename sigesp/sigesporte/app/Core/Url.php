<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Url
{
    private static ?array $config = null;

    private static function config(): array
    {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__, 2) . '/config/app.php';
        }

        return self::$config;
    }

    public static function basePath(): string
    {
        return (string) (self::config()['base_path'] ?? '');
    }

    public static function to(string $path = ''): string
    {
        $path = trim($path);
        if ($path === '#') {
            return '#';
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $suffix = '';
        if (preg_match('/[?#]/', $path, $match, PREG_OFFSET_CAPTURE) === 1) {
            $offset = $match[0][1];
            $suffix = substr($path, $offset);
            $path = substr($path, 0, $offset);
        }

        $basePath = self::basePath();
        $normalizedPath = '/' . ltrim($path, '/');
        if ($path === '' || $normalizedPath === '/') {
            return ($basePath !== '' ? $basePath . '/' : '/') . $suffix;
        }

        if ($basePath !== '' && ($normalizedPath === $basePath || str_starts_with($normalizedPath, $basePath . '/'))) {
            return ($normalizedPath === $basePath ? $basePath . '/' : $normalizedPath) . $suffix;
        }

        return $basePath . $normalizedPath . $suffix;
    }

    public static function asset(string $path): string
    {
        return self::to('/assets/' . ltrim($path, '/'));
    }

    public static function absolute(string $path = ''): string
    {
        $appUrl = rtrim((string) (self::config()['url'] ?? ''), '/');
        $internalUrl = self::to($path);
        if ($appUrl === '') {
            return $internalUrl;
        }

        $basePath = self::basePath();
        if ($basePath !== '' && str_ends_with($appUrl, $basePath)) {
            $internalUrl = substr($internalUrl, strlen($basePath)) ?: '/';
        }

        return $appUrl . '/' . ltrim($internalUrl, '/');
    }
}
