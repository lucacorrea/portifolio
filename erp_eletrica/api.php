<?php
require_once 'config.php';

$controller = new \App\Controllers\ApiController();
$action = $_GET['action'] ?? '';

if (method_exists($controller, $action)) {
    $controller->$action();
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Endpoint inválido']);
}
exit;
