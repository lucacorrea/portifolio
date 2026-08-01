<?php

declare(strict_types=1);

/**
 * Bootstrap da API Flux Empresas → SO.
 *
 * Responsabilidades:
 * - carregar as configurações privadas do SO e da integração;
 * - criar uma conexão PDO exclusiva para a API;
 * - aceitar somente HTTPS, POST e JSON;
 * - validar cliente, timestamp, nonce e assinatura HMAC-SHA256;
 * - impedir replay da mesma requisição;
 * - limitar o tamanho do payload;
 * - responder sempre em JSON sem expor informações internas.
 *
 * Local esperado:
 * SO/api/integracoes/fluxempresa/bootstrap.php
 *
 * Compatibilidade: PHP 7.2+.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

/** @var string $fluxApiRequestId */
$fluxApiRequestId = '';

try {
    $fluxApiRequestId = bin2hex(random_bytes(16));
} catch (Throwable $exception) {
    $fluxApiRequestId = hash(
        'sha256',
        uniqid('', true) . microtime(true)
    );
}

header('X-Request-Id: ' . $fluxApiRequestId);

if (
    isset($_SERVER['REQUEST_METHOD'])
    && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'OPTIONS'
) {
    /*
     * A integração é servidor-servidor e não expõe CORS.
     */
    http_response_code(204);
    exit;
}

/**
 * Envia uma resposta JSON e encerra a execução.
 *
 * @param int   $statusCode
 * @param array $payload
 * @return void
 */
function flux_api_response($statusCode, array $payload)
{
    $requestId = isset($GLOBALS['fluxApiRequestId'])
        ? (string) $GLOBALS['fluxApiRequestId']
        : '';

    if ($requestId !== '' && !isset($payload['request_id'])) {
        $payload['request_id'] = $requestId;
    }

    http_response_code((int) $statusCode);

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        http_response_code(500);

        echo json_encode(
            array(
                'success' => false,
                'error' => array(
                    'code' => 'JSON_ENCODING_ERROR',
                    'message' => 'Não foi possível gerar a resposta.',
                ),
                'request_id' => $requestId,
            ),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    echo $json;
    exit;
}

/**
 * Resposta padronizada de erro.
 *
 * @param int        $statusCode
 * @param string     $code
 * @param string     $message
 * @param array|null $details
 * @return void
 */
function flux_api_error(
    $statusCode,
    $code,
    $message,
    array $details = null
) {
    $error = array(
        'code' => (string) $code,
        'message' => (string) $message,
    );

    if ($details !== null && $details !== array()) {
        $error['details'] = $details;
    }

    flux_api_response(
        (int) $statusCode,
        array(
            'success' => false,
            'error' => $error,
        )
    );
}

/**
 * Retorna um header HTTP.
 *
 * Compatível com Apache, Nginx e FastCGI.
 *
 * @param string $name
 * @return string
 */
function flux_api_header($name)
{
    $normalized = strtoupper(
        str_replace(
            '-',
            '_',
            trim((string) $name)
        )
    );

    $serverKey = 'HTTP_' . $normalized;

    if (isset($_SERVER[$serverKey])) {
        return trim((string) $_SERVER[$serverKey]);
    }

    if ($normalized === 'CONTENT_TYPE') {
        return trim(
            (string) (
                isset($_SERVER['CONTENT_TYPE'])
                    ? $_SERVER['CONTENT_TYPE']
                    : ''
            )
        );
    }

    if ($normalized === 'CONTENT_LENGTH') {
        return trim(
            (string) (
                isset($_SERVER['CONTENT_LENGTH'])
                    ? $_SERVER['CONTENT_LENGTH']
                    : ''
            )
        );
    }

    return '';
}

/**
 * Verifica se a requisição chegou por HTTPS.
 *
 * X-Forwarded-Proto só é considerado quando o proxy estiver
 * explicitamente habilitado na configuração privada.
 *
 * @param bool $trustProxy
 * @return bool
 */
function flux_api_is_https($trustProxy)
{
    if (
        isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off'
    ) {
        return true;
    }

    if (
        isset($_SERVER['SERVER_PORT'])
        && (int) $_SERVER['SERVER_PORT'] === 443
    ) {
        return true;
    }

    if (!$trustProxy) {
        return false;
    }

    $forwardedProto = strtolower(
        flux_api_header('X-Forwarded-Proto')
    );

    if (strpos($forwardedProto, ',') !== false) {
        $parts = explode(',', $forwardedProto);
        $forwardedProto = trim((string) $parts[0]);
    }

    return $forwardedProto === 'https';
}

/**
 * Lê um arquivo .env simples sem usar putenv().
 *
 * @param string $path
 * @return array
 */
function flux_api_read_env_file($path)
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException(
            'Arquivo privado da integração não encontrado.'
        );
    }

    $lines = file(
        $path,
        FILE_IGNORE_NEW_LINES
    );

    if ($lines === false) {
        throw new RuntimeException(
            'Arquivo privado da integração não pôde ser lido.'
        );
    }

    $values = array();

    foreach ($lines as $index => $line) {
        if ($index === 0) {
            $line = preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                (string) $line
            );
        }

        $line = trim((string) $line);

        if (
            $line === ''
            || strpos($line, '#') === 0
            || strpos($line, '=') === false
        ) {
            continue;
        }

        list($key, $value) = explode(
            '=',
            $line,
            2
        );

        $key = trim((string) $key);
        $value = trim((string) $value);

        if (
            strlen($value) >= 2
            && (
                (
                    $value[0] === '"'
                    && substr($value, -1) === '"'
                )
                || (
                    $value[0] === "'"
                    && substr($value, -1) === "'"
                )
            )
        ) {
            $value = substr(
                $value,
                1,
                -1
            );
        }

        if (
            preg_match(
                '/^[A-Z][A-Z0-9_]*$/',
                $key
            ) !== 1
        ) {
            continue;
        }

        $values[$key] = $value;
    }

    return $values;
}

