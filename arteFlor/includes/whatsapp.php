<?php
require_once __DIR__ . '/orders.php';

function integration_setting(string $key, ?string $default = null): ?string
{
    try {
        $statement = db()->prepare('SELECT valor FROM configuracoes_integracao WHERE chave = :chave LIMIT 1');
        $statement->execute(['chave' => $key]);
        $row = $statement->fetch();

        return $row ? (string) $row['valor'] : $default;
    } catch (Throwable $error) {
        error_log('[ArteFlor][integration-setting] ' . $error->getMessage());
        return $default;
    }
}

function integration_setting_set(string $key, ?string $value, string $description = '', bool $secret = false, ?int $adminId = null): void
{
    db()->prepare(
        'INSERT INTO configuracoes_integracao (chave, valor, descricao, secreto, atualizado_por)
         VALUES (:chave, :valor, :descricao, :secreto, :atualizado_por)
         ON DUPLICATE KEY UPDATE
            valor = VALUES(valor),
            descricao = VALUES(descricao),
            secreto = VALUES(secreto),
            atualizado_por = VALUES(atualizado_por),
            atualizado_em = CURRENT_TIMESTAMP'
    )->execute([
        'chave' => $key,
        'valor' => $value,
        'descricao' => $description !== '' ? $description : null,
        'secreto' => $secret ? 1 : 0,
        'atualizado_por' => $adminId ?: null,
    ]);
}

function whatsapp_default_message(): string
{
    return "Olá, {{cliente}}!\n\nObrigada pela sua compra na Arte&Flor.\n\nRecebemos o seu pedido {{codigo}}.\n\nResumo do pedido:\n{{itens}}\n\nTotal: {{total}}\nPagamento: {{forma_pagamento}}\nStatus: {{status}}\n\nRecebimento: {{recebimento}}\n{{endereco}}\n\nVocê pode acompanhar seu pedido pelo sistema:\n{{link_pedido}}\n\nCom carinho,\nArte&Flor";
}

function whatsapp_config(): array
{
    $tokenFromLocal = defined('WHATSAPP_CLOUD_API_TOKEN') ? (string) WHATSAPP_CLOUD_API_TOKEN : '';
    $phoneIdFromLocal = defined('WHATSAPP_PHONE_NUMBER_ID') ? (string) WHATSAPP_PHONE_NUMBER_ID : '';
    $versionFromLocal = defined('WHATSAPP_API_VERSION') ? (string) WHATSAPP_API_VERSION : '';
    $twilioSidFromLocal = defined('TWILIO_ACCOUNT_SID') ? (string) TWILIO_ACCOUNT_SID : '';
    $twilioTokenFromLocal = defined('TWILIO_AUTH_TOKEN') ? (string) TWILIO_AUTH_TOKEN : '';
    $twilioFromLocal = defined('TWILIO_WHATSAPP_FROM') ? (string) TWILIO_WHATSAPP_FROM : '';

    return [
        'pix_key' => integration_setting('pix_key', 'arteflor@pix.demo'),
        'pix_receiver_name' => integration_setting('pix_receiver_name', 'Arte&Flor'),
        'pix_instructions' => integration_setting('pix_instructions', 'Pix manual: confirme o pagamento no painel administrativo antes do preparo.'),
        'whatsapp_enabled' => integration_setting('whatsapp_enabled', '0') === '1',
        'whatsapp_mode' => integration_setting('whatsapp_mode', 'simulacao') ?: 'simulacao',
        'whatsapp_phone_number_id' => $phoneIdFromLocal !== '' ? $phoneIdFromLocal : (integration_setting('whatsapp_phone_number_id', '') ?: ''),
        'whatsapp_business_token' => $tokenFromLocal !== '' ? $tokenFromLocal : (integration_setting('whatsapp_business_token', '') ?: ''),
        'whatsapp_api_version' => $versionFromLocal !== '' ? $versionFromLocal : (integration_setting('whatsapp_api_version', 'v21.0') ?: 'v21.0'),
        'whatsapp_template_name' => integration_setting('whatsapp_template_name', '') ?: '',
        'whatsapp_template_language' => integration_setting('whatsapp_template_language', 'pt_BR') ?: 'pt_BR',
        'whatsapp_message_after_order' => integration_setting('whatsapp_message_after_order', whatsapp_default_message()) ?: whatsapp_default_message(),
        'whatsapp_company_number' => integration_setting('whatsapp_company_number', '') ?: '',
        'whatsapp_send_after_order' => integration_setting('whatsapp_send_after_order', '1') === '1',
        'whatsapp_send_on_status_change' => integration_setting('whatsapp_send_on_status_change', '0') === '1',
        'twilio_account_sid' => $twilioSidFromLocal !== '' ? $twilioSidFromLocal : (integration_setting('twilio_account_sid', '') ?: ''),
        'twilio_auth_token' => $twilioTokenFromLocal !== '' ? $twilioTokenFromLocal : (integration_setting('twilio_auth_token', '') ?: ''),
        'twilio_whatsapp_from' => $twilioFromLocal !== '' ? $twilioFromLocal : (integration_setting('twilio_whatsapp_from', '') ?: ''),
        'twilio_content_sid' => integration_setting('twilio_content_sid', '') ?: '',
    ];
}

