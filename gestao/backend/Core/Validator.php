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

    public static function intId(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    public static function date(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public static function money(mixed $value, float $min = 0.0): bool
    {
        return is_numeric($value) && (float) $value >= $min;
    }

    public static function decimal(mixed $value, float $min = 0.0): bool
    {
        return is_numeric($value) && (float) $value >= $min;
    }

    public static function brazilWhatsapp(string $value): bool
    {
        return $value === '' || self::normalizeBrazilWhatsapp($value) !== '';
    }

    public static function normalizeBrazilWhatsapp(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '55') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) !== 10) {
            return '';
        }

        return sprintf(
            '+55 (%s) %s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 4),
            substr($digits, 6, 4)
        );
    }
}
