<?php
/**
 * Envio Manual de Mensagem via WhatsApp - Tático GPS
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/functions.php';

// Validar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método inválido.']);
    exit;
}

$clienteId = (int)($_POST['cliente_id'] ?? 0);
$mensagem = trim($_POST['mensagem'] ?? '');
$tipo = $_POST['tipo'] ?? 'manual';

if ($clienteId <= 0 || empty($mensagem)) {
    echo json_encode(['ok' => false, 'error' => 'Dados incompletos. Selecione um cliente e digite a mensagem.']);
    exit;
}

// Buscar telefone do cliente
$stmt = $pdo->prepare("SELECT nome, telefone, whatsapp_principal FROM clientes WHERE id = :id");
$stmt->execute([':id' => $clienteId]);
$cliente = $stmt->fetch();

if (!$cliente) {
    echo json_encode(['ok' => false, 'error' => 'Cliente não encontrado.']);
    exit;
}

$telefone = $cliente['whatsapp_principal'] ?: $cliente['telefone'];
if (empty($telefone)) {
    echo json_encode(['ok' => false, 'error' => 'Cliente não possui telefone cadastrado.']);
    exit;
}

// Enviar via Bridge
$retorno = enviarMensagemWhatsApp($telefone, $mensagem);

// Registrar log
$statusLog = $retorno['ok'] ? 'enviado' : 'falhou';
$respostaLog = ($retorno['ok'] ? 'Sucesso' : ($retorno['error'] ?? 'Erro no envio')) . " | Tipo: $tipo (Manual)";

registrarLogEnvio($pdo, $clienteId, $telefone, $mensagem, $statusLog, $respostaLog);

if ($retorno['ok']) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => $retorno['error'] ?? 'Não foi possível enviar a mensagem.']);
}
