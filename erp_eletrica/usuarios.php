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
    default:
        \App\Services\AuthService::checkPermission('usuarios', 'visualizar');
        $controller->index();
        break;
}
exit;
