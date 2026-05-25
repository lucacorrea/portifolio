<?php
require_once __DIR__ . '/orders.php';

const ARTEFLOR_DEFAULT_BAILEYS_BRIDGE_URL = 'https://smthcoari.cloud';

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

function whatsapp_local_config_value(string $key): string
{
    if (defined($key)) {
        return (string) constant($key);
    }

    if (function_exists('env_value')) {
        return (string) env_value($key, '');
    }

    $value = getenv($key);

    return $value === false ? '' : (string) $value;
}

function whatsapp_config(): array
{
    $tokenFromLocal = whatsapp_local_config_value('WHATSAPP_CLOUD_API_TOKEN');
    $phoneIdFromLocal = whatsapp_local_config_value('WHATSAPP_PHONE_NUMBER_ID');
    $versionFromLocal = whatsapp_local_config_value('WHATSAPP_API_VERSION');
    $twilioSidFromLocal = whatsapp_local_config_value('TWILIO_ACCOUNT_SID');
    $twilioTokenFromLocal = whatsapp_local_config_value('TWILIO_AUTH_TOKEN');
    $twilioFromLocal = whatsapp_local_config_value('TWILIO_WHATSAPP_FROM');
    $evolutionUrlFromLocal = whatsapp_local_config_value('EVOLUTION_API_URL');
    $evolutionKeyFromLocal = whatsapp_local_config_value('EVOLUTION_API_KEY');
    $evolutionInstanceFromLocal = whatsapp_local_config_value('EVOLUTION_INSTANCE');
    $bridgeUrlFromLocal = whatsapp_local_config_value('BAILEYS_BRIDGE_URL');
    $bridgeKeyFromLocal = whatsapp_local_config_value('BAILEYS_BRIDGE_API_KEY');
    $storedBridgeUrl = integration_setting('baileys_bridge_url', '') ?: '';
    $bridgeUrl = $bridgeUrlFromLocal !== ''
        ? whatsapp_normalize_base_url($bridgeUrlFromLocal)
        : whatsapp_normalize_base_url($storedBridgeUrl);
    if ($bridgeUrl === '' || whatsapp_bridge_url_should_fallback($bridgeUrl)) {
        $bridgeUrl = whatsapp_bridge_default_url();
    }

    $bridgeKey = $bridgeKeyFromLocal !== ''
        ? $bridgeKeyFromLocal
        : (integration_setting('baileys_bridge_api_key', '') ?: '');
    if ($bridgeUrl === whatsapp_bridge_default_url()) {
        $bridgeKey = '';
    }

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
        'twilio_sandbox_number' => integration_setting('twilio_sandbox_number', '14155238886') ?: '14155238886',
        'twilio_sandbox_join_code' => integration_setting('twilio_sandbox_join_code', '') ?: '',
        'evolution_api_url' => $evolutionUrlFromLocal !== '' ? $evolutionUrlFromLocal : (integration_setting('evolution_api_url', '') ?: ''),
        'evolution_api_key' => $evolutionKeyFromLocal !== '' ? $evolutionKeyFromLocal : (integration_setting('evolution_api_key', '') ?: ''),
        'evolution_instance' => $evolutionInstanceFromLocal !== '' ? $evolutionInstanceFromLocal : (integration_setting('evolution_instance', 'arteflor') ?: 'arteflor'),
        'evolution_owner_number' => integration_setting('evolution_owner_number', '') ?: '',
        'baileys_bridge_url' => $bridgeUrl,
        'baileys_bridge_api_key' => $bridgeKey,
        'baileys_owner_number' => integration_setting('baileys_owner_number', '') ?: '',
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
    if (!in_array($mode, ['simulacao', 'baileys_bridge', 'cloud_api', 'twilio', 'evolution_api'], true)) {
        $mode = 'simulacao';
    }

    if (array_key_exists('pix_key', $input) || array_key_exists('pix_receiver_name', $input) || array_key_exists('pix_instructions', $input)) {
        integration_setting_set('pix_key', order_clean_text($input['pix_key'] ?? '', 180), 'Chave Pix manual', false, $adminId);
        integration_setting_set('pix_receiver_name', order_clean_text($input['pix_receiver_name'] ?? '', 180), 'Nome do recebedor Pix manual', false, $adminId);
        integration_setting_set('pix_instructions', order_clean_text($input['pix_instructions'] ?? '', 500), 'Instruções Pix manual', false, $adminId);
    }
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

    $savedBridgeUrl = whatsapp_normalize_base_url($input['baileys_bridge_url'] ?? '');
    integration_setting_set('baileys_bridge_url', $savedBridgeUrl, 'URL do bridge Baileys ArteFlor', false, $adminId);
    integration_setting_set('baileys_owner_number', whatsapp_link_phone_digits((string) ($input['baileys_owner_number'] ?? '')), 'Número conectado no bridge Baileys', false, $adminId);

    integration_setting_set('twilio_account_sid', order_clean_text($input['twilio_account_sid'] ?? '', 80), 'Twilio Account SID', false, $adminId);
    integration_setting_set('twilio_whatsapp_from', order_clean_text($input['twilio_whatsapp_from'] ?? '', 60), 'Remetente WhatsApp Twilio', false, $adminId);
    integration_setting_set('twilio_content_sid', order_clean_text($input['twilio_content_sid'] ?? '', 80), 'Twilio Content SID para template aprovado', false, $adminId);
    integration_setting_set('twilio_sandbox_number', whatsapp_clean_sandbox_number($input['twilio_sandbox_number'] ?? '14155238886'), 'Número Twilio Sandbox WhatsApp', false, $adminId);
    integration_setting_set('twilio_sandbox_join_code', whatsapp_clean_sandbox_join_code($input['twilio_sandbox_join_code'] ?? ''), 'Código join do Twilio Sandbox', false, $adminId);

    integration_setting_set('evolution_api_url', whatsapp_normalize_base_url($input['evolution_api_url'] ?? ''), 'URL do gateway Evolution API legado', false, $adminId);
    integration_setting_set('evolution_instance', whatsapp_clean_instance_name($input['evolution_instance'] ?? 'arteflor'), 'Instância Evolution API legado', false, $adminId);
    integration_setting_set('evolution_owner_number', whatsapp_link_phone_digits((string) ($input['evolution_owner_number'] ?? '')), 'Número conectado no Evolution API legado', false, $adminId);

    $bridgeKey = trim((string) ($input['baileys_bridge_api_key'] ?? ''));
    if ($bridgeKey !== '') {
        integration_setting_set('baileys_bridge_api_key', $bridgeKey, 'API key secreta do bridge Baileys ArteFlor', true, $adminId);
    } elseif ($savedBridgeUrl === whatsapp_bridge_default_url()) {
        integration_setting_set('baileys_bridge_api_key', '', 'API key secreta do bridge Baileys ArteFlor', true, $adminId);
    }

    $token = trim((string) ($input['whatsapp_business_token'] ?? ''));
    if ($token !== '') {
        integration_setting_set('whatsapp_business_token', $token, 'Token secreto da WhatsApp Cloud API', true, $adminId);
    }

    $twilioToken = trim((string) ($input['twilio_auth_token'] ?? ''));
    if ($twilioToken !== '') {
        integration_setting_set('twilio_auth_token', $twilioToken, 'Twilio Auth Token secreto', true, $adminId);
    }

    $evolutionKey = trim((string) ($input['evolution_api_key'] ?? ''));
    if ($evolutionKey !== '') {
        integration_setting_set('evolution_api_key', $evolutionKey, 'API key secreta do Evolution API legado', true, $adminId);
    }
}

