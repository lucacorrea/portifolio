<?php
require_once 'config.php';
checkAuth(['admin', 'gerente']);

$controller = new \App\Controllers\PurchaseController();

$action = $_GET['action'] ?? 'index';

if ($action == 'process') {
    $controller->process();
} else {
    $controller->index();
}
exit;
