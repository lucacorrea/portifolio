<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Controllers\ClientController;
use App\Core\Request;

(new ClientController())->warning(new Request());