function whatsapp_is_enabled(): bool
{
    $config = whatsapp_config();

    return (bool) $config['whatsapp_enabled'];
}

function whatsapp_save_settings(array $input, ?int $adminId = null): void
{
    $enabled = !empty($input['whatsapp_enabled']) ? '1' : '0';
    $sendAfterOrder = !empty($input['whatsapp_send_after_order']) ? '1' : '0';
    $sendOnStatusChange = !empty($input['whatsapp_send_on_status_change']) ? '1' : '0';
    $mode = (string) ($input['whatsapp_mode'] ?? 'simulacao');
    if (!in_array($mode, ['simulacao', 'cloud_api', 'twilio'], true)) {
        $mode = 'simulacao';
    }

    integration_setting_set('pix_key', order_clean_text($input['pix_key'] ?? '', 180), 'Chave Pix manual', false, $adminId);
    integration_setting_set('pix_receiver_name', order_clean_text($input['pix_receiver_name'] ?? '', 180), 'Nome do recebedor Pix manual', false, $adminId);
    integration_setting_set('pix_instructions', order_clean_text($input['pix_instructions'] ?? '', 500), 'Instruções Pix manual', false, $adminId);
    integration_setting_set('whatsapp_enabled', $enabled, 'Ativa notificações WhatsApp pós-compra', false, $adminId);
    integration_setting_set('whatsapp_mode', $mode, 'Modo da integração WhatsApp', false, $adminId);
    integration_setting_set('whatsapp_phone_number_id', order_clean_text($input['whatsapp_phone_number_id'] ?? '', 120), 'Phone Number ID da WhatsApp Cloud API', false, $adminId);
    integration_setting_set('whatsapp_api_version', order_clean_text($input['whatsapp_api_version'] ?? 'v21.0', 20), 'Versão da WhatsApp Cloud API', false, $adminId);
    integration_setting_set('whatsapp_template_name', order_clean_text($input['whatsapp_template_name'] ?? '', 120), 'Template WhatsApp opcional', false, $adminId);
    integration_setting_set('whatsapp_template_language', order_clean_text($input['whatsapp_template_language'] ?? 'pt_BR', 20), 'Idioma do template WhatsApp', false, $adminId);
    integration_setting_set('whatsapp_message_after_order', trim((string) ($input['whatsapp_message_after_order'] ?? whatsapp_default_message())), 'Mensagem editável após pedido', false, $adminId);
    integration_setting_set('whatsapp_company_number', order_clean_text($input['whatsapp_company_number'] ?? '', 40), 'Número comercial da empresa', false, $adminId);
    integration_setting_set('whatsapp_send_after_order', $sendAfterOrder, 'Enviar WhatsApp ao criar pedido', false, $adminId);
    integration_setting_set('whatsapp_send_on_status_change', $sendOnStatusChange, 'Enviar WhatsApp ao mudar status', false, $adminId);
    integration_setting_set('twilio_account_sid', order_clean_text($input['twilio_account_sid'] ?? '', 80), 'Twilio Account SID', false, $adminId);
    integration_setting_set('twilio_whatsapp_from', order_clean_text($input['twilio_whatsapp_from'] ?? '', 60), 'Remetente WhatsApp Twilio', false, $adminId);
    integration_setting_set('twilio_content_sid', order_clean_text($input['twilio_content_sid'] ?? '', 80), 'Twilio Content SID para template aprovado', false, $adminId);

    $token = trim((string) ($input['whatsapp_business_token'] ?? ''));
    if ($token !== '') {
        integration_setting_set('whatsapp_business_token', $token, 'Token secreto da WhatsApp Cloud API', true, $adminId);
    }

    $twilioToken = trim((string) ($input['twilio_auth_token'] ?? ''));
    if ($twilioToken !== '') {
        integration_setting_set('twilio_auth_token', $twilioToken, 'Twilio Auth Token secreto', true, $adminId);
    }
}

