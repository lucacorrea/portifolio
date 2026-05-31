<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Security\Auth;

Auth::requireLogin();

$repo = new ClientRepository();

Response::json([
    'success' => true,
    'data' => $repo->findAll((int)Auth::user()['empresa_id']),
]);
