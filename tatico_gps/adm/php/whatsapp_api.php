<?php
/**
 * adm/php/whatsapp_api.php
 * 
 * Este arquivo serve como uma ponte (bridge) entre o frontend PHP
 * e o serviço Node.js (Evolution API ou bridge customizada).
 */

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Em um cenário real, estas variáveis viriam de um arquivo de configuração (.env ou banco)
$node_api_url = 'http://localhost:8080'; // URL onde o serviço Node.js estaria rodando
$instance_name = 'tatico_gps';

switch ($action) {
    case 'status':
        try {
            $response = @file_get_contents($node_api_url . '/status');
            if ($response === false) {
                throw new Exception("Bridge Offline");
            }
            echo $response;
        } catch (Exception $e) {
            echo json_encode([
                'connected' => false,
                'status' => 'offline',
                'message' => $e->getMessage()
            ]);
        }
        break;

    case 'qrcode':
        try {
            $response = @file_get_contents($node_api_url . '/qrcode');
            if ($response === false) {
                throw new Exception("Bridge Offline");
            }
            echo $response;
        } catch (Exception $e) {
            echo json_encode(['qr' => null, 'error' => $e->getMessage()]);
        }
        break;

    case 'logout':
        @file_get_contents($node_api_url . '/logout');
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Ação inválida']);
        break;
}
