<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\FinancialController();

$action = $_GET['action'] ?? 'index';

if ($action == 'dre') {
    $controller->dre();
} elseif ($action == 'abcCurve') {
    $controller->abcCurve();
} elseif ($action == 'delinquency') {
    $controller->delinquency();
} else {
    $controller->index();
}
exit;