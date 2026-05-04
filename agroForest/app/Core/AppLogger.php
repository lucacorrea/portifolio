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
            return;
        }

        $line = sprintf('[%s] %s', date('Y-m-d H:i:s'), $message);

        if ($exception instanceof Throwable) {
            $line .= sprintf(
                ' | %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
        }

        @file_put_contents($directory . '/app.log', $line . PHP_EOL, FILE_APPEND);
    }
}
