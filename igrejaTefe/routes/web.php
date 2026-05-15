<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CategoriaController;
use App\Controllers\ConfiguracaoController;
use App\Controllers\DashboardController;
use App\Controllers\EntradaController;
use App\Controllers\HomeController;
use App\Controllers\RelatorioController;
use App\Controllers\SaidaController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\TenantMiddleware;

$router->get('/', [HomeController::class, 'index']);

$router->get('/login', [AuthController::class, 'login']);
$router->get('/registro', [AuthController::class, 'register']);
$router->post('/login', [AuthController::class, 'attemptLogin'], [CsrfMiddleware::class]);
$router->post('/logout', [AuthController::class, 'logout'], [
    AuthMiddleware::class,
    CsrfMiddleware::class,
]);
$router->post('/registro', [AuthController::class, 'storeRegister'], [CsrfMiddleware::class]);

$router->get('/me', [AuthController::class, 'me'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/dashboard', [DashboardController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/entradas', [EntradaController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/saidas', [SaidaController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/categorias', [CategoriaController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/relatorios', [RelatorioController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/configuracoes', [ConfiguracaoController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);
