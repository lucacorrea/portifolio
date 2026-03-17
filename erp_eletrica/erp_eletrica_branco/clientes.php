<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\ClientController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'save':
        $permissionAction = isset($_POST['id']) && !empty($_POST['id']) ? 'editar' : 'criar';
        \App\Services\AuthService::checkPermission('clientes', $permissionAction);
        $controller->save();
        break;
    case 'profile':
        \App\Services\AuthService::checkPermission('clientes', 'visualizar');
        $controller->profile();
        break;
    default:
        \App\Services\AuthService::checkPermission('clientes', 'visualizar');
        $controller->index();
        break;
}
exit;