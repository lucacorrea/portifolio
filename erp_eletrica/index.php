<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\DashboardController();
$controller->index();
