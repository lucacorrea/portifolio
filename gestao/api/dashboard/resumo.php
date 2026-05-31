<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Core\Response;
use App\Security\Auth;

Auth::requireLogin();

Response::json([
    'success' => true,
    'message' => 'Endpoint preparado para integração real.',
    'data' => [],
]);