function whatsapp_mask_secret(?string $secret): string
{
    $secret = (string) $secret;
    if ($secret === '') {
        return 'Não configurado';
    }

    return strlen($secret) <= 8
        ? str_repeat('*', strlen($secret))
        : substr($secret, 0, 4) . str_repeat('*', max(4, strlen($secret) - 8)) . substr($secret, -4);
}

function whatsapp_normalize_phone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return null;
    }

    if (strlen($digits) === 10 || strlen($digits) === 11) {
        $digits = '55' . $digits;
    }

    if (str_starts_with($digits, '55') && strlen($digits) >= 12 && strlen($digits) <= 13) {
        return $digits;
    }

    return null;
}

function whatsapp_public_order_link(string $code): string
{
    $path = site_url('cliente.php?pedido=' . rawurlencode(ltrim($code, '#')));
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $path;
    }

    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'))
        ? 'https'
        : 'http';

    return $scheme . '://' . $host . $path;
}

function whatsapp_render_order_message(array $pedido, array $itens): string
{
    $config = whatsapp_config();
    $itemsText = [];
    foreach ($itens as $item) {
        $itemsText[] = '- ' . (int) $item['quantidade'] . 'x ' . $item['produto_nome'] . ' - ' . money_br((float) $item['total_linha']);
    }

    $address = (string) ($pedido['recebimento'] ?? '') === 'entrega'
        ? trim((string) ($pedido['endereco'] ?? '') . (($pedido['bairro'] ?? '') ? ' - ' . $pedido['bairro'] : ''))
        : 'Retirada na loja';

    $replacements = [
        '{{codigo}}' => (string) ($pedido['codigo'] ?? ''),
        '{{cliente}}' => (string) ($pedido['cliente_nome'] ?? ''),
        '{{total}}' => money_br((float) ($pedido['total'] ?? 0)),
        '{{forma_pagamento}}' => order_payment_method_label((string) ($pedido['forma_pagamento'] ?? '')),
        '{{status}}' => order_status_label((string) ($pedido['status'] ?? '')),
        '{{itens}}' => implode("\n", $itemsText),
        '{{recebimento}}' => order_receipt_label((string) ($pedido['recebimento'] ?? 'entrega')),
        '{{endereco}}' => $address,
        '{{data_desejada}}' => (string) ($pedido['data_desejada'] ?? ''),
        '{{horario_desejado}}' => substr((string) ($pedido['horario_desejado'] ?? ''), 0, 5),
        '{{link_pedido}}' => whatsapp_public_order_link((string) ($pedido['codigo'] ?? '')),
    ];

    return strtr((string) $config['whatsapp_message_after_order'], $replacements);
}

function whatsapp_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'whatsapp_notificacoes'");
        $exists = (bool) $statement->fetch();
    } catch (Throwable $error) {
        error_log('[ArteFlor][whatsapp-table] ' . $error->getMessage());
        $exists = false;
    }

    return $exists;
}

