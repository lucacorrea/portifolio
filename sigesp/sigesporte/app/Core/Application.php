<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Application
{
    private Router $router;
    private function __construct(private readonly string $basePath) {}

    public static function boot(string $basePath): self
    {
        self::loadEnvironment($basePath . '/.env');
        $app = new self($basePath);
        $config = require $basePath . '/config/app.php';
        date_default_timezone_set($config['timezone']);
        Session::start($config['session_lifetime']);
        $app->router = new Router();
        require $basePath . '/routes/web.php';
        return $app;
    }

    public function router(): Router { return $this->router; }
    public function run(): never { $this->router->dispatch(Request::capture())->send(); }
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
