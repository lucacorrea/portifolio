<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\SalesController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'search':
    case 'list_recent':
    case 'get_sale':
    case 'list_admins':
        \App\Services\AuthService::checkPermission('vendas', 'visualizar');
        $controller->$action();
        break;
    case 'checkout':
    case 'authorize_discount':
        \App\Services\AuthService::checkPermission('vendas', 'criar');
        $controller->$action();
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
