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
    default:
        \App\Services\AuthService::checkPermission('fiscal', 'emitir_nota');
        $controller->index();
        break;
}
exit;
