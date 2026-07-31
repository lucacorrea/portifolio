<?php

declare(strict_types=1);

namespace App\Sales\DTO;

use App\Access\DTO\AuthenticatedUser;

final class BudgetActorData
{
    public function __construct(
        private readonly int $userId,
        private readonly string $name,
        private readonly int $profileId,
        private readonly string $profileCode,
        private readonly string $profileName,
        private readonly bool $support
    ) {
    }

    public static function fromAuthenticatedUser(AuthenticatedUser $user): self
    {
        return new self($user->id(), $user->name(), $user->profileId(), $user->profileCode(), $user->profileName(), $user->isSupport());
    }

    public function userId(): int { return $this->userId; }
    public function name(): string { return $this->name; }
    public function profileId(): int { return $this->profileId; }
    public function profileCode(): string { return $this->profileCode; }
    public function profileName(): string { return $this->profileName; }
    public function isSupport(): bool { return $this->support; }
}
