<?php

declare(strict_types=1);

function whatsapp_ensure_tables(): bool
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS whatsapp_conexoes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT UNSIGNED NOT NULL,
                instancia_nome VARCHAR(100) NOT NULL,
                telefone_conectado VARCHAR(30) DEFAULT NULL,
                status ENUM('desconectado','conectando','conectado','erro') NOT NULL DEFAULT 'desconectado',
                qr_code TEXT DEFAULT NULL,
                qr_code_imagem MEDIUMTEXT DEFAULT NULL,
                pairing_code VARCHAR(40) DEFAULT NULL,
                ultimo_erro TEXT DEFAULT NULL,
                ultima_sincronizacao DATETIME DEFAULT NULL,
                conectado_em DATETIME DEFAULT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_whatsapp_conexoes_empresa (empresa_id),
                UNIQUE KEY uq_whatsapp_conexoes_instancia (instancia_nome),
                INDEX idx_whatsapp_conexoes_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS whatsapp_comprovantes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT UNSIGNED NOT NULL,
                cliente_id INT UNSIGNED DEFAULT NULL,
                cobranca_id INT UNSIGNED DEFAULT NULL,
                mensagem_id VARCHAR(120) NOT NULL,
                remetente VARCHAR(30) NOT NULL,
                mime_type VARCHAR(80) DEFAULT NULL,
                valor_detectado DECIMAL(10,2) DEFAULT NULL,
                data_detectada DATE DEFAULT NULL,
                status_processamento ENUM('recebido','pendente','confirmado','recusado','erro') NOT NULL DEFAULT 'recebido',
                motivo TEXT DEFAULT NULL,
                resposta_ia TEXT DEFAULT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_whatsapp_comprovante_msg (empresa_id, mensagem_id),
                INDEX idx_whatsapp_comprovantes_empresa (empresa_id),
                INDEX idx_whatsapp_comprovantes_cliente (cliente_id),
                INDEX idx_whatsapp_comprovantes_cobranca (cobranca_id),
                INDEX idx_whatsapp_comprovantes_status (status_processamento),
                INDEX idx_whatsapp_comprovantes_criado (criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $ready = true;
    } catch (Throwable $e) {
        error_log('[WHATSAPP SETUP] ' . $e->getMessage());
        $ready = false;
    }

    return $ready;
}

function whatsapp_api_base_url(): string
{
    return rtrim((string) env('WHATSAPP_API_URL', ''), '/');
}

function whatsapp_provider(): string
{
    $provider = strtolower(trim((string) env('WHATSAPP_PROVIDER', '')));

    if ($provider === '' && trim((string) env('WHATSAPP_BRIDGE_URL', '')) !== '') {
        return 'bridge';
    }

    if ($provider === '' || in_array($provider, ['bridge', 'baileys'], true)) {
        return 'bridge';
    }

    return 'evolution';
}

function whatsapp_api_key(): string
{
    return trim((string) env('WHATSAPP_API_KEY', ''));
}

function whatsapp_api_configured(): bool
{
    return whatsapp_api_base_url() !== '' && whatsapp_api_key() !== '';
}

function whatsapp_bridge_base_url(): string
{
    $configuredUrl = rtrim((string) env('WHATSAPP_BRIDGE_URL', ''), '/');

    if ($configuredUrl !== '') {
        return $configuredUrl;
    }

    return whatsapp_provider() === 'bridge' ? 'http://127.0.0.1:8080' : '';
}

function whatsapp_bridge_token(): string
{
    return trim((string) env('WHATSAPP_BRIDGE_TOKEN', ''));
}

function whatsapp_bridge_configured(): bool
{
    return whatsapp_bridge_base_url() !== '';
}

function whatsapp_bridge_instance_path(string $path, string $instanceName): string
{
    $separator = str_contains($path, '?') ? '&' : '?';
    $instanceName = trim($instanceName) !== '' ? $instanceName : 'default';

    return $path . $separator . 'instance=' . rawurlencode(whatsapp_sanitize_instance_name($instanceName, 0));
}

function whatsapp_expected_webhook_token(): string
{
    return trim((string) env('WHATSAPP_WEBHOOK_TOKEN', env('WHATSAPP_BRIDGE_TOKEN', '')));
}

function whatsapp_integration_configured(): bool
{
    return whatsapp_provider() === 'bridge'
        ? whatsapp_bridge_configured()
        : whatsapp_api_configured();
}

function whatsapp_config_error_message(): string
{
    if (whatsapp_provider() === 'bridge') {
        return 'Configure WHATSAPP_PROVIDER=bridge e, se a bridge não estiver no mesmo servidor, informe WHATSAPP_BRIDGE_URL no .env.';
    }

    return 'Configure WHATSAPP_API_URL e WHATSAPP_API_KEY no .env.';
}

function whatsapp_default_instance_name(int $empresaId): string
{
    $prefix = strtolower((string) env('WHATSAPP_INSTANCE_PREFIX', 'fluxpay'));
    $prefix = preg_replace('/[^a-z0-9_-]+/', '', $prefix) ?: 'fluxpay';

    return $prefix . '-empresa-' . $empresaId;
}

function whatsapp_sanitize_instance_name(string $value, int $empresaId): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    $value = trim($value, '-_');

    if ($value === '' || strlen($value) < 3) {
        return whatsapp_default_instance_name($empresaId);
    }

    return substr($value, 0, 100);
}

function whatsapp_get_connection(int $empresaId): array
{
    whatsapp_ensure_tables();

    $stmt = db()->prepare('SELECT * FROM whatsapp_conexoes WHERE empresa_id = :empresa_id LIMIT 1');
    $stmt->execute([':empresa_id' => $empresaId]);
    $connection = $stmt->fetch();

    if ($connection) {
        return $connection;
    }

    $instanceName = whatsapp_default_instance_name($empresaId);
    $stmt = db()->prepare(
        "INSERT INTO whatsapp_conexoes (empresa_id, instancia_nome, status, criado_em)
         VALUES (:empresa_id, :instancia_nome, 'desconectado', NOW())"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':instancia_nome' => $instanceName,
    ]);

    $stmt = db()->prepare('SELECT * FROM whatsapp_conexoes WHERE empresa_id = :empresa_id LIMIT 1');
    $stmt->execute([':empresa_id' => $empresaId]);

    return $stmt->fetch() ?: [];
}

function whatsapp_update_connection(int $empresaId, array $data): void
{
    whatsapp_ensure_tables();

    $allowed = [
        'instancia_nome',
        'telefone_conectado',
        'status',
        'qr_code',
        'qr_code_imagem',
        'pairing_code',
        'ultimo_erro',
        'ultima_sincronizacao',
        'conectado_em',
    ];

    $fields = [];
    $params = [':empresa_id' => $empresaId];

    foreach ($data as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }

        $param = ':' . $key;
        $fields[] = $key . ' = ' . $param;
        $params[$param] = $value;
    }

    if (!$fields) {
        return;
    }

    $fields[] = 'atualizado_em = NOW()';
    $stmt = db()->prepare('UPDATE whatsapp_conexoes SET ' . implode(', ', $fields) . ' WHERE empresa_id = :empresa_id');
    $stmt->execute($params);
}

