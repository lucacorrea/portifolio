<?php

declare(strict_types=1);

namespace App\Fiscal\Tax;

final class FiscalIdentityValidator
{
    public static function normalizeTaxId(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function isValidCpfOrCnpj(?string $value): bool
    {
        $digits = self::normalizeTaxId($value);
        return strlen($digits) === 11 ? self::isValidCpf($digits) : self::isValidCnpj($digits);
    }

    public static function isValidCpf(string $digits): bool
    {
        if (preg_match('/^\d{11}$/', $digits) !== 1 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }
        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $digits[$index] * (($position + 1) - $index);
            }
            $digit = (10 * $sum) % 11;
            if ($digit === 10) {
                $digit = 0;
            }
            if ($digit !== (int) $digits[$position]) {
                return false;
            }
        }
        return true;
    }

    public static function isValidCnpj(string $digits): bool
    {
        if (preg_match('/^\d{14}$/', $digits) !== 1 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }
        foreach ([12, 13] as $position) {
            $weights = $position === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            foreach ($weights as $index => $weight) {
                $sum += (int) $digits[$index] * $weight;
            }
            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;
            if ($digit !== (int) $digits[$position]) {
                return false;
            }
        }
        return true;
    }
}
