<?php
require_once 'config.php';
checkAuth(['admin']);

$controller = new \App\Controllers\UserController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'save':
        \App\Services\AuthService::checkPermission('usuarios', 'gerenciar');
        $controller->save();
        break;
    case 'toggle_status':
        \App\Services\AuthService::checkPermission('usuarios', 'gerenciar');
        $controller->toggle_status();
        break;
    case 'delete':
        \App\Services\AuthService::checkPermission('usuarios', 'gerenciar');
        $controller->delete();
        break;
    default:
        \App\Services\AuthService::checkPermission('usuarios', 'visualizar');
        $controller->index();
        break;
}
exit;
