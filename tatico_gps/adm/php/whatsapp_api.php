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
        // Simulação de resposta enquanto o serviço Node não está configurado
        echo json_encode([
            'connected' => false,
            'number' => null,
            'message' => 'Serviço Node.js não iniciado'
        ]);
        break;

    case 'qrcode':
        // Aqui o PHP solicitaria o QR Code (em base64 ou link) para o Node.js
        // Por agora, retorna uma imagem de instrução ou erro
        header('Content-Type: image/png');
        // readfile('../../assets/img/illustrations/qr-placeholder.png');
        break;

    case 'logout':
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Ação inválida']);
        break;
}
