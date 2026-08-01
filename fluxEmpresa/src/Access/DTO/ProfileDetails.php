<?php
declare(strict_types=1);

namespace App\Access\DTO;

use App\Access\Entity\Profile;

final class ProfileDetails
{
    /**
     * @param int[] $permissionIds
     */
    public function __construct(
        private readonly Profile $profile,
        private readonly array $permissionIds,
        private readonly int $totalUsers,
        private readonly int $totalPermissions
    ) {
    }

    public function profile(): Profile
    {
        return $this->profile;
    }

    /**
     * @return int[]
     */
    public function permissionIds(): array
    {
        return $this->permissionIds;
    }

    public function totalUsers(): int
    {
        return $this->totalUsers;
    }

    public function totalPermissions(): int
    {
        return $this->totalPermissions;
    }
}
