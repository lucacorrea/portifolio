<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/WhatsappService.php';

semas_whatsapp_require_auth(true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    semas_whatsapp_json(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$service = new WhatsappService();
$retorno = $service->verificarConexao();
$dados = is_array($retorno['dados']) ? $retorno['dados'] : [];

semas_whatsapp_json([
    'sucesso' => $retorno['sucesso'],
    'mensagem' => $retorno['mensagem'],
    'status' => $dados['status'] ?? 'indisponivel',
    'conectado' => !empty($dados['conectado']),
    'numero' => $dados['numero'] ?? null,
], $retorno['sucesso'] ? 200 : 502);
