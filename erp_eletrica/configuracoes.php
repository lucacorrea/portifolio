<?php
require_once 'config.php';
checkAuth(['admin']);

$controller = new \App\Controllers\SettingController();

if (isset($_GET['action']) && $_GET['action'] == 'save') {
    $controller->save();
} else {
    $controller->index();
}
exit;
