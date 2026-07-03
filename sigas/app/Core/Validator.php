<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var list<string> */
    private const USER_STATUSES = ['pendente', 'ativo', 'bloqueado', 'rejeitado', 'inativo'];

    public static function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function cpf(string $value): bool
    {
        $cpf = self::onlyDigits($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $cpf[$index] * (($position + 1) - $index);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function strongPassword(string $value): bool
    {
        return strlen($value) >= 8
            && preg_match('/[A-Za-z]/', $value) === 1
            && preg_match('/\d/', $value) === 1
            && preg_match('/[^A-Za-z0-9]/', $value) === 1;
    }

    public static function positiveInt(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    public static function userStatus(string $status): bool
    {
        return in_array($status, self::USER_STATUSES, true);
    }

    /** @return list<string> */
    public static function userStatuses(): array
    {
        return self::USER_STATUSES;
    }
}
