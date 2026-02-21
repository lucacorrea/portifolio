<?php
require_once 'config.php';
checkAuth(['admin']);

$controller = new \App\Controllers\UserController();

$action = $_GET['action'] ?? 'index';

if ($action == 'save') {
    $controller->save();
} else {
    $controller->index();
}
exit;