/**
 * Resolve o diretório privado das configurações.
 *
 * Estrutura esperada:
 *
 * public_html/
 * ├── SO/
 * └── configuracoes/so/
 *     ├── .env
 *     └── api.env
 *
 * O diretório fora de public_html tem prioridade quando existir.
 *
 * @return string
 */
function flux_api_resolve_config_directory()
{
    /*
     * __DIR__ = SO/api/integracoes/fluxempresa
     */
    $soRoot = dirname(__DIR__, 3);
    $publicHtml = dirname($soRoot);
    $accountRoot = dirname($publicHtml);

    $explicitDirectory = getenv(
        'SO_PRIVATE_CONFIG_DIR'
    );

    $candidates = array();

    if (
        is_string($explicitDirectory)
        && trim($explicitDirectory) !== ''
    ) {
        $candidates[] = rtrim(
            trim($explicitDirectory),
            DIRECTORY_SEPARATOR
        );
    }

    $candidates[] = $accountRoot
        . '/configuracoes/so';

    $candidates[] = $publicHtml
        . '/configuracoes/so';

    /*
     * Compatibilidade adicional. Não é o local recomendado.
     */
    $candidates[] = $soRoot
        . '/configuracoes/so';

    foreach ($candidates as $directory) {
        if (!is_dir($directory)) {
            continue;
        }

        if (
            is_readable($directory . '/.env')
            || is_readable($directory . '/api.env')
        ) {
            return $directory;
        }
    }

    throw new RuntimeException(
        'Diretório privado da integração não localizado.'
    );
}

/**
 * Carrega .env e api.env. O api.env sobrescreve somente as chaves
 * repetidas do .env.
 *
 * @param string $directory
 * @return array
 */
