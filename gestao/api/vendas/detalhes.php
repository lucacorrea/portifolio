<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Controllers\SaleController;
use App\Core\Request;

(new SaleController())->details(new Request());
