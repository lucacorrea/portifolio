<?php

declare(strict_types=1);

namespace App\Config;

use App\Core\Environment;

final class StorageConfig
{
    public static function privateBasePath(): string
    {
        return rtrim((string) Environment::get('PRIVATE_BASE_PATH', ''), DIRECTORY_SEPARATOR);
    }

    public static function imagePath(): string
    {
        return Environment::required('SIGAS_IMAGE_PATH');
    }

    public static function documentPath(): string
    {
        return Environment::required('SIGAS_DOCUMENT_PATH');
    }

    public static function logPath(): string
    {
        return (string) Environment::get('SIGAS_LOG_PATH', '');
    }

    public static function maxImageSize(): int
    {
        return Environment::int('MAX_IMAGE_SIZE', 5242880);
    }

    public static function maxDocumentSize(): int
    {
        return Environment::int('MAX_DOCUMENT_SIZE', 10485760);
    }
}
