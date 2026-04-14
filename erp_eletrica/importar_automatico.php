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
    case 'config':
    case 'config_save':
        header("Location: configuracoes.php");
        exit;
    case 'sincronizar':
        $controller->sincronizar();
        break;
    case 'manifestar':
        $controller->manifestar();
        break;
    case 'visualizar_produtos':
        $controller->visualizar_produtos();
        break;
    case 'processar_entrada':
        $controller->processar_entrada();
        break;
    case 'baixar_xml':
        $controller->baixar_xml();
        break;
    case 'baixar_danfe':
        $controller->baixar_danfe();
        break;
    default:
        $controller->index();
        break;
}
exit;
