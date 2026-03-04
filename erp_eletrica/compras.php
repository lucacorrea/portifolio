<?php
require_once 'config.php';
checkAuth(['admin', 'gerente']);

$controller = new \App\Controllers\PurchaseController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'process':
        \App\Services\AuthService::checkPermission('compras', 'gerenciar');
        $controller->process();
        break;
    default:
        \App\Services\AuthService::checkPermission('compras', 'visualizar');
        $controller->index();
        break;
}
exit;
