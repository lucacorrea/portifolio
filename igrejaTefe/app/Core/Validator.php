<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, string $message): self
    {
        if (!isset($this->data[$field]) || trim((string) $this->data[$field]) === '') {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function email(string $field, string $message): self
    {
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function max(string $field, int $length, string $message): self
    {
        $value = (string) ($this->data[$field] ?? '');

        if (mb_strlen($value) > $length) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function positiveMoney(string $field, string $message): self
    {
        $value = $this->data[$field] ?? null;

        if (!is_numeric($value) || (float) $value <= 0) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function dateNotFuture(string $field, string $message): self
    {
        $value = $this->data[$field] ?? null;

        if (!$value) {
            return $this;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        $today = new DateTimeImmutable('today');

        if (!$date || $date > $today) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }
}

