<?php
require_once 'autoloader.php';
require_once 'config.php';

use App\Controllers\CentroCustoController;

$controller = new CentroCustoController();
$action = $_GET['action'] ?? 'index';

if ($action === 'criar') {
    $controller->criar();
} elseif ($action === 'lancar') {
    $controller->lancarCusto();
} elseif ($action === 'relatorioMensal') {
    $controller->relatorioMensal();
} elseif ($action === 'margemProdutos') {
    $controller->margemProdutos();
} elseif ($action === 'rentabilidade') {
    $controller->rentabilidadeFilial();
} else {
    $controller->index();
}
