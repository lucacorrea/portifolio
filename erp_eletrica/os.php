<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\OSController();

$action = $_GET['action'] ?? 'index';

switch($action) {
    case 'save':
        $controller->save();
        break;
    case 'view':
        $controller->view($_GET['id']);
        break;
    default:
        $controller->index();
        break;
}
exit;