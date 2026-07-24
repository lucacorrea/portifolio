<?php
declare(strict_types=1);

namespace App\Access\DTO;

final class AuthenticatedUser
{
    /**
     * @param string[] $permissions
     */
    public function __construct(
        private readonly int $id,
        private readonly int $profileId,
        private readonly string $profileName,
        private readonly string $name,
        private readonly string $username,
        private readonly string $email,
        private readonly array $permissions
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function profileId(): int
    {
        return $this->profileId;
    }

    public function profileName(): string
    {
        return $this->profileName;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function email(): string
    {
        return $this->email;
    }

    /**
     * @return string[]
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = [];

        foreach ($parts as $part) {
            if ($part !== '') {
                $letters[] = strtoupper(substr($part, 0, 1));
            }
            if (count($letters) === 2) {
                break;
            }
        }

        return $letters === [] ? 'US' : implode('', $letters);
    }
}
