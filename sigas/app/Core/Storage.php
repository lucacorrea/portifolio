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

    public static function ensureImageDirectory(string $relativeDirectory): string
    {
        return self::ensureDirectoryInsideRoot($relativeDirectory, self::imageRoot());
    }

    public static function ensureDocumentDirectory(string $relativeDirectory): string
    {
        return self::ensureDirectoryInsideRoot($relativeDirectory, self::documentRoot());
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
        $candidate = self::normalizePath($pathReal !== false ? $pathReal : $path);
        $rootBase = rtrim(self::normalizePath($rootReal), DIRECTORY_SEPARATOR);
        $rootNormalized = $rootBase . DIRECTORY_SEPARATOR;

        return $candidate === $rootBase || str_starts_with($candidate, $rootNormalized);
    }

    private static function ensureDirectoryInsideRoot(string $relativeDirectory, string $root): string
    {
        $relativeDirectory = self::normalizeRelativePath($relativeDirectory);
        $target = $root . DIRECTORY_SEPARATOR . $relativeDirectory;
        $parent = self::nearestExistingParent($target);

        if (!self::isInsideRoot($parent, $root) || !self::isInsideRoot($target, $root)) {
            throw new RuntimeException('Storage path is outside the configured root.');
        }

        if (is_file($target)) {
            throw new RuntimeException('Storage path is not a directory.');
        }

        if (!is_dir($target) && !mkdir($target, 0750, true) && !is_dir($target)) {
            throw new RuntimeException('Storage directory could not be created.');
        }

        if (!is_readable($target) || !is_writable($target)) {
            throw new RuntimeException('Storage directory is not readable and writable.');
        }

        return $target;
    }

    private static function validateRoot(string $root): string
    {
        if (trim($root) === '') {
            throw new RuntimeException('Storage root is not configured.');
        }

        self::rejectUnsafePath($root, false);

        if (!is_dir($root) || !is_readable($root) || !is_writable($root)) {
            throw new RuntimeException('Storage root is not available.');
        }

        $real = realpath($root);

        if ($real === false) {
            throw new RuntimeException('Storage root is not available.');
        }

        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private static function resolveRelativePath(string $relativePath, string $root): string
    {
        $relativePath = self::normalizeRelativePath($relativePath);
        $candidate = $root . DIRECTORY_SEPARATOR . $relativePath;

        if (!self::isInsideRoot($candidate, $root)) {
            throw new RuntimeException('Storage path is outside the configured root.');
        }

        return $candidate;
    }

    private static function normalizeRelativePath(string $path): string
    {
        self::rejectUnsafePath($path, true);

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw new RuntimeException('Absolute storage paths are not allowed.');
        }

        return trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private static function rejectUnsafePath(string $path, bool $rejectDriveLetter): void
    {
        if (
            str_contains($path, '..')
            || str_contains($path, "\0")
            || str_contains($path, '://')
            || ($rejectDriveLetter && preg_match('/^[A-Za-z]:/', $path) === 1)
        ) {
            throw new RuntimeException('Unsafe storage path.');
        }
    }

    private static function nearestExistingParent(string $path): string
    {
        $parent = $path;

        while (!is_dir($parent)) {
            $next = dirname($parent);

            if ($next === $parent) {
                throw new RuntimeException('Storage parent directory was not found.');
            }

            $parent = $next;
        }

        $real = realpath($parent);

        if ($real === false) {
            throw new RuntimeException('Storage parent directory was not found.');
        }

        return $real;
    }

    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
