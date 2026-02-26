<?php
require_once 'config.php';
checkAuth();
if (($_SESSION['usuario_nivel'] ?? '') === 'vendedor') {
    header('Location: pre_vendas.php');
    exit;
}
if (($_SESSION['usuario_nivel'] ?? '') === 'gerente') {
    header('Location: vendas.php');
    exit;
}

$controller = new \App\Controllers\DashboardController();
$controller->index();
