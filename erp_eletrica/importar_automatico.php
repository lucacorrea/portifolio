<?php
require_once 'config.php';
checkAuth();

// Apenas Gerente ou Master podem acessar
if (!in_array($_SESSION['usuario_nivel'], ['gerente', 'master', 'admin'])) {
    die("Acesso negado. Apenas gestores podem realizar importação automática.");
}

$controller = new \App\Controllers\ImportacaoAutomaticaController();

$action = $_GET['action'] ?? 'index';

switch($action) {
    case 'sincronizar':
        $controller->sincronizar();
        break;
    case 'visualizar_produtos':
        $controller->visualizar_produtos();
        break;
    case 'processar_entrada':
        $controller->processar_entrada();
        break;
    default:
        $controller->index();
        break;
}
exit;
