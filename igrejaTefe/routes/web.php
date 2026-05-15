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
use App\Controllers\UsuarioController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
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

$router->get('/entradas/criar', [EntradaController::class, 'create'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->post('/entradas', [EntradaController::class, 'store'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    CsrfMiddleware::class,
]);

$router->get('/saidas', [SaidaController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/saidas/criar', [SaidaController::class, 'create'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->post('/saidas', [SaidaController::class, 'store'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    CsrfMiddleware::class,
]);

$router->get('/categorias', [CategoriaController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/categorias/criar', [CategoriaController::class, 'create'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->post('/categorias', [CategoriaController::class, 'store'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    CsrfMiddleware::class,
]);

$router->get('/relatorios', [RelatorioController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/relatorios/exportar/excel', [RelatorioController::class, 'exportExcel'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/relatorios/exportar/pdf', [RelatorioController::class, 'exportPdf'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);

$router->get('/usuarios', [UsuarioController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    RoleMiddleware::allow(['admin']),
]);

$router->get('/usuarios/criar', [UsuarioController::class, 'create'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    RoleMiddleware::allow(['admin']),
]);

$router->post('/usuarios', [UsuarioController::class, 'store'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    RoleMiddleware::allow(['admin']),
    CsrfMiddleware::class,
]);

$router->get('/usuarios/editar', [UsuarioController::class, 'edit'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    RoleMiddleware::allow(['admin']),
]);

$router->post('/usuarios/atualizar', [UsuarioController::class, 'update'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    RoleMiddleware::allow(['admin']),
    CsrfMiddleware::class,
]);

$router->post('/usuarios/desativar', [UsuarioController::class, 'deactivate'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    RoleMiddleware::allow(['admin']),
    CsrfMiddleware::class,
]);

$router->post('/usuarios/ativar', [UsuarioController::class, 'activate'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
    RoleMiddleware::allow(['admin']),
    CsrfMiddleware::class,
]);

$router->get('/configuracoes', [ConfiguracaoController::class, 'index'], [
    AuthMiddleware::class,
    TenantMiddleware::class,
]);