function whatsapp_status_label(string $status): string
{
    return [
        'desconectado' => 'Desconectado',
        'conectando' => 'Aguardando leitura',
        'conectado' => 'Conectado',
        'erro' => 'Erro de conexão',
    ][$status] ?? 'Desconectado';
}

function whatsapp_status_badge(string $status): string
{
    return [
        'desconectado' => 'pendente',
        'conectando' => 'pendente',
        'conectado' => 'ativa',
        'erro' => 'vencida',
    ][$status] ?? 'pendente';
}

function whatsapp_normalize_phone(string $phone): ?string
{
    $digits = only_digits($phone);

    if ($digits === '') {
        return null;
    }

    while (str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) === 10 || strlen($digits) === 11) {
        $digits = '55' . $digits;
    }

    if (strlen($digits) < 12 || strlen($digits) > 15) {
        return null;
    }

    return $digits;
}

function whatsapp_api_request(string $method, string $path, ?array $payload = null): array
{
    if (!whatsapp_api_configured()) {
        return [
            'ok' => false,
            'http_code' => 0,
            'data' => null,
            'raw' => '',
            'error' => whatsapp_config_error_message(),
        ];
    }

    $url = whatsapp_api_base_url() . '/' . ltrim($path, '/');
    $authHeader = trim((string) env('WHATSAPP_API_AUTH_HEADER', 'apikey')) ?: 'apikey';
    $timeout = max(3, (int) env('WHATSAPP_API_TIMEOUT', '15'));

    $headers = [
        'Accept: application/json',
        $authHeader . ': ' . whatsapp_api_key(),
    ];

    $options = [
        'method' => strtoupper($method),
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'timeout' => $timeout,
    ];

    if ($payload !== null) {
        $options['header'] .= "\r\nContent-Type: application/json";
        $options['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $context = stream_context_create(['http' => $options]);
    $raw = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $httpCode = 0;

    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
            $httpCode = (int) $matches[1];
            break;
        }
    }

    if ($raw === false) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'data' => null,
            'raw' => '',
            'error' => 'Falha ao comunicar com a API de WhatsApp.',
        ];
    }

    $decoded = json_decode($raw, true);
    $data = is_array($decoded) ? $decoded : ['raw' => $raw];
    $ok = $httpCode >= 200 && $httpCode < 300;

    return [
        'ok' => $ok,
        'http_code' => $httpCode,
        'data' => $data,
        'raw' => $raw,
        'error' => $ok ? null : whatsapp_api_error_message($data, $httpCode),
    ];
}

function whatsapp_bridge_request(string $method, string $path, ?array $payload = null): array
{
    if (!whatsapp_bridge_configured()) {
        return [
            'ok' => false,
            'http_code' => 0,
            'data' => null,
            'raw' => '',
            'error' => whatsapp_config_error_message(),
        ];
    }

    $url = whatsapp_bridge_base_url() . '/' . ltrim($path, '/');
    $configuredTimeout = (int) env('WHATSAPP_BRIDGE_TIMEOUT', env('WHATSAPP_API_TIMEOUT', '15'));
    $timeout = str_starts_with('/' . ltrim($path, '/'), '/qrcode')
        ? max(25, $configuredTimeout)
        : max(3, $configuredTimeout);
    $token = whatsapp_bridge_token();
    $authHeader = trim((string) env('WHATSAPP_BRIDGE_AUTH_HEADER', 'Authorization')) ?: 'Authorization';

    $headers = ['Accept: application/json'];

    if ($token !== '') {
        $headerValue = strcasecmp($authHeader, 'Authorization') === 0
            ? 'Bearer ' . $token
            : $token;
        $headers[] = $authHeader . ': ' . $headerValue;
    }

    $options = [
        'method' => strtoupper($method),
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'timeout' => $timeout,
    ];

    if ($payload !== null) {
        $options['header'] .= "\r\nContent-Type: application/json";
        $options['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $context = stream_context_create(['http' => $options]);
    $raw = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $httpCode = 0;

    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
            $httpCode = (int) $matches[1];
            break;
        }
    }

    if ($raw === false) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'data' => null,
            'raw' => '',
            'error' => 'Falha ao comunicar com a bridge de WhatsApp.',
        ];
    }

    $decoded = json_decode($raw, true);
    $data = is_array($decoded) ? $decoded : ['raw' => $raw];
    $ok = $httpCode >= 200 && $httpCode < 300;

    return [
        'ok' => $ok,
        'http_code' => $httpCode,
        'data' => $data,
        'raw' => $raw,
        'error' => $ok ? null : whatsapp_api_error_message($data, $httpCode),
    ];
}

function whatsapp_api_error_message(array $data, int $httpCode): string
{
    foreach (['message', 'error', 'response', 'details'] as $key) {
        if (!empty($data[$key]) && is_string($data[$key])) {
            return $data[$key];
        }
    }

    return $httpCode > 0
        ? 'API de WhatsApp retornou HTTP ' . $httpCode . '.'
        : 'Não foi possível obter resposta da API de WhatsApp.';
}

