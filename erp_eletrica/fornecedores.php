<?php
require_once 'config.php';
checkAuth(['admin', 'gerente']);

$controller = new \App\Controllers\SupplierController();

$action = $_GET['action'] ?? 'index';

if ($action == 'save') {
    $controller->save();
} else {
    $controller->index();
}
exit;
