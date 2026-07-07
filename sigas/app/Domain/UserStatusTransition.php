<?php

declare(strict_types=1);

namespace App\Domain;

use App\Exceptions\InvalidStatusTransitionException;

final class UserStatusTransition
{
    /** @return array<string, list<string>> */
    private static function allowed(): array
    {
        return [
            UserStatus::PENDING->value => [UserStatus::ACTIVE->value, UserStatus::REJECTED->value],
            UserStatus::ACTIVE->value => [UserStatus::BLOCKED->value, UserStatus::INACTIVE->value],
            UserStatus::BLOCKED->value => [UserStatus::ACTIVE->value],
            UserStatus::INACTIVE->value => [UserStatus::ACTIVE->value],
            UserStatus::REJECTED->value => [UserStatus::PENDING->value],
        ];
    }

    public static function canTransition(UserStatus $from, UserStatus $to, bool $administrativeReopen = false): bool
    {
        if ($from === $to) {
            return true;
        }

        if ($from === UserStatus::REJECTED && $to === UserStatus::PENDING) {
            return $administrativeReopen;
        }

        return in_array($to->value, self::allowed()[$from->value] ?? [], true);
    }

    public static function assertAllowed(UserStatus $from, UserStatus $to, bool $administrativeReopen = false): void
    {
        if (!self::canTransition($from, $to, $administrativeReopen)) {
            throw new InvalidStatusTransitionException('Transição de status não permitida.');
        }
    }
}
