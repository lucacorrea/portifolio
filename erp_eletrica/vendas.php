<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\SalesController();

$action = $_GET['action'] ?? 'index';

if ($action == 'search') {
    $controller->search();
} elseif ($action == 'checkout') {
    $controller->checkout();
} elseif ($action == 'list_recent') {
    $controller->list_recent();
} elseif ($action == 'get_sale') {
    $controller->get_sale();
} elseif ($action == 'cancel_sale') {
    $controller->cancel_sale();
} else {
    $controller->index();
}
exit;
