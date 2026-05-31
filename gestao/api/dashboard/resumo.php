<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Core\Response;
use App\Security\Auth;

Auth::requireLogin();

$repo = new \App\Repositories\DashboardRepository();
$resumo = $repo->getTodaySummary((int) Auth::user()['empresa_id']);

Response::json([
    'success' => true,
    'data' => $resumo,
]);
