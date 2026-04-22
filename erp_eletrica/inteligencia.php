<?php
require_once 'autoloader.php';
require_once 'config.php';

use App\Controllers\InteligenciaComercialController;

$controller = new InteligenciaComercialController();
$action = $_GET['action'] ?? 'index';

if ($action === 'productivity') {
    $prodController = new \App\Controllers\ProductivityController();
    $prodController->index();
} elseif ($action === 'topProdutos') {
    $controller->topProdutos();
} elseif ($action === 'encalhados') {
    $controller->produtosEncalhados();
} elseif ($action === 'curvaABC') {
    $controller->curvaABC();
} elseif ($action === 'recalcularABC') {
    $controller->recalcularCurvaABC();
} elseif ($action === 'reposicao') {
    $controller->sugestaoReposicao();
} else {
    $controller->topProdutos();
}
