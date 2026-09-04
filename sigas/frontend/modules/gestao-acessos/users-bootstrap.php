<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\GovernanceUsersRepository;
use App\Services\GovernanceUsersService;

return new GovernanceUsersService(
    new GovernanceUsersRepository(Database::connection())
);
