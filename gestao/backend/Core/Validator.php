<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function required(mixed $value): bool
    {
        return !($value === null || trim((string) $value) === '');
    }

    public static function max(string $value, int $length): bool
    {
        return mb_strlen($value) <= $length;
    }
}
