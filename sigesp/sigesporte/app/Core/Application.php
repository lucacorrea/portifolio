<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Application
{
    private Router $router;
    private function __construct(private readonly string $basePath, private readonly array $config) {}

    public static function boot(string $basePath): self
    {
        self::loadEnvironment($basePath . '/.env');
        $config = require $basePath . '/config/app.php';
        error_reporting(E_ALL);
        ini_set('display_errors', $config['debug'] ? '1' : '0');
        ini_set('display_startup_errors', $config['debug'] ? '1' : '0');
        $app = new self($basePath, $config);
        date_default_timezone_set($config['timezone']);
        Session::start($config['session_lifetime']);
        $app->router = new Router();
        require $basePath . '/routes/web.php';
        return $app;
    }

    public function router(): Router { return $this->router; }
    public function run(): never
    {
        try {
            $this->router->dispatch(Request::capture())->send();
        } catch (\Throwable $exception) {
            if ($this->config['debug']) {
                throw $exception;
            }

            $this->logException($exception);
            $layout = Auth::check() ? 'layouts/app' : 'layouts/auth';
            (new Response(View::render('errors/500', ['title' => 'Erro interno'], $layout), 500))->send();
        }
    }

    private function logException(\Throwable $exception): void
    {
        $logDirectory = $this->basePath . '/storage/logs';
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }

        $message = sprintf(
            "[%s] %s: %s in %s:%d%s",
            date(DATE_ATOM),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL
        );
        if (is_dir($logDirectory) && is_writable($logDirectory)) {
            error_log($message, 3, $logDirectory . '/app.log');
            return;
        }

        error_log('SIGESP: falha interna não registrada em storage/logs.');
    }
    private static function loadEnvironment(string $file): void
    {
        if (!is_file($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}
