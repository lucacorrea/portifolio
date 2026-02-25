<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\OSController();

$action = $_GET['action'] ?? 'index';

switch($action) {
    case 'save':
        $permissionAction = isset($_POST['id']) && !empty($_POST['id']) ? 'editar' : 'criar';
        \App\Services\AuthService::checkPermission('os', $permissionAction);
        $controller->save();
        break;
    case 'view':
        \App\Services\AuthService::checkPermission('os', 'visualizar');
        $controller->view($_GET['id']);
        break;
    default:
        \App\Services\AuthService::checkPermission('os', 'visualizar');
        $controller->index();
        break;
}
exit;