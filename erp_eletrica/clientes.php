<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\ClientController();

$action = $_GET['action'] ?? 'index';

if (method_exists($controller, $action)) {
    $controller->$action();
} else {
    $controller->index();
}
exit;