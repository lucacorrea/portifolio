<?php
class AppLogger
{
    public static function init(): void
    {
        $path = self::path();

        if ($path === null) {
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (!is_file($path) && is_dir($directory) && is_writable($directory)) {
            @file_put_contents($path, '');
        }
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function error(string $message, ?Throwable $exception = null): void
    {
        self::write('ERROR', $message, $exception);
    }

    public static function path(): ?string
    {
        if (!defined('BASE_PATH')) {
            return null;
        }

        return BASE_PATH . '/storage/logs/app.log';
    }

    private static function write(string $level, string $message, ?Throwable $exception = null): void
    {
        $path = self::path();

        if ($path === null) {
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            error_log(self::formatLine($level, $message, $exception));
            return;
        }

        $line = self::formatLine($level, $message, $exception);

        if (@file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log($line);
        }
    }

    private static function formatLine(string $level, string $message, ?Throwable $exception = null): string
    {
        $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), $level, $message);

        if ($exception instanceof Throwable) {
            $line .= sprintf(
                ' | %s: %s in %s:%d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
        }

        return $line;
    }
}
