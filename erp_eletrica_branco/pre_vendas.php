<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\PreSaleController();

$action = $_GET['action'] ?? 'index';

switch($action) {
    case 'save':
        $controller->save();
        break;
    case 'get_by_code':
        $controller->get_by_code();
        break;
    case 'list_pending':
        $controller->list_pending();
        break;
    default:
        $controller->index();
        break;
}
exit;
