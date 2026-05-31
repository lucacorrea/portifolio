<?php

declare(strict_types=1);

namespace App\Security;

final class Permission
{
    public static function canManageSettings(?array $user): bool
    {
        return in_array($user['nivel'] ?? '', ['admin', 'gerente'], true);
    }

    public static function canCancelSale(?array $user): bool
    {
        return in_array($user['nivel'] ?? '', ['admin', 'gerente'], true);
    }

    public static function canManageProducts(?array $user): bool
    {
        return in_array($user['nivel'] ?? '', ['admin', 'gerente', 'estoquista'], true);
    }
}
