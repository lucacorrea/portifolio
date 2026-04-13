<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\SalesController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'search':
    case 'search_clients':
    case 'list_recent':
    case 'get_sale':
    case 'list_admins':
    case 'check_client_completeness':
    case 'sold_list':
    case 'sold_search':
    case 'get_sale_detail':
    case 'sync_status':
        \App\Services\AuthService::checkPermission('vendas', 'visualizar');
        $controller->$action();
        break;
    case 'sync':
        \App\Services\AuthService::checkPermission('vendas', 'criar');
        $controller->$action();
        break;
    case 'checkout':
    case 'authorize_discount':
    case 'update_client_quick':
    case 'issue_nfce':
    case 'exchange_item':
        \App\Services\AuthService::checkPermission('vendas', 'criar');
        $controller->$action();
        break;
    case 'cancel_sale':
        \App\Services\AuthService::checkPermission('vendas', 'excluir');
        $controller->cancel_sale();
        break;
    case 'quick_register_client':
        $clientController = new \App\Controllers\ClientController();
        $clientController->quickSave();
        break;
    default:
        \App\Services\AuthService::checkPermission('vendas', 'visualizar');
        $controller->index();
        break;
}
exit;
