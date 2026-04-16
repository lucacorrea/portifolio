<?php
declare(strict_types=1);

require_once APP_PATH . '/Modules/Admin/Controllers/DashboardController.php';

$router->get('/admin/dashboard', [DashboardController::class, 'index']);