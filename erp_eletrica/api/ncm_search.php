<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// Ensure user is authenticated
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado.']);
    exit;
}

$search = $_GET['search'] ?? '';

if (empty($search)) {
    http_response_code(400);
    echo json_encode(['error' => 'Termo de pesquisa é obrigatório.']);
    exit;
}

// Proxies the request to Brasil API to avoid CORS or direct frontend queries
$url = "https://brasilapi.com.br/api/ncm/v1?search=" . urlencode($search);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local envs, though BrasilAPI has valid SSL.

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha de conexão com a API de NCM.', 'details' => $error]);
    exit;
}

http_response_code($httpCode);
echo $response;
