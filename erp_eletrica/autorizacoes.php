<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\AuthorizationController();

$action = $_GET['action'] ?? 'generate';

switch($action) {
    case 'generate':
        $controller->generate();
        break;
    default:
        exit;
}
exit;