function whatsapp_find_first_value(array $data, array $keys): ?string
{
    foreach ($data as $key => $value) {
        if (in_array((string) $key, $keys, true) && is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        if (is_array($value)) {
            $found = whatsapp_find_first_value($value, $keys);

            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function whatsapp_extract_qr_image(array $data): ?string
{
    $value = whatsapp_find_first_value($data, ['base64', 'qr', 'qrcode', 'qrCode', 'qr_code', 'image']);

    if ($value === null) {
        return null;
    }

    if (str_starts_with($value, 'data:image/')) {
        return $value;
    }

    $compact = preg_replace('/\s+/', '', $value) ?? '';

    if (strlen($compact) > 100 && preg_match('/^[A-Za-z0-9+\/=]+$/', $compact)) {
        return 'data:image/png;base64,' . $compact;
    }

    return null;
}

function whatsapp_extract_connection_state(array $data): string
{
    $state = strtolower((string) (
        $data['instance']['state']
        ?? $data['instance']['status']
        ?? $data['state']
        ?? $data['status']
        ?? ''
    ));

    return match ($state) {
        'open', 'connected', 'conectado' => 'conectado',
        'connecting', 'qrcode', 'qr', 'pairing', 'conectando' => 'conectando',
        'close', 'closed', 'disconnected', 'desconectado' => 'desconectado',
        default => $state !== '' ? 'erro' : 'desconectado',
    };
}

function whatsapp_extract_bridge_state(array $data): string
{
    if (array_key_exists('connected', $data) && (bool) $data['connected']) {
        return 'conectado';
    }

    $status = strtolower((string) ($data['status'] ?? ''));

    return match ($status) {
        'connected', 'open', 'conectado' => 'conectado',
        'starting', 'connecting', 'waiting_qr', 'qrcode', 'qr', 'gerando', 'reconnecting', 'conectando' => 'conectando',
        'offline', 'error', 'erro' => 'erro',
        'disconnected', 'desconectado', 'close', 'closed' => 'desconectado',
        default => 'desconectado',
    };
}

function whatsapp_bridge_fetch_qr_with_retry(?string $instanceName = null, int $attempts = 5, int $sleepMicroseconds = 1000000, int $maxSeconds = 18): array
{
    $lastResponse = [
        'ok' => false,
        'http_code' => 0,
        'data' => null,
        'raw' => '',
        'error' => 'QR Code ainda não foi gerado pela bridge.',
    ];

    $deadline = time() + $maxSeconds;

    for ($attempt = 1; $attempt <= $attempts && time() <= $deadline; $attempt++) {
        $path = $instanceName !== null
            ? whatsapp_bridge_instance_path('/qrcode', $instanceName)
            : '/qrcode';
        $response = whatsapp_bridge_request('GET', $path);
        $lastResponse = $response;

        if (!$response['ok']) {
            return $response;
        }

        $qrImage = whatsapp_extract_qr_image($response['data']);
        $qrCode = whatsapp_find_first_value($response['data'], ['code', 'qrCode', 'qrcode']);
        $status = whatsapp_extract_bridge_state($response['data']);

        if ($qrImage !== null || $qrCode !== null || $status === 'conectado') {
            return $response;
        }

        if ($attempt < $attempts && time() < $deadline) {
            usleep($sleepMicroseconds);
        }
    }

    return $lastResponse;
}

function whatsapp_refresh_connection(int $empresaId): array
{
    $connection = whatsapp_get_connection($empresaId);
    $instance = (string) ($connection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId));

    if (!whatsapp_integration_configured()) {
        whatsapp_update_connection($empresaId, [
            'status' => 'erro',
            'ultimo_erro' => whatsapp_config_error_message(),
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => false, 'message' => 'Integração de WhatsApp não configurada.'];
    }

    if (whatsapp_provider() === 'bridge') {
        $response = whatsapp_bridge_request('GET', whatsapp_bridge_instance_path('/status', $instance));

        if (!$response['ok']) {
            whatsapp_update_connection($empresaId, [
                'status' => 'erro',
                'ultimo_erro' => $response['error'],
                'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => false, 'message' => (string) $response['error']];
        }

        $status = whatsapp_extract_bridge_state($response['data']);
        $connectedNumber = whatsapp_normalize_phone((string) ($response['data']['number'] ?? ''));
        $qrCode = $connection['qr_code'] ?? null;
        $qrImage = $connection['qr_code_imagem'] ?? null;

        if ($status !== 'conectado') {
            $qrResponse = whatsapp_bridge_fetch_qr_with_retry($instance, 3, 500000);

            if ($qrResponse['ok']) {
                $qrCode = whatsapp_find_first_value($qrResponse['data'], ['code', 'qrCode', 'qrcode']);
                $qrImage = whatsapp_extract_qr_image($qrResponse['data']);
                $qrStatus = whatsapp_extract_bridge_state($qrResponse['data']);

                if ($qrStatus === 'conectado') {
                    $status = 'conectado';
                    $qrCode = null;
                    $qrImage = null;
                } elseif ($qrImage !== null || $qrCode !== null) {
                    $status = 'conectando';
                }
            }
        }

        whatsapp_update_connection($empresaId, [
            'status' => $status,
            'telefone_conectado' => $connectedNumber ?: ($connection['telefone_conectado'] ?? null),
            'ultimo_erro' => null,
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            'conectado_em' => $status === 'conectado' ? date('Y-m-d H:i:s') : null,
            'qr_code' => $status === 'conectado' ? null : $qrCode,
            'qr_code_imagem' => $status === 'conectado' ? null : $qrImage,
            'pairing_code' => null,
        ]);

        return ['ok' => true, 'message' => 'Status atualizado.', 'status' => $status];
    }

    $response = whatsapp_api_request('GET', '/instance/connectionState/' . rawurlencode($instance));

    if (!$response['ok']) {
        whatsapp_update_connection($empresaId, [
            'status' => 'erro',
            'ultimo_erro' => $response['error'],
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => false, 'message' => (string) $response['error']];
    }

    $status = whatsapp_extract_connection_state($response['data']);
    whatsapp_update_connection($empresaId, [
        'status' => $status,
        'ultimo_erro' => null,
        'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        'conectado_em' => $status === 'conectado' ? date('Y-m-d H:i:s') : null,
        'qr_code' => $status === 'conectado' ? null : ($connection['qr_code'] ?? null),
        'qr_code_imagem' => $status === 'conectado' ? null : ($connection['qr_code_imagem'] ?? null),
        'pairing_code' => $status === 'conectado' ? null : ($connection['pairing_code'] ?? null),
    ]);

    return ['ok' => true, 'message' => 'Status atualizado.', 'status' => $status];
}

function whatsapp_connect_instance(int $empresaId, string $instanceName, string $phone = ''): array
{
    whatsapp_get_connection($empresaId);

    $instanceName = whatsapp_sanitize_instance_name($instanceName, $empresaId);
    $normalizedPhone = whatsapp_normalize_phone($phone);

    whatsapp_update_connection($empresaId, [
        'instancia_nome' => $instanceName,
        'telefone_conectado' => $normalizedPhone ?: null,
        'status' => 'conectando',
        'ultimo_erro' => null,
        'ultima_sincronizacao' => date('Y-m-d H:i:s'),
    ]);

    if (!whatsapp_integration_configured()) {
        whatsapp_update_connection($empresaId, [
            'status' => 'erro',
            'ultimo_erro' => whatsapp_config_error_message(),
        ]);

        return ['ok' => false, 'message' => 'Integração de WhatsApp não configurada.'];
    }

    if (whatsapp_provider() === 'bridge') {
        $statusResponse = whatsapp_bridge_request('GET', whatsapp_bridge_instance_path('/status', $instanceName));

        if ($statusResponse['ok'] && whatsapp_extract_bridge_state($statusResponse['data']) === 'conectado') {
            whatsapp_update_connection($empresaId, [
                'status' => 'conectado',
                'qr_code' => null,
                'qr_code_imagem' => null,
                'pairing_code' => null,
                'ultimo_erro' => null,
                'ultima_sincronizacao' => date('Y-m-d H:i:s'),
                'conectado_em' => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => true, 'message' => 'WhatsApp já conectado na bridge.'];
        }

        $qrResponse = whatsapp_bridge_fetch_qr_with_retry($instanceName);

        if (!$qrResponse['ok']) {
            whatsapp_update_connection($empresaId, [
                'status' => 'erro',
                'ultimo_erro' => $qrResponse['error'],
                'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => false, 'message' => (string) $qrResponse['error']];
        }

        $qrImage = whatsapp_extract_qr_image($qrResponse['data']);
        $qrCode = whatsapp_find_first_value($qrResponse['data'], ['code', 'qrCode', 'qrcode']);
        $message = (string) ($qrResponse['data']['message'] ?? '');
        $qrStatus = whatsapp_extract_bridge_state($qrResponse['data']);

        whatsapp_update_connection($empresaId, [
            'status' => $qrStatus === 'conectado' ? 'conectado' : 'conectando',
            'qr_code' => $qrStatus === 'conectado' ? null : $qrCode,
            'qr_code_imagem' => $qrStatus === 'conectado' ? null : $qrImage,
            'pairing_code' => null,
            'ultimo_erro' => null,
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            'conectado_em' => $qrStatus === 'conectado' ? date('Y-m-d H:i:s') : null,
        ]);

        if ($qrStatus === 'conectado') {
            return ['ok' => true, 'message' => 'WhatsApp já conectado na bridge.'];
        }

        return [
            'ok' => $qrImage !== null || $qrCode !== null,
            'message' => $qrImage !== null || $qrCode !== null
                ? 'QR Code gerado pela bridge. Leia com o WhatsApp da empresa.'
                : ($message !== '' ? $message : 'QR Code ainda não foi gerado pela bridge. Tente atualizar em alguns segundos.'),
        ];
    }

    $createResponse = whatsapp_api_request('POST', '/instance/create', [
        'instanceName' => $instanceName,
        'integration' => (string) env('WHATSAPP_INTEGRATION', 'WHATSAPP-BAILEYS'),
        'qrcode' => true,
        'groupsIgnore' => true,
        'alwaysOnline' => false,
        'readMessages' => false,
        'readStatus' => false,
        'syncFullHistory' => false,
    ]);

    if (!$createResponse['ok'] && !in_array((int) $createResponse['http_code'], [400, 403, 409], true)) {
        whatsapp_update_connection($empresaId, [
            'status' => 'erro',
            'ultimo_erro' => $createResponse['error'],
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => false, 'message' => (string) $createResponse['error']];
    }

    $connectResponse = whatsapp_api_request('GET', '/instance/connect/' . rawurlencode($instanceName));

    if (!$connectResponse['ok']) {
        whatsapp_update_connection($empresaId, [
            'status' => 'erro',
            'ultimo_erro' => $connectResponse['error'],
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => false, 'message' => (string) $connectResponse['error']];
    }

    $qrCode = whatsapp_find_first_value($connectResponse['data'], ['code', 'qr', 'qrCode', 'qrcode']);
    $qrImage = whatsapp_extract_qr_image($connectResponse['data']);
    $pairingCode = whatsapp_find_first_value($connectResponse['data'], ['pairingCode', 'pairing_code']);

    whatsapp_update_connection($empresaId, [
        'status' => 'conectando',
        'qr_code' => $qrCode,
        'qr_code_imagem' => $qrImage,
        'pairing_code' => $pairingCode,
        'ultimo_erro' => null,
        'ultima_sincronizacao' => date('Y-m-d H:i:s'),
    ]);

    return [
        'ok' => true,
        'message' => $qrImage !== null
            ? 'QR Code gerado. Leia com o WhatsApp da empresa.'
            : 'Conexão iniciada. O QR Code será renderizado localmente quando houver token disponível.',
    ];
}

function whatsapp_disconnect_instance(int $empresaId): array
{
    $connection = whatsapp_get_connection($empresaId);
    $instance = (string) ($connection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId));

    if (whatsapp_provider() === 'bridge' && whatsapp_bridge_configured()) {
        $response = whatsapp_bridge_request('POST', whatsapp_bridge_instance_path('/logout', $instance));

        if (!$response['ok'] && !in_array((int) $response['http_code'], [400, 404], true)) {
            whatsapp_update_connection($empresaId, [
                'status' => 'erro',
                'ultimo_erro' => $response['error'],
                'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => false, 'message' => (string) $response['error']];
        }
    } elseif (whatsapp_api_configured()) {
        $response = whatsapp_api_request('DELETE', '/instance/logout/' . rawurlencode($instance));

        if (!$response['ok'] && !in_array((int) $response['http_code'], [400, 404], true)) {
            whatsapp_update_connection($empresaId, [
                'status' => 'erro',
                'ultimo_erro' => $response['error'],
                'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => false, 'message' => (string) $response['error']];
        }
    }

    whatsapp_update_connection($empresaId, [
        'status' => 'desconectado',
        'qr_code' => null,
        'qr_code_imagem' => null,
        'pairing_code' => null,
        'ultimo_erro' => null,
        'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        'conectado_em' => null,
    ]);

    return ['ok' => true, 'message' => 'WhatsApp desconectado.'];
}

function whatsapp_insert_envio(
    int $empresaId,
    ?int $clienteId,
    ?int $cobrancaId,
    string $telefone,
    string $mensagem,
    string $tipo
): int {
    $stmt = db()->prepare(
        "INSERT INTO whatsapp_envios (
            empresa_id,
            cliente_id,
            cobranca_id,
            telefone,
            mensagem,
            tipo,
            status_envio,
            criado_em
        ) VALUES (
            :empresa_id,
            :cliente_id,
            :cobranca_id,
            :telefone,
            :mensagem,
            :tipo,
            'pendente',
            NOW()
        )"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':cliente_id' => $clienteId,
        ':cobranca_id' => $cobrancaId,
        ':telefone' => $telefone,
        ':mensagem' => $mensagem,
        ':tipo' => $tipo,
    ]);

    return (int) db()->lastInsertId();
}

function whatsapp_update_envio_status(int $envioId, string $status, array|string|null $retorno): void
{
    $encoded = is_array($retorno)
        ? json_encode($retorno, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : $retorno;

    $stmt = db()->prepare(
        "UPDATE whatsapp_envios
         SET status_envio = :status_envio, retorno_api = :retorno_api
         WHERE id = :id"
    );
    $stmt->execute([
        ':status_envio' => $status,
        ':retorno_api' => $encoded,
        ':id' => $envioId,
    ]);
}

function whatsapp_send_text(
    int $empresaId,
    string $telefone,
    string $mensagem,
    string $tipo = 'manual',
    ?int $clienteId = null,
    ?int $cobrancaId = null
): array {
    $telefoneNormalizado = whatsapp_normalize_phone($telefone);

    if ($telefoneNormalizado === null) {
        return ['ok' => false, 'message' => 'Telefone inválido para envio por WhatsApp.'];
    }

    $mensagem = trim($mensagem);

    if ($mensagem === '' || strlen($mensagem) > 2000) {
        return ['ok' => false, 'message' => 'A mensagem deve ter entre 1 e 2000 caracteres.'];
    }

    $envioId = whatsapp_insert_envio($empresaId, $clienteId, $cobrancaId, $telefoneNormalizado, $mensagem, $tipo);
    $connection = whatsapp_get_connection($empresaId);

    if (($connection['status'] ?? '') !== 'conectado') {
        whatsapp_refresh_connection($empresaId);
        $connection = whatsapp_get_connection($empresaId);
    }

    if (!whatsapp_integration_configured() || ($connection['status'] ?? '') !== 'conectado') {
        $message = !whatsapp_integration_configured()
            ? 'Integração de WhatsApp não configurada.'
            : 'WhatsApp da empresa não está conectado.';

        whatsapp_update_envio_status($envioId, 'falhou', $message);

        return ['ok' => false, 'message' => $message, 'envio_id' => $envioId];
    }

    $payload = [
        'number' => $telefoneNormalizado,
        'text' => $mensagem,
        'delay' => max(0, (int) env('WHATSAPP_SEND_DELAY_MS', '1200')),
        'linkPreview' => false,
    ];

    if (whatsapp_provider() === 'bridge') {
        $response = whatsapp_bridge_request('POST', '/send-message', [
            'instanceName' => (string) ($connection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId)),
            'number' => $telefoneNormalizado,
            'text' => $mensagem,
        ]);
    } else {
        $instance = (string) ($connection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId));
        $response = whatsapp_api_request('POST', '/message/sendText/' . rawurlencode($instance), $payload);
    }

    if (!$response['ok']) {
        whatsapp_update_envio_status($envioId, 'falhou', [
            'error' => $response['error'],
            'http_code' => $response['http_code'],
            'response' => $response['data'],
        ]);

        return ['ok' => false, 'message' => (string) $response['error'], 'envio_id' => $envioId];
    }

    whatsapp_update_envio_status($envioId, 'enviado', $response['data']);

    return ['ok' => true, 'message' => 'Mensagem enviada com sucesso.', 'envio_id' => $envioId];
}

function whatsapp_cobranca_tipo_por_dias(int $dias): ?string
{
    return match ($dias) {
        10 => 'cobranca_10_dias',
        5 => 'cobranca_5_dias',
        0 => 'cobranca_vencimento',
        -7 => 'cobranca_7_dias_atraso',
        default => null,
    };
}

function whatsapp_template_por_tipo(string $tipo, array $config): string
{
    $defaults = [
        'cobranca_10_dias' => 'Olá, {cliente}. Sua cobrança de {valor} vence em {vencimento}. Caso já tenha pago, desconsidere esta mensagem.',
        'cobranca_5_dias' => 'Olá, {cliente}. Passando para lembrar que sua cobrança de {valor} vence em {vencimento}.',
        'cobranca_vencimento' => 'Olá, {cliente}. Hoje é o vencimento da sua cobrança de {valor}. Chave PIX: {pix_chave}.',
        'cobranca_7_dias_atraso' => 'Olá, {cliente}. Identificamos uma cobrança vencida em {vencimento}, no valor de {valor}. Regularize para evitar bloqueio do serviço.',
    ];

    $column = [
        'cobranca_10_dias' => 'mensagem_10_dias',
        'cobranca_5_dias' => 'mensagem_5_dias',
        'cobranca_vencimento' => 'mensagem_dia_vencimento',
        'cobranca_7_dias_atraso' => 'mensagem_7_dias_atraso',
    ][$tipo] ?? null;

    if ($column !== null && !empty($config[$column])) {
        return (string) $config[$column];
    }

    return $defaults[$tipo] ?? $defaults['cobranca_vencimento'];
}

function whatsapp_montar_mensagem_cobranca(array $cobranca, array $config, string $tipo): string
{
    $template = whatsapp_template_por_tipo($tipo, $config);
    $saldo = (float) ($cobranca['saldo'] ?? $cobranca['valor'] ?? 0);

    $replacements = [
        '{cliente}' => (string) ($cobranca['cliente'] ?? 'cliente'),
        '{empresa}' => (string) ($config['empresa_nome'] ?? ''),
        '{valor}' => moeda_br($saldo),
        '{vencimento}' => data_br((string) ($cobranca['data_vencimento'] ?? '')),
        '{referencia}' => (string) ($cobranca['referencia'] ?? ''),
        '{pix_chave}' => (string) ($config['pix_chave'] ?? ''),
        '{pix_nome}' => (string) ($config['pix_nome_recebedor'] ?? ''),
    ];

    return trim(strtr($template, $replacements));
}

function whatsapp_cobranca_envio_ja_existe(int $empresaId, int $cobrancaId, string $tipo): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM whatsapp_envios
         WHERE empresa_id = :empresa_id
           AND cobranca_id = :cobranca_id
           AND tipo = :tipo
           AND status_envio IN ('pendente','enviado')"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':cobranca_id' => $cobrancaId,
        ':tipo' => $tipo,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function whatsapp_processar_cobrancas_empresa(int $empresaId): array
{
    $summary = [
        'enviadas' => 0,
        'falhas' => 0,
        'ignoradas' => 0,
        'duplicadas' => 0,
        'sem_telefone' => 0,
    ];

    $stmt = db()->prepare('SELECT * FROM configuracoes_automacao WHERE empresa_id = :empresa_id LIMIT 1');
    $stmt->execute([':empresa_id' => $empresaId]);
    $config = $stmt->fetch() ?: [];

    if ((int) ($config['automacao_ativa'] ?? 1) !== 1) {
        $summary['ignoradas']++;
        return $summary;
    }

    $stmt = db()->prepare(
        "SELECT
            cb.id,
            cb.cliente_id,
            cb.referencia,
            cb.valor,
            cb.data_vencimento,
            cb.status,
            c.nome AS cliente,
            c.telefone,
            COALESCE(pg.total_pago, 0) AS total_pago,
            GREATEST(cb.valor - COALESCE(pg.total_pago, 0), 0) AS saldo,
            DATEDIFF(cb.data_vencimento, CURDATE()) AS dias_para_vencimento
         FROM cobrancas cb
         INNER JOIN clientes c ON c.id = cb.cliente_id AND c.empresa_id = cb.empresa_id
         LEFT JOIN (
            SELECT empresa_id, cobranca_id, SUM(valor_pago) AS total_pago
            FROM pagamentos
            WHERE empresa_id = :empresa_pagamentos
            GROUP BY empresa_id, cobranca_id
         ) pg ON pg.cobranca_id = cb.id AND pg.empresa_id = cb.empresa_id
         WHERE cb.empresa_id = :empresa_id
           AND cb.status IN ('Em aberto','Vencida')
           AND cb.data_vencimento BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
           AND GREATEST(cb.valor - COALESCE(pg.total_pago, 0), 0) > 0
         ORDER BY cb.data_vencimento ASC, cb.id ASC"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':empresa_pagamentos' => $empresaId,
    ]);

    foreach ($stmt->fetchAll() as $cobranca) {
        $tipo = whatsapp_cobranca_tipo_por_dias((int) $cobranca['dias_para_vencimento']);

        if ($tipo === null) {
            $summary['ignoradas']++;
            continue;
        }

        if (whatsapp_cobranca_envio_ja_existe($empresaId, (int) $cobranca['id'], $tipo)) {
            $summary['duplicadas']++;
            continue;
        }

        if (whatsapp_normalize_phone((string) ($cobranca['telefone'] ?? '')) === null) {
            $summary['sem_telefone']++;
            continue;
        }

        $mensagem = whatsapp_montar_mensagem_cobranca($cobranca, $config, $tipo);
        $result = whatsapp_send_text(
            $empresaId,
            (string) $cobranca['telefone'],
            $mensagem,
            $tipo,
            (int) $cobranca['cliente_id'],
            (int) $cobranca['id']
        );

        if ($result['ok']) {
            $summary['enviadas']++;
            continue;
        }

        $summary['falhas']++;
    }

    return $summary;
}

function whatsapp_processar_cobrancas_todas_empresas(): array
{
    $result = ['empresas' => 0, 'enviadas' => 0, 'falhas' => 0, 'ignoradas' => 0, 'duplicadas' => 0, 'sem_telefone' => 0];

    $stmt = db()->query(
        "SELECT e.id
         FROM empresas e
         INNER JOIN whatsapp_conexoes wc ON wc.empresa_id = e.id AND wc.status = 'conectado'
         LEFT JOIN configuracoes_automacao ca ON ca.empresa_id = e.id
         WHERE e.status IN ('teste','ativa')"
        . " AND COALESCE(ca.automacao_ativa, 1) = 1"
    );

    foreach ($stmt->fetchAll() as $empresa) {
        $result['empresas']++;
        $summary = whatsapp_processar_cobrancas_empresa((int) $empresa['id']);

        foreach (['enviadas', 'falhas', 'ignoradas', 'duplicadas', 'sem_telefone'] as $key) {
            $result[$key] += (int) $summary[$key];
        }
    }

    return $result;
}

function whatsapp_http_post_json(string $url, array $payload, int $timeout = 35): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        return ['ok' => false, 'http_code' => 0, 'data' => null, 'raw' => '', 'error' => 'Payload JSON inválido.'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ],
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'http_code' => $httpCode, 'data' => null, 'raw' => '', 'error' => $error ?: 'Falha na requisição HTTP.'];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Accept: application/json\r\nContent-Type: application/json",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $httpCode = 0;

        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                $httpCode = (int) $matches[1];
                break;
            }
        }

        if ($raw === false) {
            return ['ok' => false, 'http_code' => $httpCode, 'data' => null, 'raw' => '', 'error' => 'Falha na requisição HTTP.'];
        }
    }

    $decoded = json_decode((string) $raw, true);
    $data = is_array($decoded) ? $decoded : ['raw' => (string) $raw];

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => $data,
        'raw' => (string) $raw,
        'error' => $httpCode >= 200 && $httpCode < 300 ? null : whatsapp_api_error_message($data, $httpCode),
    ];
}

function whatsapp_parse_json_from_ai_text(string $text): ?array
{
    $text = trim(str_replace(['```json', '```'], '', $text));

    if ($text === '') {
        return null;
    }

    $decoded = json_decode($text, true);

    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{.*\}/s', $text, $matches)) {
        $decoded = json_decode($matches[0], true);

        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function whatsapp_date_from_ai(mixed $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);

        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
}

function whatsapp_validar_comprovante_com_gemini(string $apiKey, string $mediaBase64, string $mimeType, float $valorEsperado = 0): array
{
    $model = trim((string) env('GEMINI_MODEL', 'gemini-1.5-flash')) ?: 'gemini-1.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $valorTexto = $valorEsperado > 0 ? moeda_br($valorEsperado) : 'o valor presente no comprovante';

    $prompt = "Analise o arquivo recebido como comprovante de pagamento PIX, transferencia, boleto ou deposito. "
        . "Extraia somente dados visiveis no comprovante. Considere valido apenas se o pagamento estiver concluido, efetuado, aprovado ou liquidado. "
        . "O valor esperado de referencia e {$valorTexto}, mas retorne o valor realmente pago. "
        . "Responda apenas JSON com: valido (boolean), valor (number), data (YYYY-MM-DD se houver), motivo (string).";

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $mediaBase64,
                ]],
            ],
        ]],
        'generationConfig' => [
            'temperature' => 0.1,
            'response_mime_type' => 'application/json',
        ],
    ];

    $response = whatsapp_http_post_json($url, $payload);

    if (!$response['ok']) {
        return [
            'valido' => false,
            'valor' => 0.0,
            'data' => null,
            'motivo' => (string) ($response['error'] ?? 'Falha ao consultar IA.'),
            'raw' => $response['data'],
        ];
    }

    $text = (string) ($response['data']['candidates'][0]['content']['parts'][0]['text'] ?? '');
    $json = whatsapp_parse_json_from_ai_text($text);

    if (!$json) {
        return [
            'valido' => false,
            'valor' => 0.0,
            'data' => null,
            'motivo' => 'A IA não retornou JSON válido.',
            'raw' => $response['data'],
        ];
    }

    $valor = is_numeric($json['valor'] ?? null)
        ? (float) $json['valor']
        : decimal_from_input((string) ($json['valor'] ?? '0'));

    return [
        'valido' => filter_var($json['valido'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'valor' => round($valor, 2),
        'data' => whatsapp_date_from_ai($json['data'] ?? null),
        'motivo' => trim((string) ($json['motivo'] ?? '')),
        'raw' => $json,
    ];
}

function whatsapp_find_empresa_by_instance(string $instanceName): ?int
{
    whatsapp_ensure_tables();

    $instanceName = whatsapp_sanitize_instance_name($instanceName, 0);
    $stmt = db()->prepare('SELECT empresa_id FROM whatsapp_conexoes WHERE instancia_nome = :instancia_nome LIMIT 1');
    $stmt->execute([':instancia_nome' => $instanceName]);
    $empresaId = $stmt->fetchColumn();

    if ($empresaId !== false) {
        return (int) $empresaId;
    }

    $stmt = db()->query('SELECT empresa_id FROM whatsapp_conexoes ORDER BY id ASC LIMIT 2');
    $rows = $stmt->fetchAll();

    return count($rows) === 1 ? (int) $rows[0]['empresa_id'] : null;
}

function whatsapp_find_cliente_by_sender(int $empresaId, string $sender): ?array
{
    $senderDigits = only_digits($sender);
    $senderNormalized = whatsapp_normalize_phone($senderDigits) ?? $senderDigits;
    $senderSuffix = substr($senderNormalized, -8);

    $stmt = db()->prepare(
        "SELECT id, nome, telefone
         FROM clientes
         WHERE empresa_id = :empresa_id
           AND telefone IS NOT NULL
           AND telefone <> ''
           AND status <> 'cancelado'
         ORDER BY id ASC"
    );
    $stmt->execute([':empresa_id' => $empresaId]);
    $clientes = $stmt->fetchAll();

    foreach ($clientes as $cliente) {
        $clienteDigits = only_digits((string) ($cliente['telefone'] ?? ''));
        $clienteNormalized = whatsapp_normalize_phone($clienteDigits) ?? $clienteDigits;

        if ($clienteNormalized !== '' && $clienteNormalized === $senderNormalized) {
            return $cliente;
        }
    }

    foreach ($clientes as $cliente) {
        $clienteDigits = only_digits((string) ($cliente['telefone'] ?? ''));

        if ($senderSuffix !== '' && substr($clienteDigits, -8) === $senderSuffix) {
            return $cliente;
        }
    }

    return null;
}

function whatsapp_cliente_cobrancas_abertas(int $empresaId, int $clienteId): array
{
    $stmt = db()->prepare(
        "SELECT
            cb.id,
            cb.cliente_id,
            cb.referencia,
            cb.valor,
            cb.data_vencimento,
            cb.status,
            COALESCE(pg.total_pago, 0) AS total_pago,
            GREATEST(cb.valor - COALESCE(pg.total_pago, 0), 0) AS saldo
         FROM cobrancas cb
         LEFT JOIN (
            SELECT empresa_id, cobranca_id, SUM(valor_pago) AS total_pago
            FROM pagamentos
            WHERE empresa_id = :empresa_pagamentos
            GROUP BY empresa_id, cobranca_id
         ) pg ON pg.cobranca_id = cb.id AND pg.empresa_id = cb.empresa_id
         WHERE cb.empresa_id = :empresa_id
           AND cb.cliente_id = :cliente_id
           AND cb.status IN ('Em aberto','Vencida')
         HAVING saldo > 0
         ORDER BY cb.data_vencimento ASC, cb.id ASC"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':empresa_pagamentos' => $empresaId,
        ':cliente_id' => $clienteId,
    ]);

    return $stmt->fetchAll();
}

