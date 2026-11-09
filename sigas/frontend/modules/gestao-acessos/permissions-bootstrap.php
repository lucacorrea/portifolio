<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GovernancePermissionRepository;
use App\Repositories\PermissionRepository;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\GovernancePermissionService;
use App\Services\PermissionService;

$pdo = Database::connection();
$levels = new AccessLevelRepository($pdo);

return new GovernancePermissionService(
    new GovernancePermissionRepository($pdo),
    new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        $levels,
    ),
    new AuditService(new AuditLogRepository($pdo)),
);
