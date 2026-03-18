<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\VendaFiadoController();

$action = $_GET['action'] ?? 'index';

switch($action) {
    case 'fetch':
        $controller->fetch();
        break;
    case 'get_details':
        $controller->get_details();
        break;
    case 'pagar':
        $controller->pagar();
        break;
    case 'excel':
        $controller->excel();
        break;
    default:
        $controller->index();
        break;
}
exit;
