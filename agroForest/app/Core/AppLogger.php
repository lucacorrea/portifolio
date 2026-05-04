<?php
class AppLogger
{
    public static function error(string $message, ?Throwable $exception = null): void
    {
        if (!defined('BASE_PATH')) {
            return;
        }

        $directory = BASE_PATH . '/storage/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            error_log(self::formatLine($message, $exception));
            return;
        }

        $line = self::formatLine($message, $exception);

        if (@file_put_contents($directory . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log($line);
        }
    }

    private static function formatLine(string $message, ?Throwable $exception = null): string
    {
        $line = sprintf('[%s] %s', date('Y-m-d H:i:s'), $message);

        if ($exception instanceof Throwable) {
            $line .= sprintf(
                ' | %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
        }

        return $line;
    }
}
