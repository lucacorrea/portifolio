<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Controllers\ReportController;
use App\Core\Request;

(new ReportController())->products(new Request());
