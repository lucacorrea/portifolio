<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\GovernanceRepository;
use App\Services\GovernanceService;

return new GovernanceService(
    new GovernanceRepository(Database::connection())
);