function whatsapp_save_qr_settings(array $input, ?int $adminId = null): void
{
    $currentConfig = whatsapp_config();
    $bridgeUrl = array_key_exists('baileys_bridge_url', $input)
        ? (string) $input['baileys_bridge_url']
        : (string) ($currentConfig['baileys_bridge_url'] ?? '');
    $bridgeKeyProvided = array_key_exists('baileys_bridge_api_key', $input);
    $bridgeKey = $bridgeKeyProvided ? trim((string) $input['baileys_bridge_api_key']) : '';
    $ownerNumber = whatsapp_link_phone_digits((string) ($input['baileys_owner_number'] ?? ($input['evolution_owner_number'] ?? '')));

    integration_setting_set('whatsapp_enabled', '1', 'Ativa notificações WhatsApp pós-compra', false, $adminId);
    integration_setting_set('whatsapp_mode', 'baileys_bridge', 'Modo da integração WhatsApp', false, $adminId);
    integration_setting_set('whatsapp_send_after_order', '1', 'Enviar WhatsApp ao criar pedido', false, $adminId);
    $savedBridgeUrl = whatsapp_normalize_base_url($bridgeUrl);
    integration_setting_set('baileys_bridge_url', $savedBridgeUrl, 'URL do bridge Baileys ArteFlor', false, $adminId);
    integration_setting_set('baileys_owner_number', $ownerNumber, 'Número conectado no bridge Baileys', false, $adminId);

    if ($ownerNumber !== '') {
        integration_setting_set('whatsapp_company_number', $ownerNumber, 'Número comercial da empresa', false, $adminId);
    }

    if ($bridgeKey !== '') {
        integration_setting_set('baileys_bridge_api_key', $bridgeKey, 'API key secreta do bridge Baileys ArteFlor', true, $adminId);
    } elseif ($bridgeKeyProvided && $savedBridgeUrl === whatsapp_bridge_default_url()) {
        integration_setting_set('baileys_bridge_api_key', '', 'API key secreta do bridge Baileys ArteFlor', true, $adminId);
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

    if (strlen($digits) >= 10 && strlen($digits) <= 15) {
        return $digits;
    }

    return null;
}

function whatsapp_clean_sandbox_number(mixed $value): string
{
    $digits = preg_replace('/\D+/', '', order_clean_text($value, 40)) ?? '';

    return $digits !== '' ? $digits : '14155238886';
}

function whatsapp_clean_sandbox_join_code(mixed $value): string
{
    $code = trim((string) $value);
    $code = preg_replace('/^join\s+/i', '', $code) ?? $code;
    $code = preg_replace('/\s+/', ' ', $code) ?? '';

    return order_clean_text($code, 80);
}

function whatsapp_link_phone_digits(string $phone, bool $defaultBrazil = true): string
{
    $digits = preg_replace('/\D+/', '', str_replace('whatsapp:', '', $phone)) ?? '';
    if ($defaultBrazil && $digits !== '' && !str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
        $digits = '55' . $digits;
    }

    return $digits;
}

function whatsapp_wa_me_link(string $phone, string $message = '', bool $defaultBrazil = true): ?string
{
    $digits = whatsapp_link_phone_digits($phone, $defaultBrazil);
    if (strlen($digits) < 10 || strlen($digits) > 15) {
        return null;
    }

    $url = 'https://wa.me/' . $digits;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }

    return $url;
}

