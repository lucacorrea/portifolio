<?php
require_once 'config.php';
checkAuth(['admin', 'master']);

$controller = new \App\Controllers\ReportsController();
$controller->index();
exit;
