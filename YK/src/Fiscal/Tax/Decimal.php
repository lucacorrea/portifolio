<?php

declare(strict_types=1);

namespace App\Fiscal\Tax;

use InvalidArgumentException;

final class Decimal
{
    public static function moneyToCents(string $value): int
    {
        return self::toScaledInteger($value, 2, 'valor monetário');
    }

    public static function rateToUnits(string $value): int
    {
        return self::toScaledInteger($value, 4, 'alíquota');
    }

    public static function normalizeUnsigned(string $value, int $scale, string $field): string
    {
        if ($scale < 0 || $scale > 10) {
            throw new InvalidArgumentException('Escala decimal inválida.');
        }

        $units = self::toScaledInteger($value, $scale, $field);
        if ($scale === 0) {
            return (string) $units;
        }

        $factor = 10 ** $scale;
        return intdiv($units, $factor) . '.'
            . str_pad((string) ($units % $factor), $scale, '0', STR_PAD_LEFT);
    }

    public static function taxCents(int $baseCents, int $rateUnits): int
    {
        if ($baseCents < 0 || $rateUnits < 0) {
            throw new InvalidArgumentException('Base ou alíquota fiscal inválida.');
        }

        return intdiv(($baseCents * $rateUnits) + 500000, 1000000);
    }

    public static function formatCents(int $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);
        return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function formatRate(int $value): string
    {
        $whole = intdiv($value, 10000);
        $fraction = str_pad((string) ($value % 10000), 4, '0', STR_PAD_LEFT);
        return $whole . '.' . $fraction;
    }

    private static function toScaledInteger(string $value, int $scale, string $field): int
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,' . $scale . '})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Formato de ' . $field . ' inválido.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $whole * (10 ** $scale)) + (int) str_pad($fraction, $scale, '0');
    }
}
