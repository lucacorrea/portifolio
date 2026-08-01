<?php
declare(strict_types=1);

namespace App\Access\DTO;

final class LoginResult
{
    public function __construct(
        private readonly bool $success,
        private readonly string $message,
        private readonly ?AuthenticatedUser $user = null
    ) {
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function user(): ?AuthenticatedUser
    {
        return $this->user;
    }
}
