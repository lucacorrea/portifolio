<?php

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\LoginController;
use App\Controllers\DashboardController;

$router = new Router();

// Auth Routes
$router->get('/', [LoginController::class, 'index']);
$router->get('/login', [LoginController::class, 'index']);
$router->post('/login/auth', [LoginController::class, 'auth']);
$router->get('/logout', [LoginController::class, 'logout']);

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index']);

// Products / Inventory
// Products / Inventory
$router->get('/produtos', [App\Controllers\ProdutosController::class, 'index']);
$router->get('/produtos/create', [App\Controllers\ProdutosController::class, 'create']);
$router->post('/produtos/create', [App\Controllers\ProdutosController::class, 'create']);
$router->get('/produtos/edit/{id}', [App\Controllers\ProdutosController::class, 'edit']);
$router->post('/produtos/edit/{id}', [App\Controllers\ProdutosController::class, 'edit']);

// Inventory
$router->get('/estoque', [App\Controllers\EstoqueController::class, 'index']);
$router->get('/estoque/movimentacao', [App\Controllers\EstoqueController::class, 'movimentacao']);
$router->post('/estoque/movimentacao', [App\Controllers\EstoqueController::class, 'movimentacao']);

// Customers
$router->get('/clientes', [App\Controllers\ClientesController::class, 'index']);
$router->get('/clientes/create', [App\Controllers\ClientesController::class, 'create']);
$router->post('/clientes/create', [App\Controllers\ClientesController::class, 'create']);
$router->get('/clientes/edit/{id}', [App\Controllers\ClientesController::class, 'edit']);
$router->post('/clientes/edit/{id}', [App\Controllers\ClientesController::class, 'edit']);

// Sales
$router->get('/vendas', [App\Controllers\VendasController::class, 'index']);
$router->get('/vendas/pdv', [App\Controllers\VendasController::class, 'create']);
$router->post('/vendas/store', [App\Controllers\VendasController::class, 'store']);
$router->get('/vendas/search', [App\Controllers\VendasController::class, 'searchProducts']);

// Branches (Filiais)
$router->get('/filiais', [App\Controllers\FiliaisController::class, 'index']);






// Routes will be added as we migrate controllers

return $router;
