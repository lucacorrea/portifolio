<?php
/**
 * Funções compartilhadas para facilitar o envio via WhatsApp Bridge
 */

function enviarMensagemWhatsApp(string $telefone, string $mensagem): array {
    $node_api_url = 'https://smthcoari.cloud/send';
    
    // Garantir que o telefone está no formato correto
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) === 10 || strlen($telefone) === 11) {
        $telefone = '55' . $telefone;
    }

    $payload = json_encode([
        'number' => $telefone,
        'text' => $mensagem
    ]);

    $ch = curl_init($node_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['ok' => false, 'error' => "Erro de conexão: " . $error];
    }

    $response = json_decode($result, true);
    if ($http_code >= 200 && $http_code < 300) {
        return ['ok' => true, 'response' => $response];
    }

    return ['ok' => false, 'error' => "Erro no Servidor ({$http_code}): " . ($response['error'] ?? 'Falha ao processar envio')];
}

function registrarLogEnvio(PDO $pdo, int $clienteId, string $telefone, string $mensagem, string $status, string $resposta = ''): void {
    $sql = "INSERT INTO whatsapp_envios (cliente_id, telefone, mensagem, status_envio, resposta_api) 
            VALUES (:id, :tel, :msg, :status, :resp)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $clienteId,
        ':tel' => $telefone,
        ':msg' => $mensagem,
        ':status' => $status,
        ':resp' => $resposta
    ]);
}
