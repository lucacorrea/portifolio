<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private static string $logPath = '';

    /** @var list<string> */
    private static array $secretTerms = [
        'password',
        'senha',
        'senha_hash',
        'token',
        'secret',
        'authorization',
        'cookie',
        'db_password',
        'installation_key',
    ];

    public static function configure(string $logPath): void
    {
        self::$logPath = trim($logPath);

        if (self::$logPath !== '' && !is_dir(self::$logPath)) {
            @mkdir(self::$logPath, 0750, true);
        }
    }

    public static function application(string $message, array $context = []): void
    {
        self::write('application.log', $message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        self::write('security.log', $message, $context);
    }

    public static function authentication(string $message, array $context = []): void
    {
        self::write('authentication.log', $message, $context);
    }

    public static function upload(string $message, array $context = []): void
    {
        self::write('upload.log', $message, $context);
    }

    private static function write(string $file, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s %s%s",
            date('Y-m-d H:i:s'),
            $message,
            json_encode(self::sanitize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            PHP_EOL
        );

        if (self::$logPath === '' || !is_dir(self::$logPath) || !is_writable(self::$logPath)) {
            error_log($message);
            return;
        }

        @file_put_contents(
            rtrim(self::$logPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file,
            $line,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function sanitize(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            foreach (self::$secretTerms as $term) {
                if (str_contains($normalizedKey, $term)) {
                    $clean[$key] = '[redacted]';
                    continue 2;
                }
            }

            $clean[$key] = is_array($value) ? self::sanitize($value) : self::safeValue($value);
        }

        return $clean;
    }

    private static function safeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return mb_substr((string) $value, 0, 500);
    }
}
