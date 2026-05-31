<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Controllers\AuthController;
use App\Core\Request;

(new AuthController())->login(new Request());
