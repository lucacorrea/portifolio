<?php
require_once 'config.php';
\App\Services\AuthService::check(['master']);

$controller = new \App\Controllers\MasterController();
$action = $_GET['action'] ?? 'index';

if (method_exists($controller, $action)) {
    $controller->$action();
} else {
    $controller->index();
}
exit;