function flux_api_load_config($directory)
{
    $config = array();

    $mainEnv = rtrim(
        (string) $directory,
        DIRECTORY_SEPARATOR
    ) . '/.env';

    $apiEnv = rtrim(
        (string) $directory,
        DIRECTORY_SEPARATOR
    ) . '/api.env';

    if (is_file($mainEnv) && is_readable($mainEnv)) {
        $config = flux_api_read_env_file(
            $mainEnv
        );
    }

    if (is_file($apiEnv) && is_readable($apiEnv)) {
        $config = array_merge(
            $config,
            flux_api_read_env_file($apiEnv)
        );
    }

    if ($config === array()) {
        throw new RuntimeException(
            'Nenhuma configuração privada foi carregada.'
        );
    }

    return $config;
}

/**
 * Busca configuração obrigatória.
 *
 * @param array  $config
 * @param string $key
 * @return string
 */
function flux_api_config_required(array $config, $key)
{
    $value = isset($config[$key])
        ? trim((string) $config[$key])
        : '';

    if ($value === '') {
        throw new RuntimeException(
            'Configuração obrigatória ausente: '
            . $key
        );
    }

    if (strpos($value, "\0") !== false) {
        throw new RuntimeException(
            'Configuração inválida: '
            . $key
        );
    }

    return $value;
}

/**
 * Converte uma configuração booleana.
 *
 * @param array  $config
 * @param string $key
 * @param bool   $default
 * @return bool
 */
function flux_api_config_bool(
    array $config,
    $key,
    $default
) {
    if (!array_key_exists($key, $config)) {
        return (bool) $default;
    }

    $value = strtolower(
        trim((string) $config[$key])
    );

    return in_array(
        $value,
        array(
            '1',
            'true',
            'yes',
            'sim',
            'on',
        ),
        true
    );
}

/**
 * Converte uma configuração inteira com limites.
 *
 * @param array  $config
 * @param string $key
 * @param int    $default
 * @param int    $minimum
 * @param int    $maximum
 * @return int
 */
function flux_api_config_int(
    array $config,
    $key,
    $default,
    $minimum,
    $maximum
) {
    $value = isset($config[$key])
        ? filter_var(
            $config[$key],
            FILTER_VALIDATE_INT
        )
        : false;

    if ($value === false) {
        $value = (int) $default;
    }

    return max(
        (int) $minimum,
        min(
            (int) $maximum,
            (int) $value
        )
    );
}

/**
 * Cria uma conexão PDO exclusiva da API.
 *
 * Não carrega config/database.php, evitando sessão, saída HTML,
 * automigrações e credenciais versionadas no código legado.
 *
 * @param array $config
 * @return PDO
 */
function flux_api_create_pdo(array $config)
{
    $host = flux_api_config_required(
        $config,
        'DB_HOST'
    );

    $database = flux_api_config_required(
        $config,
        'DB_DATABASE'
    );

    $username = flux_api_config_required(
        $config,
        'DB_USERNAME'
    );

    $password = isset($config['DB_PASSWORD'])
        ? (string) $config['DB_PASSWORD']
        : '';

    $charset = isset($config['DB_CHARSET'])
        ? strtolower(
            trim((string) $config['DB_CHARSET'])
        )
        : 'utf8mb4';

    $port = flux_api_config_int(
        $config,
        'DB_PORT',
        3306,
        1,
        65535
    );

    if (
        preg_match(
            '/^[A-Za-z0-9._:\-]+$/D',
            $host
        ) !== 1
    ) {
        throw new RuntimeException(
            'DB_HOST inválido.'
        );
    }

    if (
        preg_match(
            '/^[A-Za-z0-9_]+$/D',
            $database
        ) !== 1
    ) {
        throw new RuntimeException(
            'DB_DATABASE inválido.'
        );
    }

    if (
        preg_match(
            '/^[A-Za-z0-9_]+$/D',
            $username
        ) !== 1
    ) {
        throw new RuntimeException(
            'DB_USERNAME inválido.'
        );
    }

    if (!in_array($charset, array('utf8mb4', 'utf8'), true)) {
        throw new RuntimeException(
            'DB_CHARSET inválido.'
        );
    }

    $dsn = 'mysql:host=' . $host
        . ';port=' . $port
        . ';dbname=' . $database
        . ';charset=' . $charset;

    return new PDO(
        $dsn,
        $username,
        $password,
        array(
            PDO::ATTR_ERRMODE
                => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE
                => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES
                => false,
            PDO::ATTR_STRINGIFY_FETCHES
                => false,
        )
    );
}

