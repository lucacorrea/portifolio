<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Core\Response;
use App\Repositories\SaleRepository;
use App\Security\Auth;

Auth::requireLogin();

$repo = new SaleRepository();

Response::json([
    'success' => true,
    'data' => $repo->findAll((int)Auth::user()['empresa_id']),
]);
