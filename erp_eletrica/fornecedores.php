<?php
require_once 'config.php';
checkAuth(['admin', 'gerente']);

$controller = new \App\Controllers\SupplierController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'save':
        \App\Services\AuthService::checkPermission('fornecedores', 'gerenciar');
        $controller->save();
        break;
    default:
        \App\Services\AuthService::checkPermission('fornecedores', 'visualizar');
        $controller->index();
        break;
}
exit;
