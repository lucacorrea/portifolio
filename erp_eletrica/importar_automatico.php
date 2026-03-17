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
        if (!($_SESSION['is_matriz'] ?? false)) die("Acesso negado. Apenas o administrador da Matriz pode configurar o certificado global.");
        $configController = new \App\Controllers\SefazConfigController();
        $configController->index();
        break;
    case 'config_save':
        if (!($_SESSION['is_matriz'] ?? false)) die("Acesso negado.");
        $configController = new \App\Controllers\SefazConfigController();
        $configController->save();
        break;
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
    default:
        $controller->index();
        break;
}
exit;
