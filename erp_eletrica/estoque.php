<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\InventoryController();

$action = $_GET['action'] ?? 'index';

if ($action == 'save') {
    $controller->save();
} elseif ($action == 'move') {
    $controller->move();
} else {
    $controller->index();
}
exit;