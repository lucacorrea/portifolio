<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\VendaFiadoController();

$action = $_GET['action'] ?? 'index';

switch($action) {
    case 'pagar':
        $controller->pagar();
        break;
    default:
        $controller->index();
        break;
}
exit;
