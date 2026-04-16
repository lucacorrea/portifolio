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
    case 'iniciar_analise':
        $controller->iniciar_analise();
        break;
    case 'listar_analise':
        $controller->listar_analise();
        break;
    case 'vincular_item':
        $controller->vincular_item();
        break;
    case 'cadastrar_e_vincular':
        $controller->cadastrar_e_vincular();
        break;
    case 'finalizar_importacao':
        $controller->finalizar_importacao();
        break;
    case 'baixar_xml':
        $controller->baixar_xml();
        break;
    case 'baixar_danfe':
        $controller->baixar_danfe();
        break;
    case 'save_marker':
        $controller->save_marker();
        break;
    default:
        $controller->index();
        break;
}
exit;
