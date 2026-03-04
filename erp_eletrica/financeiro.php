<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\FinancialController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'dre':
        \App\Services\AuthService::checkPermission('financeiro', 'dre');
        $controller->dre();
        break;
    case 'abcCurve':
    case 'delinquency':
        \App\Services\AuthService::checkPermission('financeiro', 'visualizar');
        $controller->$action();
        break;
    default:
        \App\Services\AuthService::checkPermission('financeiro', 'visualizar');
        $controller->index();
        break;
}
exit;