/**
 * Lê o corpo bruto da requisição.
 *
 * A assinatura usa exatamente os bytes recebidos.
 *
 * @param int $maxBytes
 * @return string
 */
function flux_api_raw_body($maxBytes)
{
    $body = file_get_contents(
        'php://input'
    );

    if ($body === false) {
        flux_api_error(
            400,
            'BODY_READ_ERROR',
            'Não foi possível ler o corpo da requisição.'
        );
    }

    $body = (string) $body;

    if (strlen($body) > (int) $maxBytes) {
        flux_api_error(
            413,
            'PAYLOAD_TOO_LARGE',
            'O corpo da requisição excede o limite permitido.'
        );
    }

    if ($body === '') {
        flux_api_error(
            400,
            'EMPTY_BODY',
            'O corpo da requisição não pode estar vazio.'
        );
    }

    return $body;
}

/**
 * Registra falha interna sem vazar dados sensíveis ao consumidor.
 *
 * @param string    $event
 * @param Throwable $exception
 * @return void
 */
function flux_api_log_exception(
    $event,
    Throwable $exception
) {
    $requestId = isset($GLOBALS['fluxApiRequestId'])
        ? (string) $GLOBALS['fluxApiRequestId']
        : '';

    error_log(
        '[FluxEmpresa API] request_id='
        . $requestId
        . ' event='
        . preg_replace(
            '/[^A-Za-z0-9_.:\-]/',
            '_',
            (string) $event
        )
        . ' type='
        . get_class($exception)
        . ' code='
        . (string) $exception->getCode()
        . ' message_hash='
        . hash(
            'sha256',
            (string) $exception->getMessage()
        )
    );
}

/**
 * Cria a tabela de nonces caso ela ainda não exista.
 *
 * @param PDO $pdo
 * @return void
 */
function flux_api_ensure_nonce_table(PDO $pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS integracao_fluxempresa_nonces (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id VARCHAR(100) NOT NULL,
            nonce CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,
            timestamp_requisicao INT UNSIGNED NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expira_em DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_fluxempresa_nonce_cliente (
                client_id,
                nonce
            ),
            KEY idx_fluxempresa_nonce_expiracao (
                expira_em
            )
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Registra o nonce. Nonce repetido caracteriza replay.
 *
 * @param PDO    $pdo
 * @param string $clientId
 * @param string $nonce
 * @param int    $timestamp
 * @param int    $ttl
 * @return void
 */
function flux_api_register_nonce(
    PDO $pdo,
    $clientId,
    $nonce,
    $timestamp,
    $ttl
) {
    if (mt_rand(1, 100) <= 5) {
        $pdo->exec(
            'DELETE FROM integracao_fluxempresa_nonces
              WHERE expira_em < NOW()'
        );
    }

    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + (int) $ttl
    );

    $statement = $pdo->prepare(
        'INSERT INTO integracao_fluxempresa_nonces (
            client_id,
            nonce,
            timestamp_requisicao,
            expira_em
        ) VALUES (
            :client_id,
            :nonce,
            :timestamp_requisicao,
            :expira_em
        )'
    );

    try {
        $statement->execute(
            array(
                'client_id' => $clientId,
                'nonce' => $nonce,
                'timestamp_requisicao' => (int) $timestamp,
                'expira_em' => $expiresAt,
            )
        );
    } catch (PDOException $exception) {
        $nativeCode = isset($exception->errorInfo[1])
            ? (int) $exception->errorInfo[1]
            : 0;

        if ($nativeCode === 1062) {
            flux_api_error(
                409,
                'REPLAY_DETECTED',
                'Esta requisição já foi processada.'
            );
        }

        throw $exception;
    }
}

/**
 * Retorna a URI exata usada na assinatura.
 *
 * @return string
 */
