<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\VendaFiadoController();

$action = $_GET['action'] ?? 'index';

switch($action) {
    case 'pagar':
        $controller->pagar();
        break;
    case 'get_items':
        $controller->get_items();
        break;
    default:
        $controller->index();
        break;
}
exit;
