<?php
declare(strict_types=1);

require_once APP_PATH . '/Modules/Auth/Controllers/LoginController.php';

$router->get('/admin/login', [LoginController::class, 'show']);
$router->post('/admin/login', [LoginController::class, 'authenticate']);
$router->get('/admin/logout', [LoginController::class, 'logout']);