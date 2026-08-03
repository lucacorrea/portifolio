<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Request
{
    private function __construct(public readonly string $method, public readonly string $path, private readonly array $query, private readonly array $body, private readonly array $files) {}
    public static function capture(): self
    {
        $method = strtoupper((string) ($_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = self::pathFromUri((string) ($_SERVER['REQUEST_URI'] ?? '/'));
        return new self($method, $path, $_GET, $_POST, $_FILES);
    }
    public static function pathFromUri(string $requestUri): string
    {
        $uriPath = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($uriPath) && $uriPath !== '' ? rawurldecode($uriPath) : '/';
        $basePath = Url::basePath();

        if ($basePath !== '') {
            if ($path === $basePath || $path === $basePath . '/') {
                $path = '/';
            } elseif (str_starts_with($path, $basePath . '/')) {
                $path = substr($path, strlen($basePath)) ?: '/';
            }
        }

        $path = '/' . ltrim($path, '/');
        return rtrim($path, '/') ?: '/';
    }
    public function input(string $key, mixed $default = null): mixed { return $this->body[$key] ?? $this->query[$key] ?? $default; }
    public function query(string $key, mixed $default = null): mixed { return $this->query[$key] ?? $default; }
    public function all(): array { return $this->body; }
    public function file(string $key): ?array { return $this->files[$key] ?? null; }
}
