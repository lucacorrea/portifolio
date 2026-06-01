<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private ?array $json = null;

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json()[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->json());
    }

    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        $this->json = is_array($decoded) ? $decoded : [];

        return $this->json;
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }
}