function whatsapp_log_notification(
    int $orderId,
    string $phone,
    string $type,
    string $message,
    string $status,
    ?string $apiResponse = null,
    ?string $errorMessage = null
): ?int {
    if (!whatsapp_table_exists()) {
        error_log('[ArteFlor][whatsapp-log] Tabela whatsapp_notificacoes ainda não existe.');
        return null;
    }

    db()->prepare(
        'INSERT INTO whatsapp_notificacoes (
            pedido_id, telefone_destino, tipo, mensagem, status, resposta_api, erro, enviado_em
         ) VALUES (
            :pedido_id, :telefone_destino, :tipo, :mensagem, :status, :resposta_api, :erro,
            CASE WHEN :status_enviado IN ("enviado", "simulado") THEN CURRENT_TIMESTAMP ELSE NULL END
         )'
    )->execute([
        'pedido_id' => $orderId,
        'telefone_destino' => $phone,
        'tipo' => $type,
        'mensagem' => $message,
        'status' => $status,
        'resposta_api' => $apiResponse,
        'erro' => $errorMessage,
        'status_enviado' => $status,
    ]);

    return (int) db()->lastInsertId();
}

function whatsapp_http_post_json(string $url, array $headers, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Falha ao preparar JSON da mensagem.'];
    }

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 12,
        ]);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'ok' => $error === '' && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($response) ? $response : '',
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", array_merge(['Content-Type: application/json'], $headers)),
            'content' => $body,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'ok' => $response !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => is_string($response) ? $response : '',
        'error' => $response === false ? 'Falha de rede ao chamar WhatsApp Cloud API.' : '',
    ];
}

function whatsapp_http_post_form(string $url, string $username, string $password, array $payload): array
{
    $body = http_build_query($payload);
    $authHeader = 'Authorization: Basic ' . base64_encode($username . ':' . $password);

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', $authHeader],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 12,
        ]);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'ok' => $error === '' && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($response) ? $response : '',
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" . $authHeader,
            'content' => $body,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'ok' => $response !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => is_string($response) ? $response : '',
        'error' => $response === false ? 'Falha de rede ao chamar Twilio Messaging API.' : '',
    ];
}

function whatsapp_twilio_address(string $phone): string
{
    $phone = trim($phone);
    if (str_starts_with($phone, 'whatsapp:')) {
        return $phone;
    }

    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits !== '' && !str_starts_with($digits, '55') && strlen($digits) <= 11) {
        $digits = '55' . $digits;
    }

    return 'whatsapp:+' . $digits;
}

