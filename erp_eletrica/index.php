<?php
require_once 'config.php';
checkAuth();
if (($_SESSION['is_temporary'] ?? false)) {
    header('Location: caixa.php');
    exit;
}
if (($_SESSION['usuario_nivel'] ?? '') === 'vendedor') {
    header('Location: pre_vendas.php');
    exit;
}
if (($_SESSION['usuario_nivel'] ?? '') === 'gerente') {
    header('Location: vendas.php');
    exit;
}

$controller = new \App\Controllers\DashboardController();
if (isset($_GET['action']) && $_GET['action'] === 'getRealtimeStats') {
    $controller->getRealtimeStats();
} else {
    $controller->index();
}
