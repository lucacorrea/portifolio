<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\ImportacaoSefazController();

$action = $_GET['action'] ?? 'index';

switch($action) {
    case 'upload':
        $controller->upload();
        break;
    case 'confirmar':
        $controller->confirmar();
        break;
    default:
        $controller->index();
        break;
}
exit;
