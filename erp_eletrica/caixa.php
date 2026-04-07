<?php
require_once 'autoloader.php';
require_once 'config.php';

use App\Controllers\CaixaController;

$controller = new CaixaController();
$action = $_GET['action'] ?? 'index';

if ($action === 'abrir') {
    $controller->abrir();
} elseif ($action === 'fechar') {
    $controller->fechar();
} elseif ($action === 'movimentar') {
    $controller->registrarMovimentacao();
} elseif ($action === 'validate_code') {
    $controller->validate_code();
} elseif ($action === 'detalhes') {
    $controller->detalhes();
} else {
    $controller->index();
}
