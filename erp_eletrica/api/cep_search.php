<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// Ensure user is authenticated
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado.']);
    exit;
}

$cep = $_GET['cep'] ?? '';
$cep = preg_replace('/\D/', '', $cep);

if (empty($cep) || strlen($cep) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'CEP inválido.']);
    exit;
}

$url = "https://brasilapi.com.br/api/cep/v1/" . $cep;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha de conexão com a API de CEP.', 'details' => $error]);
    exit;
}

http_response_code($httpCode);
echo $response;
