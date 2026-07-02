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
    case 'replicate_catalog':
        \App\Services\AuthService::checkPermission('estoque', 'editar');
        $controller->replicateCatalog();
        break;
    case 'move':
        \App\Services\AuthService::checkPermission('estoque', 'editar');
        $controller->move();
        break;
    case 'delete':
        \App\Services\AuthService::checkPermission('estoque', 'excluir');
        $controller->delete();
        break;
    case 'movimentacoes':
        \App\Services\AuthService::checkPermission('estoque', 'visualizar');
        $controller->movimentacoes();
        break;
    case 'problems':
        \App\Services\AuthService::checkPermission('estoque', 'visualizar');
        $controller->problems();
        break;
    case 'stock_by_unit':
        \App\Services\AuthService::checkPermission('estoque', 'visualizar');
        $controller->stockByUnit();
        break;
    case 'save_problem':
        \App\Services\AuthService::checkPermission('estoque', 'editar');
        $controller->saveProblem();
        break;
    case 'update_problem_status':
        \App\Services\AuthService::checkPermission('estoque', 'editar');
        $controller->updateProblemStatus();
        break;
    case 'delete_problem':
        \App\Services\AuthService::checkPermission('estoque', 'excluir');
        $controller->deleteProblem();
        break;
    default:
        \App\Services\AuthService::checkPermission('estoque', 'visualizar');
        $controller->index();
        break;
}
exit;
