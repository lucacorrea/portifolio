<?php
declare(strict_types=1);

require_once APP_PATH . '/Modules/Auth/Controllers/LoginController.php';

$router->get('/', [LoginController::class, 'show']);
$router->post('/login', [LoginController::class, 'authenticate']);
$router->get('/logout', [LoginController::class, 'logout']);