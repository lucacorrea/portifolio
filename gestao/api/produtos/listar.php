<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Controllers\ProductController;
use App\Core\Request;

(new ProductController())->list(new Request());
