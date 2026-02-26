<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\InventoryController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'save':
        $permissionAction = isset($_POST['id']) && !empty($_POST['id']) ? 'editar' : 'criar';
        \App\Services\AuthService::checkPermission('estoque', $permissionAction);
        $controller->save();
        break;
    case 'move':
        \App\Services\AuthService::checkPermission('estoque', 'editar');
        $controller->move();
        break;
    default:
        \App\Services\AuthService::checkPermission('estoque', 'visualizar');
        $controller->index();
        break;
}
exit;