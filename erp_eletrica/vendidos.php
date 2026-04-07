<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\SalesController();

$action = $_GET['action'] ?? 'sold_list';

switch ($action) {
    case 'sold_search':
    case 'get_sale_detail':
        \App\Services\AuthService::checkPermission('vendas', 'visualizar');
        $controller->$action();
        break;
    case 'exchange_item':
        \App\Services\AuthService::checkPermission('vendas', 'editar');
        $controller->exchange_item();
        break;
    case 'cancel_sale':
        \App\Services\AuthService::checkPermission('vendas', 'excluir');
        $controller->cancel_sale();
        break;
    default:
        \App\Services\AuthService::checkPermission('vendas', 'visualizar');
        $controller->sold_list();
        break;
}
exit;
