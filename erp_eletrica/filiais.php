<?php
require_once 'config.php';
checkAuth(['admin']);

$controller = new \App\Controllers\BranchController();
$controller->index();
exit;