function whatsapp_escolher_cobranca_para_pagamento(int $empresaId, int $clienteId, float $valorPago): ?array
{
    $cobrancas = whatsapp_cliente_cobrancas_abertas($empresaId, $clienteId);
    $elegiveis = array_values(array_filter($cobrancas, static fn (array $cobranca): bool => $valorPago <= ((float) $cobranca['saldo'] + 0.005)));

    if (!$elegiveis) {
        return null;
    }

    $exatas = array_values(array_filter($elegiveis, static fn (array $cobranca): bool => abs((float) $cobranca['saldo'] - $valorPago) <= 0.05));

    if (count($exatas) === 1) {
        return $exatas[0];
    }

    if (count($exatas) > 1) {
        return null;
    }

    return $elegiveis[0];
}

function whatsapp_get_comprovante_by_message(int $empresaId, string $messageId): ?array
{
    $stmt = db()->prepare(
        "SELECT *
         FROM whatsapp_comprovantes
         WHERE empresa_id = :empresa_id
           AND mensagem_id = :mensagem_id
         LIMIT 1"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':mensagem_id' => $messageId,
    ]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function whatsapp_insert_comprovante_recebido(int $empresaId, ?int $clienteId, string $messageId, string $sender, string $mimeType): int
{
    $stmt = db()->prepare(
        "INSERT INTO whatsapp_comprovantes (
            empresa_id,
            cliente_id,
            mensagem_id,
            remetente,
            mime_type,
            status_processamento,
            criado_em
         ) VALUES (
            :empresa_id,
            :cliente_id,
            :mensagem_id,
            :remetente,
            :mime_type,
            'recebido',
            NOW()
         )"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':cliente_id' => $clienteId,
        ':mensagem_id' => $messageId,
        ':remetente' => $sender,
        ':mime_type' => $mimeType,
    ]);

    return (int) db()->lastInsertId();
}

