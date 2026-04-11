<?php
// transferencias.php - Ponto de entrada do módulo B2B de Transferências de Estoque
require_once 'config.php';
checkAuth();

$controller = new \App\Controllers\TransferenciasController();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'nova_solicitacao':
            $controller->novaSolicitacao();
            break;
        case 'nova_transferencia':
            $controller->novaTransferencia();
            break;
        case 'aprovar_solicitacao':
            $controller->aprovarSolicitacao();
            break;
        case 'confirmar_recebimento':
            $controller->confirmarRecebimento();
            break;
        case 'relatar_problema':
            $controller->relatarProblema();
            break;
        case 'resolver_problema':
            $controller->resolverProblema();
            break;
        default:
            $controller->index();
    }
} else {
    switch ($action) {
        case 'get_items':
            $controller->getTransferItems();
            break;
        default:
            $controller->index();
    }
}
exit;
