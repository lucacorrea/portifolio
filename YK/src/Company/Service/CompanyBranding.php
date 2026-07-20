<?php

declare(strict_types=1);

namespace App\Company\Service;

final class CompanyBranding
{
    public static function shortName(mixed $value, string $fallback = 'K. Yamaguchi'): string
    {
        $name = trim((string) ($value ?? ''));
        if ($name === '') {
            $name = $fallback;
        }

        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words) || $words === []) {
            return $fallback;
        }

        return implode(' ', array_slice($words, 0, 3));
    }

    public static function safeLogoUrl(mixed $value): ?string
    {
        $logo = trim((string) ($value ?? ''));
        if (
            $logo === ''
            || str_contains($logo, "\0")
            || $logo !== strip_tags($logo)
            || preg_match('/[\x00-\x1F\x7F]/', $logo)
            || str_contains($logo, '\\')
        ) {
            return null;
        }

        $parts = parse_url($logo);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['scheme'])) {
            $scheme = strtolower((string) $parts['scheme']);
            return in_array($scheme, ['http', 'https'], true)
                && filter_var($logo, FILTER_VALIDATE_URL) !== false
                ? $logo
                : null;
        }

        if (str_starts_with($logo, '//') || isset($parts['host'])) {
            return null;
        }

        $path = rawurldecode((string) ($parts['path'] ?? ''));
        if ($path === '') {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        if (preg_match('#^empresa-logo\.php\?v=[a-f0-9]{32}\.(?:jpg|png|webp)$#', $logo)) {
            return $logo;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        return $logo;
    }
}
