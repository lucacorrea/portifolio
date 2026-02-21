<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\ClientController();

if (isset($_GET['action']) && $_GET['action'] == 'save') {
    $controller->save();
} else {
    $controller->index();
}
exit;