function whatsapp_update_comprovante(int $id, array $data): void
{
    $allowed = [
        'cliente_id',
        'cobranca_id',
        'valor_detectado',
        'data_detectada',
        'status_processamento',
        'motivo',
        'resposta_ia',
    ];

    $fields = [];
    $params = [':id' => $id];

    foreach ($data as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }

        $param = ':' . $key;
        $fields[] = $key . ' = ' . $param;
        $params[$param] = $value;
    }

    if (!$fields) {
        return;
    }

    $fields[] = 'atualizado_em = NOW()';
    $stmt = db()->prepare('UPDATE whatsapp_comprovantes SET ' . implode(', ', $fields) . ' WHERE id = :id');
    $stmt->execute($params);
}

function whatsapp_atualizar_status_cobranca(int $empresaId, int $cobrancaId): void
{
    $stmt = db()->prepare(
        "SELECT cb.valor, cb.data_vencimento, COALESCE(SUM(p.valor_pago), 0) AS total_pago
         FROM cobrancas cb
         LEFT JOIN pagamentos p ON p.cobranca_id = cb.id AND p.empresa_id = cb.empresa_id
         WHERE cb.id = :cobranca_id AND cb.empresa_id = :empresa_id
         GROUP BY cb.id, cb.valor, cb.data_vencimento"
    );
    $stmt->execute([
        ':cobranca_id' => $cobrancaId,
        ':empresa_id' => $empresaId,
    ]);
    $cobranca = $stmt->fetch();

    if (!$cobranca) {
        return;
    }

    $novoStatus = ((float) $cobranca['total_pago'] + 0.005) >= (float) $cobranca['valor']
        ? 'Paga'
        : (strtotime((string) $cobranca['data_vencimento']) < strtotime(date('Y-m-d')) ? 'Vencida' : 'Em aberto');

    db()->prepare(
        "UPDATE cobrancas
         SET status = :status, atualizado_em = NOW()
         WHERE id = :id AND empresa_id = :empresa_id"
    )->execute([
        ':status' => $novoStatus,
        ':id' => $cobrancaId,
        ':empresa_id' => $empresaId,
    ]);
}

