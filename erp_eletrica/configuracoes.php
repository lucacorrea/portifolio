<?php
require_once 'config.php';
checkAuth(['admin']);

$controller = new \App\Controllers\SettingController();

if (isset($_GET['action'])) {
    if ($_GET['action'] == 'saveMatriz') {
        $controller->saveMatriz();
    } elseif ($_GET['action'] == 'saveFilial') {
        $controller->saveFilial();
    } else {
        $controller->index();
    }
} else {
    $controller->index();
}
exit;
