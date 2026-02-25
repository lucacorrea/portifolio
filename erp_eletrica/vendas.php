<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\SalesController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'search':
    case 'list_recent':
    case 'get_sale':
        \App\Services\AuthService::checkPermission('vendas', 'visualizar');
        $controller->$action();
        break;
    case 'checkout':
        \App\Services\AuthService::checkPermission('vendas', 'criar');
        $controller->checkout();
        break;
    case 'cancel_sale':
        \App\Services\AuthService::checkPermission('vendas', 'excluir');
        $controller->cancel_sale();
        break;
    default:
        \App\Services\AuthService::checkPermission('vendas', 'visualizar');
        $controller->index();
        break;
}
exit;