function whatsapp_enviar_resposta_comprovante(int $empresaId, string $telefone, string $mensagem, ?int $clienteId = null): void
{
    try {
        whatsapp_send_text($empresaId, $telefone, $mensagem, 'confirmacao', $clienteId, null);
    } catch (Throwable $e) {
        error_log('[WHATSAPP COMPROVANTE RESPOSTA] ' . $e->getMessage());
    }
}

function whatsapp_processar_comprovante_recebido(array $input): array
{
    whatsapp_ensure_tables();

    $instanceName = trim((string) ($input['instanceName'] ?? $input['instance'] ?? ''));
    $sender = only_digits((string) ($input['sender'] ?? ''));
    $mediaBase64 = trim((string) ($input['media'] ?? ''));
    $mimeType = trim((string) ($input['mimeType'] ?? 'image/jpeg'));

    if ($instanceName === '' || $sender === '' || $mediaBase64 === '') {
        return ['ok' => false, 'status' => 'payload_invalido', 'message' => 'Payload de comprovante inválido.'];
    }

    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
        return ['ok' => false, 'status' => 'mime_invalido', 'message' => 'Tipo de arquivo não suportado para comprovante.'];
    }

    $empresaId = whatsapp_find_empresa_by_instance($instanceName);

    if ($empresaId === null) {
        return ['ok' => false, 'status' => 'empresa_nao_encontrada', 'message' => 'Instância de WhatsApp não vinculada a uma empresa.'];
    }

    $messageId = trim((string) ($input['messageId'] ?? ''));

    if ($messageId === '') {
        $messageId = sha1($instanceName . '|' . $sender . '|' . substr($mediaBase64, 0, 512));
    }

    $senderNormalized = whatsapp_normalize_phone($sender) ?? $sender;
    $existing = whatsapp_get_comprovante_by_message($empresaId, $messageId);

    if ($existing && (string) $existing['status_processamento'] === 'confirmado') {
        return ['ok' => true, 'status' => 'duplicado', 'message' => 'Comprovante já processado.'];
    }

    $cliente = whatsapp_find_cliente_by_sender($empresaId, $senderNormalized);
    $comprovanteId = $existing
        ? (int) $existing['id']
        : whatsapp_insert_comprovante_recebido($empresaId, $cliente ? (int) $cliente['id'] : null, $messageId, $senderNormalized, $mimeType);

    if (!$cliente) {
        whatsapp_update_comprovante($comprovanteId, [
            'status_processamento' => 'recusado',
            'motivo' => 'Telefone não encontrado na carteira de clientes da empresa.',
        ]);

        return ['ok' => true, 'status' => 'cliente_nao_encontrado'];
    }

    $stmt = db()->prepare('SELECT * FROM configuracoes_automacao WHERE empresa_id = :empresa_id LIMIT 1');
    $stmt->execute([':empresa_id' => $empresaId]);
    $config = $stmt->fetch() ?: [];
    $apiKey = trim((string) ($config['gemini_api_key'] ?? env('GEMINI_API_KEY', '')));

    if ($apiKey === '') {
        whatsapp_update_comprovante($comprovanteId, [
            'cliente_id' => (int) $cliente['id'],
            'status_processamento' => 'pendente',
            'motivo' => 'Chave Gemini não configurada. Conferência manual necessária.',
        ]);

        whatsapp_enviar_resposta_comprovante(
            $empresaId,
            $senderNormalized,
            'Recebemos seu comprovante. Ele ficará aguardando conferência manual.',
            (int) $cliente['id']
        );

        return ['ok' => true, 'status' => 'pendente_conferencia_manual'];
    }

    $cobrancas = whatsapp_cliente_cobrancas_abertas($empresaId, (int) $cliente['id']);
    $valorEsperado = $cobrancas ? (float) $cobrancas[0]['saldo'] : 0.0;
    $resultadoIA = whatsapp_validar_comprovante_com_gemini($apiKey, $mediaBase64, $mimeType, $valorEsperado);
    $respostaIa = json_encode($resultadoIA['raw'] ?? $resultadoIA, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!$resultadoIA['valido']) {
        whatsapp_update_comprovante($comprovanteId, [
            'cliente_id' => (int) $cliente['id'],
            'valor_detectado' => (float) ($resultadoIA['valor'] ?? 0),
            'data_detectada' => $resultadoIA['data'] ?? null,
            'status_processamento' => 'recusado',
            'motivo' => $resultadoIA['motivo'] ?: 'Comprovante não validado pela IA.',
            'resposta_ia' => $respostaIa,
        ]);

        return ['ok' => true, 'status' => 'recusado', 'motivo' => $resultadoIA['motivo']];
    }

    $valorPago = round((float) $resultadoIA['valor'], 2);

    if ($valorPago <= 0) {
        whatsapp_update_comprovante($comprovanteId, [
            'cliente_id' => (int) $cliente['id'],
            'status_processamento' => 'pendente',
            'motivo' => 'Valor do comprovante não foi identificado.',
            'resposta_ia' => $respostaIa,
        ]);

        return ['ok' => true, 'status' => 'pendente_valor_nao_identificado'];
    }

    $cobranca = whatsapp_escolher_cobranca_para_pagamento($empresaId, (int) $cliente['id'], $valorPago);

    if (!$cobranca) {
        whatsapp_update_comprovante($comprovanteId, [
            'cliente_id' => (int) $cliente['id'],
            'valor_detectado' => $valorPago,
            'data_detectada' => $resultadoIA['data'] ?? null,
            'status_processamento' => 'pendente',
            'motivo' => 'Não foi encontrada uma cobrança aberta compatível com o valor do comprovante.',
            'resposta_ia' => $respostaIa,
        ]);

        whatsapp_enviar_resposta_comprovante(
            $empresaId,
            $senderNormalized,
            'Recebemos seu comprovante e ele ficará aguardando conferência manual.',
            (int) $cliente['id']
        );

        return ['ok' => true, 'status' => 'pendente_cobranca_nao_encontrada'];
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare(
            "SELECT id, cliente_id, valor, data_vencimento, status
             FROM cobrancas
             WHERE id = :id
               AND empresa_id = :empresa_id
             FOR UPDATE"
        );
        $stmt->execute([
            ':id' => (int) $cobranca['id'],
            ':empresa_id' => $empresaId,
        ]);
        $cobrancaAtual = $stmt->fetch();

        if (!$cobrancaAtual || (int) $cobrancaAtual['cliente_id'] !== (int) $cliente['id']) {
            throw new RuntimeException('Cobrança não encontrada durante a baixa automática.');
        }

        $stmt = db()->prepare(
            "SELECT COALESCE(SUM(valor_pago), 0)
             FROM pagamentos
             WHERE cobranca_id = :cobranca_id
               AND empresa_id = :empresa_id"
        );
        $stmt->execute([
            ':cobranca_id' => (int) $cobrancaAtual['id'],
            ':empresa_id' => $empresaId,
        ]);
        $saldo = round((float) $cobrancaAtual['valor'] - (float) $stmt->fetchColumn(), 2);

        if ($saldo <= 0 || $valorPago > $saldo + 0.005) {
            whatsapp_update_comprovante($comprovanteId, [
                'cliente_id' => (int) $cliente['id'],
                'cobranca_id' => (int) $cobrancaAtual['id'],
                'valor_detectado' => $valorPago,
                'data_detectada' => $resultadoIA['data'] ?? null,
                'status_processamento' => 'pendente',
                'motivo' => 'Valor do comprovante maior que o saldo atual da cobrança.',
                'resposta_ia' => $respostaIa,
            ]);

            db()->commit();

            return ['ok' => true, 'status' => 'pendente_valor_maior_que_saldo'];
        }

        $dataPagamento = $resultadoIA['data'] ?: date('Y-m-d');
        $stmt = db()->prepare(
            "INSERT INTO pagamentos (
                empresa_id,
                cliente_id,
                cobranca_id,
                valor_pago,
                data_pagamento,
                forma_pagamento,
                comprovante_arquivo,
                observacao,
                criado_em
            ) VALUES (
                :empresa_id,
                :cliente_id,
                :cobranca_id,
                :valor_pago,
                :data_pagamento,
                'PIX',
                :comprovante_arquivo,
                :observacao,
                NOW()
            )"
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => (int) $cliente['id'],
            ':cobranca_id' => (int) $cobrancaAtual['id'],
            ':valor_pago' => $valorPago,
            ':data_pagamento' => $dataPagamento,
            ':comprovante_arquivo' => 'whatsapp:' . $messageId,
            ':observacao' => 'Baixa automática por comprovante recebido no WhatsApp.',
        ]);

        whatsapp_atualizar_status_cobranca($empresaId, (int) $cobrancaAtual['id']);

        whatsapp_update_comprovante($comprovanteId, [
            'cliente_id' => (int) $cliente['id'],
            'cobranca_id' => (int) $cobrancaAtual['id'],
            'valor_detectado' => $valorPago,
            'data_detectada' => $dataPagamento,
            'status_processamento' => 'confirmado',
            'motivo' => 'Pagamento registrado automaticamente.',
            'resposta_ia' => $respostaIa,
        ]);

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        whatsapp_update_comprovante($comprovanteId, [
            'status_processamento' => 'erro',
            'motivo' => 'Erro interno ao registrar pagamento.',
            'resposta_ia' => $respostaIa,
        ]);

        error_log('[WHATSAPP COMPROVANTE] ' . $e->getMessage());

        return ['ok' => false, 'status' => 'erro_pagamento', 'message' => 'Erro ao registrar pagamento.'];
    }

    whatsapp_enviar_resposta_comprovante(
        $empresaId,
        $senderNormalized,
        'Pagamento confirmado. Recebemos seu comprovante de ' . moeda_br($valorPago) . '. Obrigado!',
        (int) $cliente['id']
    );

    return ['ok' => true, 'status' => 'confirmado', 'valor' => $valorPago, 'cobranca_id' => (int) $cobranca['id']];
}
