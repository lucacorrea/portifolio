<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\StorageConfig;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

final class Storage
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public static function imageRoot(): string
    {
        return self::validateRoot(StorageConfig::imagePath());
    }

    public static function documentRoot(): string
    {
        return self::validateRoot(StorageConfig::documentPath());
    }

    public static function ensureDirectory(string $directory): void
    {
        self::rejectUnsafePath($directory);

        if (is_file($directory)) {
            throw new RuntimeException('Storage path is not a directory.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Storage directory could not be created.');
        }

        if (!is_readable($directory) || !is_writable($directory)) {
            throw new RuntimeException('Storage directory is not readable and writable.');
        }
    }

    public static function buildRelativeDirectory(?DateTimeInterface $date = null): string
    {
        $date ??= new DateTimeImmutable();

        return $date->format('Y') . '/' . $date->format('m');
    }

    public static function resolveImagePath(string $relativePath): string
    {
        return self::resolveRelativePath($relativePath, self::imageRoot());
    }

    public static function resolveDocumentPath(string $relativePath): string
    {
        return self::resolveRelativePath($relativePath, self::documentRoot());
    }

    public static function generateRandomFilename(string $extension): string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('File extension is not allowed.');
        }

        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    public static function isInsideRoot(string $path, string $root): bool
    {
        $rootReal = realpath($root);

        if ($rootReal === false) {
            return false;
        }

        $pathReal = realpath($path);
        $candidate = $pathReal !== false ? $pathReal : self::normalizePath($path);
        $rootNormalized = rtrim(self::normalizePath($rootReal), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with(self::normalizePath($candidate), $rootNormalized);
    }

    private static function validateRoot(string $root): string
    {
        if (trim($root) === '') {
            throw new RuntimeException('Storage root is not configured.');
        }

        self::rejectUnsafePath($root);

        if (!is_dir($root) || !is_readable($root) || !is_writable($root)) {
            throw new RuntimeException('Storage root is not available.');
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private static function resolveRelativePath(string $relativePath, string $root): string
    {
        self::rejectUnsafePath($relativePath);

        $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        $candidate = $root . DIRECTORY_SEPARATOR . $relativePath;

        if (!self::isInsideRoot($candidate, $root)) {
            throw new RuntimeException('Storage path is outside the configured root.');
        }

        return $candidate;
    }

    private static function rejectUnsafePath(string $path): void
    {
        if (
            str_contains($path, '..')
            || str_contains($path, "\0")
            || str_contains($path, '://')
        ) {
            throw new RuntimeException('Unsafe storage path.');
        }
    }

    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
