<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\GovernanceUserOverrideRepository;
use App\Repositories\GovernanceUsersRepository;
use App\Services\GovernanceUsersService;

$pdo = Database::connection();

return new GovernanceUsersService(
    new GovernanceUsersRepository($pdo),
    new GovernanceUserOverrideRepository($pdo),
);
