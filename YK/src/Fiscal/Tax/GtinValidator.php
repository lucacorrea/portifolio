<?php

declare(strict_types=1);

namespace App\Fiscal\Tax;

final class GtinValidator
{
    public static function normalize(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if ($digits === '') {
            return 'SEM GTIN';
        }
        if (!in_array(strlen($digits), [8, 12, 13, 14], true) || !self::hasValidCheckDigit($digits)) {
            return 'SEM GTIN';
        }
        return $digits;
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== 'SEM GTIN';
    }

    private static function hasValidCheckDigit(string $digits): bool
    {
        $body = substr($digits, 0, -1);
        $sum = 0;
        $weight = 3;
        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += (int) $body[$index] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }
        $expected = (10 - ($sum % 10)) % 10;
        return $expected === (int) substr($digits, -1);
    }
}