function whatsapp_send_order_message(int $pedidoId, bool $force = false, string $type = 'pedido_recebido'): array
{
    $pedido = order_find_by_id($pedidoId);
    if (!$pedido) {
        return ['enabled' => false, 'status' => 'erro', 'message' => 'Pedido não encontrado.'];
    }

    $config = whatsapp_config();
    if (!$force && (!$config['whatsapp_enabled'] || !$config['whatsapp_send_after_order'])) {
        return ['enabled' => (bool) $config['whatsapp_enabled'], 'status' => 'desativado'];
    }

    $items = order_items($pedidoId);
    $message = whatsapp_render_order_message($pedido, $items);
    $phone = whatsapp_normalize_phone((string) $pedido['cliente_contato']);
    if (!$phone) {
        whatsapp_log_notification($pedidoId, (string) $pedido['cliente_contato'], $type, $message, 'erro', null, 'Telefone inválido para envio WhatsApp.');
        return ['enabled' => true, 'status' => 'erro', 'message' => 'Telefone inválido para envio WhatsApp.'];
    }

    if ($config['whatsapp_mode'] === 'simulacao') {
        whatsapp_log_notification($pedidoId, $phone, $type, $message, 'simulado', 'Modo simulação: nenhuma API externa chamada.', null);
        return ['enabled' => true, 'status' => 'simulado'];
    }

    if ($config['whatsapp_mode'] === 'twilio') {
        $accountSid = trim((string) $config['twilio_account_sid']);
        $authToken = trim((string) $config['twilio_auth_token']);
        $from = whatsapp_twilio_address((string) $config['twilio_whatsapp_from']);
        if ($accountSid === '' || $authToken === '' || $from === 'whatsapp:+') {
            whatsapp_log_notification($pedidoId, $phone, $type, $message, 'erro', null, 'Twilio Account SID, Auth Token ou remetente WhatsApp não configurado.');
            return ['enabled' => true, 'status' => 'erro', 'message' => 'Twilio WhatsApp sem credenciais completas.'];
        }

        $twilioPayload = [
            'From' => $from,
            'To' => whatsapp_twilio_address($phone),
        ];
        $contentSid = trim((string) $config['twilio_content_sid']);
        if ($contentSid !== '') {
            $twilioPayload['ContentSid'] = $contentSid;
            $twilioPayload['ContentVariables'] = json_encode([
                '1' => (string) ($pedido['codigo'] ?? ''),
                '2' => (string) ($pedido['cliente_nome'] ?? ''),
                '3' => money_br((float) ($pedido['total'] ?? 0)),
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $twilioPayload['Body'] = $message;
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';
        $result = whatsapp_http_post_form($url, $accountSid, $authToken, $twilioPayload);
        if ($result['ok']) {
            whatsapp_log_notification($pedidoId, $phone, $type, $message, 'enviado', $result['body'], null);
            return ['enabled' => true, 'status' => 'enviado'];
        }

        $errorMessage = $result['error'] !== '' ? $result['error'] : 'Twilio Messaging API retornou HTTP ' . $result['status'] . '.';
        whatsapp_log_notification($pedidoId, $phone, $type, $message, 'erro', $result['body'], $errorMessage);
        error_log('[ArteFlor][twilio-whatsapp-send] ' . $errorMessage);

        return ['enabled' => true, 'status' => 'erro', 'message' => 'Não foi possível enviar a notificação Twilio WhatsApp agora.'];
    }

    $token = trim((string) $config['whatsapp_business_token']);
    $phoneNumberId = trim((string) $config['whatsapp_phone_number_id']);
    $apiVersion = trim((string) $config['whatsapp_api_version']) ?: 'v21.0';
    if ($token === '' || $phoneNumberId === '') {
        whatsapp_log_notification($pedidoId, $phone, $type, $message, 'erro', null, 'Token ou Phone Number ID não configurado.');
        return ['enabled' => true, 'status' => 'erro', 'message' => 'WhatsApp Cloud API sem token ou Phone Number ID configurado.'];
    }

    $url = 'https://graph.facebook.com/' . rawurlencode($apiVersion) . '/' . rawurlencode($phoneNumberId) . '/messages';
    $result = whatsapp_http_post_json($url, ['Authorization: Bearer ' . $token], [
        'messaging_product' => 'whatsapp',
        'to' => $phone,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $message,
        ],
    ]);

    if ($result['ok']) {
        whatsapp_log_notification($pedidoId, $phone, $type, $message, 'enviado', $result['body'], null);
        return ['enabled' => true, 'status' => 'enviado'];
    }

    $errorMessage = $result['error'] !== '' ? $result['error'] : 'WhatsApp Cloud API retornou HTTP ' . $result['status'] . '.';
    whatsapp_log_notification($pedidoId, $phone, $type, $message, 'erro', $result['body'], $errorMessage);
    error_log('[ArteFlor][whatsapp-send] ' . $errorMessage);

    return ['enabled' => true, 'status' => 'erro', 'message' => 'Não foi possível enviar a notificação WhatsApp agora.'];
}

function whatsapp_recent_notifications(int $limit = 12): array
{
    if (!whatsapp_table_exists()) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $statement = db()->query(
        'SELECT n.*, p.codigo
         FROM whatsapp_notificacoes n
         INNER JOIN pedidos p ON p.id = n.pedido_id
         ORDER BY n.criado_em DESC, n.id DESC
         LIMIT ' . $limit
    );

    return $statement->fetchAll();
}

function whatsapp_order_notifications(int $orderId): array
{
    if ($orderId <= 0 || !whatsapp_table_exists()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT *
         FROM whatsapp_notificacoes
         WHERE pedido_id = :pedido_id
         ORDER BY criado_em DESC, id DESC'
    );
    $statement->execute(['pedido_id' => $orderId]);

    return $statement->fetchAll();
}

function whatsapp_error_count(): int
{
    if (!whatsapp_table_exists()) {
        return 0;
    }

    $statement = db()->query('SELECT COUNT(*) AS total FROM whatsapp_notificacoes WHERE status = "erro"');

    return (int) ($statement->fetch()['total'] ?? 0);
}
