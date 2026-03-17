<?php
require_once 'config.php';
checkAuth(['admin']);

$controller = new \App\Controllers\BranchController();
$action = $_GET['action'] ?? 'index';

if ($action == 'save') {
    $controller->save();
} else {
    $controller->index();
}
exit;
