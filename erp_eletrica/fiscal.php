<?php
require_once __DIR__ . '/config.php';
checkAuth();

$controller = new \App\Controllers\FiscalController();
$action = $_GET['action'] ?? 'index';

if (method_exists($controller, $action)) {
    $controller->$action();
} else {
    $controller->index();
}
