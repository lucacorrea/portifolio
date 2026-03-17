<?php
require_once __DIR__ . '/config.php';
checkAuth();

$controller = new \App\Controllers\FiscalController();
$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'emit':
        \App\Services\AuthService::checkPermission('fiscal', 'emitir_nota');
        $controller->emit();
        break;
    case 'settings':
    case 'config':
        \App\Services\AuthService::checkPermission('fiscal', 'configurar');
        $controller->settings();
        break;
    case 'diagnostic':
        \App\Services\AuthService::checkPermission('fiscal', 'configurar');
        $controller->diagnostic();
        break;
    case 'test_connection':
        \App\Services\AuthService::checkPermission('fiscal', 'configurar');
        $controller->test_connection();
        break;
    default:
        \App\Services\AuthService::checkPermission('fiscal', 'emitir_nota');
        $controller->index();
        break;
}
exit;
