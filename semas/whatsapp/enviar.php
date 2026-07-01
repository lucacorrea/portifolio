<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/WhatsappService.php';

semas_whatsapp_require_auth(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    semas_whatsapp_json(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') === false) {
    semas_whatsapp_json(['sucesso' => false, 'mensagem' => 'Content-Type deve ser application/json.'], 415);
}

$raw = file_get_contents('php://input');
$input = json_decode((string)$raw, true);
if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
    semas_whatsapp_json(['sucesso' => false, 'mensagem' => 'JSON inválido.'], 400);
}

if (!semas_whatsapp_validate_csrf($input['csrf_token'] ?? null)) {
    semas_whatsapp_json(['sucesso' => false, 'mensagem' => 'Token CSRF inválido.'], 403);
}

if (!semas_whatsapp_check_rate_limit()) {
    semas_whatsapp_json(['sucesso' => false, 'mensagem' => 'Limite temporário de envios atingido. Aguarde alguns minutos.'], 429);
}

$telefone = trim((string)($input['telefone'] ?? ''));
$mensagem = trim((string)($input['mensagem'] ?? ''));

if ($telefone === '' || $mensagem === '') {
    semas_whatsapp_json(['sucesso' => false, 'mensagem' => 'Telefone e mensagem são obrigatórios.'], 422);
}

$service = new WhatsappService();
$retorno = $service->enviarTexto($telefone, $mensagem);

if ($retorno['sucesso']) {
    semas_whatsapp_json([
        'sucesso' => true,
        'mensagem' => 'Mensagem enviada com sucesso.',
    ], 200);
}

$http = (int)$retorno['http_code'];
if ($http < 400 || $http > 599) {
    $http = 502;
}

semas_whatsapp_json([
    'sucesso' => false,
    'mensagem' => $retorno['mensagem'],
], $http);
