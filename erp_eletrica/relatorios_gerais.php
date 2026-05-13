<?php
require_once 'config.php';
checkAuth(['admin', 'gerente']);

$controller = new \App\Controllers\ReportsController();
$controller->index();
exit;
