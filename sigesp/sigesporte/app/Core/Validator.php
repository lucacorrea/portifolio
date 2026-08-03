<?php
declare(strict_types=1);
namespace Sigesp\Core;
final class Validator
{
    public static function cpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf) ?? '';
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($t = 9; $t < 11; $t++) { $sum = 0; for ($i = 0; $i < $t; $i++) $sum += (int) $cpf[$i] * (($t + 1) - $i); $digit = ((10 * $sum) % 11) % 10; if ($digit !== (int) $cpf[$t]) return false; }
        return true;
    }
    public static function email(string $value): bool { return filter_var($value, FILTER_VALIDATE_EMAIL) !== false; }
}
