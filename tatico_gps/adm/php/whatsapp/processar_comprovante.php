<?php
/**
 * Webhook para Processamento de Comprovantes via IA - Tático GPS
 * Recebe base64 do Bridge Node.js e valida usando Gemini AI.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/functions.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['media'], $input['sender'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

$sender = $input['sender'];
$mediaBase64 = $input['media'];
$mimeType = $input['mimeType'] ?? 'image/jpeg';
$messageId = $input['messageId'] ?? null;

try {
    // 1. Identificar o Cliente
    $stmt = $pdo->prepare("SELECT id, nome, mensalidade FROM clientes WHERE (telefone LIKE :tel OR whatsapp_principal LIKE :tel) LIMIT 1");
    // Limpar o sender para bater com o banco (ajuste conforme o formato salvo no BD)
    $telBusca = '%' . substr($sender, -8) . '%';
    $stmt->execute([':tel' => $telBusca]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        // Se não achar o cliente, salva como log genérico
        echo json_encode(['status' => 'cliente_nao_encontrado']);
        exit;
    }

    // 2. Buscar Chave do Gemini
    $stmtCfg = $pdo->query("SELECT gemini_api_key, empresa_nome FROM configuracoes_automacao ORDER BY id DESC LIMIT 1");
    $config = $stmtCfg->fetch();
    $apiKey = $config['gemini_api_key'] ?? '';

    if (empty($apiKey)) {
        // Sem chave? Apenas registra que recebeu um comprovante para conferência manual
        registrarPagamentoPendente($pdo, $cliente['id'], $messageId, "Comprovante recebido via WhatsApp (Pendente de IA)");
        echo json_encode(['status' => 'pendente_conferencia_manual']);
        exit;
    }

    // 3. Chamar Gemini AI para Validar
    $resultadoIA = validarComprovanteComGemini($apiKey, $mediaBase64, $mimeType, (float)$cliente['mensalidade']);

    if ($resultadoIA['valido']) {
        // 4. Se válido, dar baixa automática
        $valorPago = $resultadoIA['valor'] ?? (float)$cliente['mensalidade'];
        
        $pdo->beginTransaction();
        
        // Atualiza status do cliente
        $upd = $pdo->prepare("UPDATE clientes SET status = 'Ativo' WHERE id = :id");
        $upd->execute([':id' => $cliente['id']]);

        // Insere no histórico de pagamentos
        $ins = $pdo->prepare("INSERT INTO pagamentos (cliente_id, valor, status, mensagem_id, referencia_mes) VALUES (:cid, :v, 'Confirmado', :mid, :ref)");
        $ins->execute([
            ':cid' => $cliente['id'],
            ':v' => $valorPago,
            ':mid' => $messageId,
            ':ref' => date('m/Y')
        ]);

        $pdo->commit();

        // Enviar confirmação via WhatsApp
        $msgSucesso = "✅ *Pagamento Confirmado!*\n\nOlá {$cliente['nome']}, recebemos seu comprovante de R$ " . number_format($valorPago, 2, ',', '.') . ".\nSeu status já foi atualizado para ATIVO no sistema.\n\nObrigado!";
        enviarMensagemWhatsApp($sender, $msgSucesso);

        echo json_encode(['status' => 'sucesso', 'valor' => $valorPago]);
    } else {
        // Se a IA disser que não é um comprovante ou é inválido
        echo json_encode(['status' => 'recusado', 'motivo' => $resultadoIA['motivo']]);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Função que chama a API do Gemini
 */
function validarComprovanteComGemini($key, $base64, $mime, $valorEsperado) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $key;

    $prompt = "Analise este arquivo. Ele deve ser um comprovante de transferência bancária ou PIX. 
    Verifique se o status do pagamento é 'Efetuado', 'Concluído' ou 'Sucesso'. 
    Verifique se o valor é próximo de R$ " . number_format($valorEsperado, 2, ',', '.') . ".
    Responda APENAS em formato JSON com os campos: 
    'valido' (boolean), 'valor' (float), 'data' (string), 'motivo' (string se invalido).";

    $payload = [
        "contents" => [[
            "parts" => [
                ["text" => $prompt],
                ["inline_data" => ["mime_type" => $mime, "data" => $base64]]
            ]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    $textResponse = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    // Limpar markdown do JSON se a IA mandar
    $textResponse = str_replace(['```json', '```'], '', $textResponse);
    
    $json = json_decode(trim($textResponse), true);
    
    return $json ?: ['valido' => false, 'motivo' => 'Falha na análise da IA'];
}

function registrarPagamentoPendente($pdo, $clienteId, $msgId, $obs) {
    $ins = $pdo->prepare("INSERT INTO pagamentos (cliente_id, valor, status, mensagem_id, observacoes) VALUES (:cid, 0, 'Pendente', :mid, :obs)");
    $ins->execute([':cid' => $clienteId, ':mid' => $msgId, ':obs' => $obs]);
}