function whatsapp_qr_image_url(string $payload, int $size = 220): string
{
    $size = max(140, min(320, $size));

    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&margin=12&data=' . rawurlencode($payload);
}

function whatsapp_normalize_base_url(mixed $value): string
{
    $url = rtrim(order_clean_text($value, 220), '/');
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    return $url;
}

function whatsapp_bridge_default_url(): string
{
    return whatsapp_normalize_base_url(ARTEFLOR_DEFAULT_BAILEYS_BRIDGE_URL);
}

function whatsapp_bridge_url_is_local(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function whatsapp_bridge_url_should_fallback(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return whatsapp_bridge_url_is_local($url)
        || in_array($host, ['whatsapp.lucascorrea.pro'], true);
}

function whatsapp_clean_instance_name(mixed $value): string
{
    $name = strtolower(order_clean_text($value, 80));
    $name = preg_replace('/[^a-z0-9_-]/', '-', $name) ?? '';
    $name = trim($name, '-_');

    return $name !== '' ? $name : 'arteflor';
}

function whatsapp_http_get_json(string $url, array $headers = []): array
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
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
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
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
        'error' => $response === false ? 'Falha de rede ao chamar API WhatsApp.' : '',
    ];
}

function whatsapp_json_body(array $result): array
{
    $decoded = json_decode((string) ($result['body'] ?? ''), true);

    return is_array($decoded) ? $decoded : [];
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
        'error' => $response === false ? 'Falha de rede ao chamar API WhatsApp.' : '',
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

function whatsapp_bridge_configured(array $config): bool
{
    return trim((string) ($config['baileys_bridge_url'] ?? '')) !== '';
}

function whatsapp_bridge_endpoint(array $config, string $path): string
{
    return rtrim((string) $config['baileys_bridge_url'], '/') . '/' . ltrim($path, '/');
}

function whatsapp_bridge_headers(array $config): array
{
    $apiKey = trim((string) ($config['baileys_bridge_api_key'] ?? ''));
    if ($apiKey === '') {
        return [];
    }

    return [
        'X-API-Key: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
    ];
}

function whatsapp_bridge_error_message(array $result, string $fallback): string
{
    $data = whatsapp_json_body($result);
    $apiMessage = (string) ($data['message'] ?? $data['error'] ?? '');
    if ($apiMessage !== '') {
        return $apiMessage;
    }

    if ((int) ($result['status'] ?? 0) === 401) {
        return 'Bridge recusou a conexão. Confira a API key salva no painel.';
    }

    if ((int) ($result['status'] ?? 0) === 503) {
        return 'Bridge offline ou sem configuração no Node.js.';
    }

    if ((string) ($result['error'] ?? '') !== '') {
        return (string) $result['error'];
    }

    return $fallback;
}

function whatsapp_bridge_status(array $config): array
{
    if (!whatsapp_bridge_configured($config)) {
        return [
            'success' => false,
            'connected' => false,
            'status' => 'not_configured',
            'message' => 'Informe o link do serviço QR para conectar o WhatsApp.',
            'number' => '',
        ];
    }

    $status = whatsapp_http_get_json(whatsapp_bridge_endpoint($config, '/status'), whatsapp_bridge_headers($config));
    $statusData = whatsapp_json_body($status);

    if ($status['ok']) {
        return [
            'success' => true,
            'connected' => !empty($statusData['connected']),
            'status' => (string) ($statusData['status'] ?? (!empty($statusData['connected']) ? 'connected' : 'disconnected')),
            'message' => (string) ($statusData['message'] ?? ''),
            'number' => (string) ($statusData['number'] ?? ''),
            'raw' => $status['body'],
        ];
    }

    return [
        'success' => false,
        'connected' => false,
        'status' => 'offline',
        'message' => whatsapp_bridge_error_message($status, 'Bridge Offline. Inicie o Node.js e tente novamente.'),
        'number' => '',
        'raw' => $status['body'],
    ];
}

function whatsapp_bridge_qrcode(array $config): array
{
    if (!whatsapp_bridge_configured($config)) {
        return [
            'success' => false,
            'qr' => null,
            'status' => 'not_configured',
            'message' => 'Informe o link do serviço QR para gerar o código.',
        ];
    }

    $qrResponse = whatsapp_http_get_json(whatsapp_bridge_endpoint($config, '/qrcode'), whatsapp_bridge_headers($config));
    $qrData = whatsapp_json_body($qrResponse);
    $qr = (string) ($qrData['qr'] ?? $qrData['qrcode'] ?? '');

    if ($qrResponse['ok'] && $qr !== '') {
        return [
            'success' => true,
            'status' => 'qr',
            'message' => (string) ($qrData['message'] ?? 'Escaneie o QR com o WhatsApp da empresa.'),
            'qr' => $qr,
            'number' => (string) ($qrData['number'] ?? ''),
            'raw' => $qrResponse['body'],
        ];
    }

    if ($qrResponse['ok']) {
        return [
            'success' => false,
            'status' => (string) ($qrData['status'] ?? 'aguardando'),
            'message' => (string) ($qrData['message'] ?? 'QR ainda não disponível. Aguarde alguns segundos e clique novamente.'),
            'qr' => null,
            'raw' => $qrResponse['body'],
        ];
    }

    return [
        'success' => false,
        'status' => 'offline',
        'message' => whatsapp_bridge_error_message($qrResponse, 'Não foi possível consultar o QR no bridge WhatsApp.'),
        'qr' => null,
        'raw' => $qrResponse['body'],
    ];
}

function whatsapp_bridge_logout(array $config): array
{
    if (!whatsapp_bridge_configured($config)) {
        return [
            'success' => false,
            'status' => 'not_configured',
            'message' => 'Informe o link do serviço QR antes de desconectar.',
        ];
    }

    $result = whatsapp_http_post_json(whatsapp_bridge_endpoint($config, '/logout'), whatsapp_bridge_headers($config), []);
    if (!$result['ok'] && (int) ($result['status'] ?? 0) === 404) {
        $result = whatsapp_http_get_json(whatsapp_bridge_endpoint($config, '/logout'), whatsapp_bridge_headers($config));
    }

    if ($result['ok']) {
        return [
            'success' => true,
            'status' => 'disconnected',
            'message' => 'WhatsApp desconectado. Um novo QR será gerado em instantes.',
            'raw' => $result['body'],
        ];
    }

    return [
        'success' => false,
        'status' => 'erro',
        'message' => whatsapp_bridge_error_message($result, 'Não foi possível desconectar o WhatsApp agora.'),
        'raw' => $result['body'],
    ];
}

function whatsapp_bridge_prepare_qr(array $config): array
{
    $status = whatsapp_bridge_status($config);
    if (!empty($status['connected'])) {
        return [
            'success' => true,
            'status' => 'connected',
            'message' => 'WhatsApp conectado no bridge Baileys.',
            'number' => (string) ($status['number'] ?? ''),
            'raw' => (string) ($status['raw'] ?? ''),
        ];
    }

    if (($status['status'] ?? '') === 'offline' || ($status['status'] ?? '') === 'not_configured') {
        return $status;
    }

    return whatsapp_bridge_qrcode($config);
}

function whatsapp_evolution_configured(array $config): bool
{
    return trim((string) ($config['evolution_api_url'] ?? '')) !== ''
        && trim((string) ($config['evolution_api_key'] ?? '')) !== ''
        && trim((string) ($config['evolution_instance'] ?? '')) !== '';
}

function whatsapp_evolution_endpoint(array $config, string $path): string
{
    return rtrim((string) $config['evolution_api_url'], '/') . '/' . ltrim($path, '/');
}

function whatsapp_evolution_create_instance(array $config): array
{
    $instance = whatsapp_clean_instance_name($config['evolution_instance'] ?? 'arteflor');
    $payload = [
        'instanceName' => $instance,
        'integration' => 'WHATSAPP-BAILEYS',
        'qrcode' => true,
    ];
    $ownerNumber = whatsapp_link_phone_digits((string) ($config['evolution_owner_number'] ?? ''));
    if ($ownerNumber !== '') {
        $payload['number'] = $ownerNumber;
    }

    return whatsapp_http_post_json(
        whatsapp_evolution_endpoint($config, '/instance/create'),
        ['apikey: ' . (string) $config['evolution_api_key']],
        $payload
    );
}

function whatsapp_evolution_connection_state(array $config): array
{
    $instance = rawurlencode(whatsapp_clean_instance_name($config['evolution_instance'] ?? 'arteflor'));

    return whatsapp_http_get_json(
        whatsapp_evolution_endpoint($config, '/instance/connectionState/' . $instance),
        ['apikey: ' . (string) $config['evolution_api_key']]
    );
}

function whatsapp_evolution_connect(array $config): array
{
    $instance = rawurlencode(whatsapp_clean_instance_name($config['evolution_instance'] ?? 'arteflor'));
    $ownerNumber = whatsapp_link_phone_digits((string) ($config['evolution_owner_number'] ?? ''));
    $path = '/instance/connect/' . $instance . ($ownerNumber !== '' ? '?number=' . rawurlencode($ownerNumber) : '');

    return whatsapp_http_get_json(
        whatsapp_evolution_endpoint($config, $path),
        ['apikey: ' . (string) $config['evolution_api_key']]
    );
}

function whatsapp_find_recursive_value(array $data, array $keys): ?string
{
    foreach ($data as $key => $value) {
        if (is_string($key) && in_array(strtolower($key), $keys, true) && is_string($value) && trim($value) !== '') {
            return trim($value);
        }
        if (is_array($value)) {
            $found = whatsapp_find_recursive_value($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function whatsapp_evolution_qr_image_src(array $data): ?string
{
    $base64 = whatsapp_find_recursive_value($data, ['base64', 'qrcodebase64']);
    if ($base64 !== null) {
        if (str_starts_with($base64, 'data:image/')) {
            return $base64;
        }
        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $base64) && strlen($base64) > 100) {
            return 'data:image/png;base64,' . preg_replace('/\s+/', '', $base64);
        }
    }

    $code = whatsapp_find_recursive_value($data, ['code', 'qrcode', 'qr', 'qr_code']);
    if ($code !== null) {
        return whatsapp_qr_image_url($code, 260);
    }

    return null;
}

function whatsapp_evolution_pairing_code(array $data): string
{
    return whatsapp_find_recursive_value($data, ['pairingcode', 'pairing_code']) ?? '';
}

function whatsapp_evolution_prepare_qr(array $config): array
{
    if (!whatsapp_evolution_configured($config)) {
        return [
            'success' => false,
            'status' => 'erro',
            'message' => 'Configure URL, API key e instância do gateway antes de gerar o QR.',
        ];
    }

    $state = whatsapp_evolution_connection_state($config);
    $stateData = whatsapp_json_body($state);
    $stateText = strtolower((string) (whatsapp_find_recursive_value($stateData, ['state', 'status']) ?? ''));
    if ($state['ok'] && in_array($stateText, ['open', 'connected'], true)) {
        return [
            'success' => true,
            'status' => 'connected',
            'message' => 'WhatsApp conectado nesta instância.',
            'raw' => $state['body'],
        ];
    }

    if (!$state['ok'] && (int) $state['status'] === 404) {
        whatsapp_evolution_create_instance($config);
    }

    $connect = whatsapp_evolution_connect($config);
    $data = whatsapp_json_body($connect);
    $qr = whatsapp_evolution_qr_image_src($data);

    if ($connect['ok'] && $qr !== null) {
        return [
            'success' => true,
            'status' => 'qr',
            'message' => 'Escaneie o QR com o WhatsApp da empresa.',
            'qr' => $qr,
            'pairing_code' => whatsapp_evolution_pairing_code($data),
            'raw' => $connect['body'],
        ];
    }

    $error = $connect['error'] !== '' ? $connect['error'] : 'Gateway retornou HTTP ' . $connect['status'] . '.';

    return [
        'success' => false,
        'status' => 'erro',
        'message' => 'Não foi possível gerar o QR pelo gateway. ' . $error,
        'raw' => $connect['body'],
    ];
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

    if ($config['whatsapp_mode'] === 'baileys_bridge') {
        if (!whatsapp_bridge_configured($config)) {
            whatsapp_log_notification($pedidoId, $phone, $type, $message, 'erro', null, 'Bridge WhatsApp sem URL configurada.');
            return ['enabled' => true, 'status' => 'erro', 'message' => 'Bridge WhatsApp Baileys não configurado.'];
        }

        $result = whatsapp_http_post_json(
            whatsapp_bridge_endpoint($config, '/send-message'),
            whatsapp_bridge_headers($config),
            [
                'number' => $phone,
                'text' => $message,
                'reference' => (string) ($pedido['codigo'] ?? ''),
            ]
        );

        if ($result['ok']) {
            whatsapp_log_notification($pedidoId, $phone, $type, $message, 'enviado', $result['body'], null);
            return ['enabled' => true, 'status' => 'enviado'];
        }

        $errorMessage = $result['error'] !== '' ? $result['error'] : 'Bridge Baileys retornou HTTP ' . $result['status'] . '.';
        whatsapp_log_notification($pedidoId, $phone, $type, $message, 'erro', $result['body'], $errorMessage);
        error_log('[ArteFlor][baileys-bridge-send] ' . $errorMessage);

        return ['enabled' => true, 'status' => 'erro', 'message' => 'Não foi possível enviar pelo bridge WhatsApp Baileys agora.'];
    }

    if ($config['whatsapp_mode'] === 'evolution_api') {
        if (!whatsapp_evolution_configured($config)) {
            whatsapp_log_notification($pedidoId, $phone, $type, $message, 'erro', null, 'Evolution API sem URL, API key ou instância configurada.');
            return ['enabled' => true, 'status' => 'erro', 'message' => 'Gateway WhatsApp por QR não configurado.'];
        }

        $instance = rawurlencode(whatsapp_clean_instance_name($config['evolution_instance'] ?? 'arteflor'));
        $result = whatsapp_http_post_json(
            whatsapp_evolution_endpoint($config, '/message/sendText/' . $instance),
            ['apikey: ' . (string) $config['evolution_api_key']],
            [
                'number' => $phone,
                'text' => $message,
                'delay' => 1200,
                'linkPreview' => false,
            ]
        );

        if ($result['ok']) {
            whatsapp_log_notification($pedidoId, $phone, $type, $message, 'enviado', $result['body'], null);
            return ['enabled' => true, 'status' => 'enviado'];
        }

        $errorMessage = $result['error'] !== '' ? $result['error'] : 'Evolution API retornou HTTP ' . $result['status'] . '.';
        whatsapp_log_notification($pedidoId, $phone, $type, $message, 'erro', $result['body'], $errorMessage);
        error_log('[ArteFlor][evolution-whatsapp-send] ' . $errorMessage);

        return ['enabled' => true, 'status' => 'erro', 'message' => 'Não foi possível enviar pelo gateway WhatsApp por QR agora.'];
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
