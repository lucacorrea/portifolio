<?php
/**
 * ERP Elétrica — Endpoint de Sincronização Offline/Online
 * 
 * Este endpoint processa:
 *  - Heartbeat (verificação de conectividade)
 *  - Cache de produtos e clientes para uso offline
 *  - Sincronização de operações realizadas offline
 */
require_once 'config.php';

// Heartbeat não precisa de autenticação completa
$action = $_GET['action'] ?? '';

if ($action === 'heartbeat') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode([
        'status' => 'online',
        'timestamp' => date('c'),
        'server' => 'hostinger'
    ]);
    exit;
}

// Demais ações precisam de autenticação
checkAuth();

$controller = new \App\Controllers\SyncController();

switch ($action) {
    case 'cache_products':
        $controller->cacheProducts();
        break;
    case 'cache_clients':
        $controller->cacheClients();
        break;
    case 'sync_batch':
        $controller->syncBatch();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Ação inválida']);
        break;
}
exit;
