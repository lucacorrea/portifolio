<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Request
{
    private function __construct(public readonly string $method, public readonly string $path, private readonly array $query, private readonly array $body, private readonly array $files) {}
    public static function capture(): self
    {
        $method = strtoupper($_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return new self($method, rtrim($path, '/') ?: '/', $_GET, $_POST, $_FILES);
    }
    public function input(string $key, mixed $default = null): mixed { return $this->body[$key] ?? $this->query[$key] ?? $default; }
    public function query(string $key, mixed $default = null): mixed { return $this->query[$key] ?? $default; }
    public function all(): array { return $this->body; }
    public function file(string $key): ?array { return $this->files[$key] ?? null; }
}