function flux_api_request_uri()
{
    $requestUri = isset($_SERVER['REQUEST_URI'])
        ? (string) $_SERVER['REQUEST_URI']
        : '';

    if (
        $requestUri === ''
        || strlen($requestUri) > 2048
        || strpos($requestUri, "\r") !== false
        || strpos($requestUri, "\n") !== false
    ) {
        flux_api_error(
            400,
            'INVALID_REQUEST_URI',
            'URI da requisição inválida.'
        );
    }

    return $requestUri;
}

/**
 * Valida a autenticação HMAC.
 *
 * Headers obrigatórios:
 * - X-Flux-Client-Id
 * - X-Flux-Timestamp
 * - X-Flux-Nonce
 * - X-Flux-Signature
 *
 * String canônica:
 * METHOD + "\n"
 * REQUEST_URI + "\n"
 * TIMESTAMP + "\n"
 * NONCE + "\n"
 * SHA256(BODY)
 *
 * @param PDO    $pdo
 * @param array  $config
 * @param string $rawBody
 * @return array
 */
function flux_api_authenticate(
    PDO $pdo,
    array $config,
    $rawBody
) {
    if (
        !flux_api_config_bool(
            $config,
            'FLUXEMPRESA_API_ENABLED',
            false
        )
    ) {
        flux_api_error(
            503,
            'INTEGRATION_DISABLED',
            'A integração está desativada.'
        );
    }

    $expectedClientId = flux_api_config_required(
        $config,
        'FLUXEMPRESA_API_CLIENT_ID'
    );

    $secret = strtolower(
        flux_api_config_required(
            $config,
            'FLUXEMPRESA_API_SECRET'
        )
    );

    if (
        preg_match(
            '/^[A-Za-z0-9_.:\-]{3,100}$/D',
            $expectedClientId
        ) !== 1
    ) {
        throw new RuntimeException(
            'FLUXEMPRESA_API_CLIENT_ID inválido.'
        );
    }

    if (
        preg_match(
            '/^[a-f0-9]{64}$/D',
            $secret
        ) !== 1
    ) {
        throw new RuntimeException(
            'FLUXEMPRESA_API_SECRET deve ser um hexadecimal SHA-256 de 64 caracteres.'
        );
    }

    $clientId = flux_api_header(
        'X-Flux-Client-Id'
    );

    $timestampHeader = flux_api_header(
        'X-Flux-Timestamp'
    );

    $nonce = strtolower(
        flux_api_header(
            'X-Flux-Nonce'
        )
    );

    $receivedSignature = strtolower(
        flux_api_header(
            'X-Flux-Signature'
        )
    );

    if (
        $clientId === ''
        || $timestampHeader === ''
        || $nonce === ''
        || $receivedSignature === ''
    ) {
        flux_api_error(
            401,
            'AUTH_HEADERS_MISSING',
            'Cabeçalhos de autenticação ausentes.'
        );
    }

    if (
        strlen($clientId) > 100
        || !hash_equals(
            $expectedClientId,
            $clientId
        )
    ) {
        flux_api_error(
            401,
            'INVALID_CLIENT',
            'Cliente da integração inválido.'
        );
    }

    if (
        preg_match(
            '/^\d{10}$/D',
            $timestampHeader
        ) !== 1
    ) {
        flux_api_error(
            401,
            'INVALID_TIMESTAMP',
            'Timestamp da integração inválido.'
        );
    }

    if (
        preg_match(
            '/^[a-f0-9]{64}$/D',
            $nonce
        ) !== 1
    ) {
        flux_api_error(
            401,
            'INVALID_NONCE',
            'Nonce da integração inválido.'
        );
    }

    if (
        preg_match(
            '/^[a-f0-9]{64}$/D',
            $receivedSignature
        ) !== 1
    ) {
        flux_api_error(
            401,
            'INVALID_SIGNATURE',
            'Assinatura da integração inválida.'
        );
    }

    $timestamp = (int) $timestampHeader;

    $tolerance = flux_api_config_int(
        $config,
        'FLUXEMPRESA_API_TIMESTAMP_TOLERANCE',
        300,
        60,
        900
    );

    if (abs(time() - $timestamp) > $tolerance) {
        flux_api_error(
            401,
            'REQUEST_EXPIRED',
            'A requisição expirou.'
        );
    }

    $method = strtoupper(
        (string) (
            isset($_SERVER['REQUEST_METHOD'])
                ? $_SERVER['REQUEST_METHOD']
                : ''
        )
    );

    $requestUri = flux_api_request_uri();

    $bodyHash = hash(
        'sha256',
        $rawBody
    );

    $canonical = implode(
        "\n",
        array(
            $method,
            $requestUri,
            $timestampHeader,
            $nonce,
            $bodyHash,
        )
    );

    $expectedSignature = hash_hmac(
        'sha256',
        $canonical,
        $secret
    );

    if (
        !hash_equals(
            $expectedSignature,
            $receivedSignature
        )
    ) {
        flux_api_error(
            401,
            'INVALID_SIGNATURE',
            'Assinatura da integração inválida.'
        );
    }

    flux_api_ensure_nonce_table(
        $pdo
    );

    flux_api_register_nonce(
        $pdo,
        $clientId,
        $nonce,
        $timestamp,
        $tolerance * 2
    );

    return array(
        'client_id' => $clientId,
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'body_hash' => $bodyHash,
        'request_uri' => $requestUri,
    );
}


