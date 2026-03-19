<?php
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\StockBaixoController();
$controller->index();
exit;
