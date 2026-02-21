<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\FinancialController();

$action = $_GET['action'] ?? 'index';

if ($action == 'pay') {
    $controller->pay();
} else {
    $controller->index();
}
exit;