/* =========================================================
 * INICIALIZAÇÃO
 * ========================================================= */

if (
    !isset($_SERVER['REQUEST_METHOD'])
    || strtoupper(
        (string) $_SERVER['REQUEST_METHOD']
    ) !== 'POST'
) {
    header('Allow: POST');

    flux_api_error(
        405,
        'METHOD_NOT_ALLOWED',
        'Use o método POST.'
    );
}

$contentType = strtolower(
    flux_api_header(
        'Content-Type'
    )
);

if (
    strpos(
        $contentType,
        'application/json'
    ) !== 0
) {
    flux_api_error(
        415,
        'UNSUPPORTED_MEDIA_TYPE',
        'O conteúdo deve ser enviado como application/json.'
    );
}

try {
    $fluxApiConfigDirectory = flux_api_resolve_config_directory();

    $fluxApiConfig = flux_api_load_config(
        $fluxApiConfigDirectory
    );

    $trustProxy = flux_api_config_bool(
        $fluxApiConfig,
        'FLUXEMPRESA_API_TRUST_PROXY',
        false
    );

    if (!flux_api_is_https($trustProxy)) {
        flux_api_error(
            400,
            'HTTPS_REQUIRED',
            'A integração exige HTTPS.'
        );
    }

    $maxBodyBytes = flux_api_config_int(
        $fluxApiConfig,
        'FLUXEMPRESA_API_MAX_BODY_BYTES',
        1048576,
        1024,
        5242880
    );

    $contentLength = isset($_SERVER['CONTENT_LENGTH'])
        ? (int) $_SERVER['CONTENT_LENGTH']
        : 0;

    if (
        $contentLength < 0
        || $contentLength > $maxBodyBytes
    ) {
        flux_api_error(
            413,
            'PAYLOAD_TOO_LARGE',
            'O corpo da requisição excede o limite permitido.'
        );
    }

    $pdo = flux_api_create_pdo(
        $fluxApiConfig
    );

    $rawBody = flux_api_raw_body(
        $maxBodyBytes
    );

    $fluxApiAuthentication = flux_api_authenticate(
        $pdo,
        $fluxApiConfig,
        $rawBody
    );

    $fluxApiPayload = json_decode(
        $rawBody,
        true
    );

    if (
        !is_array($fluxApiPayload)
        || json_last_error() !== JSON_ERROR_NONE
    ) {
        flux_api_error(
            400,
            'INVALID_JSON',
            'O JSON enviado é inválido.'
        );
    }
} catch (Throwable $exception) {
    flux_api_log_exception(
        'bootstrap_failed',
        $exception
    );

    flux_api_error(
        500,
        'INTERNAL_ERROR',
        'Não foi possível inicializar a integração.'
    );
}