<?php

declare(strict_types=1);

/**
 * Bootstrap da API Flux Empresas → SO.
 *
 * Responsabilidades:
 * - carregar a configuração privada;
 * - carregar o PDO do SO;
 * - aceitar somente HTTPS e JSON;
 * - validar cliente, timestamp, nonce e assinatura HMAC;
 * - impedir replay;
 * - responder sempre em JSON;
 *
 * Compatível com PHP 7.2.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (
    isset($_SERVER['REQUEST_METHOD'])
    && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'OPTIONS'
) {
    /*
     * A API é servidor-servidor e não deve ser chamada pelo navegador.
     * Não liberamos CORS.
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
    http_response_code((int) $statusCode);

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        http_response_code(500);

        echo '{"success":false,"error":{"code":"JSON_ENCODING_ERROR","message":"Não foi possível gerar a resposta."}}';
        exit;
    }

    echo $json;
    exit;
}

/**
 * Resposta padronizada de erro.
 *
 * @param int         $statusCode
 * @param string      $code
 * @param string      $message
 * @param array|null  $details
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
 * Funciona em Apache, Nginx e FastCGI.
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
 * Considera proxy reverso apenas quando X-Forwarded-Proto informa
 * explicitamente "https".
 *
 * @return bool
 */
function flux_api_is_https()
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

    $forwardedProto = strtolower(
        flux_api_header('X-Forwarded-Proto')
    );

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
 * Procura o arquivo privado da integração.
 *
 * Prioriza arquivo fora do public_html. Mantém caminhos alternativos
 * para a estrutura atual da hospedagem.
 *
 * @return string
 */
function flux_api_resolve_env_path()
{
    $candidates = array();

    /*
     * SO está normalmente em:
     * public_html/SO/api/integracoes/fluxempresa
     */
    $projectRoot = dirname(
        dirname(
            dirname(
                dirname(__DIR__)
            )
        )
    );

    $publicHtml = dirname($projectRoot);
    $accountRoot = dirname($publicHtml);

    $candidates[] = $accountRoot
        . '/configuracoes/so/api.env';

    $candidates[] = $accountRoot
        . '/configuracoes/so/.env';

    $candidates[] = $publicHtml
        . '/configuracoes/so/api.env';

    $candidates[] = $publicHtml
        . '/configuracoes/so/.env';

    foreach ($candidates as $candidate) {
        if (
            is_file($candidate)
            && is_readable($candidate)
        ) {
            return $candidate;
        }
    }

    throw new RuntimeException(
        'Configuração privada da integração não localizada.'
    );
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
 * Normaliza o corpo para assinatura.
 *
 * A assinatura usa exatamente os bytes recebidos no body.
 *
 * @return string
 */
function flux_api_raw_body()
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

    return (string) $body;
}

/**
 * Registra falha interna sem vazar segredo ao consumidor.
 *
 * @param string    $event
 * @param Throwable $exception
 * @return void
 */
function flux_api_log_exception(
    $event,
    Throwable $exception
) {
    error_log(
        '[FluxEmpresa API] '
        . preg_replace(
            '/[^A-Za-z0-9_.:-]/',
            '_',
            (string) $event
        )
        . ' type='
        . get_class($exception)
        . ' code='
        . (string) $exception->getCode()
    );
}

/**
 * Cria a tabela local de nonces, caso a migration ainda não tenha
 * sido executada.
 *
 * Esta proteção existe para impedir replay da mesma assinatura.
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
 * Registra o nonce. Se já existir, a requisição é replay.
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
    /*
     * Limpeza probabilística para evitar DELETE em toda requisição.
     */
    if (mt_rand(1, 100) <= 5) {
        $pdo->exec(
            'DELETE FROM integracao_fluxempresa_nonces
              WHERE expira_em < NOW()'
        );
    }

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
            DATE_ADD(
                NOW(),
                INTERVAL :ttl SECOND
            )
        )'
    );

    try {
        $statement->bindValue(
            ':client_id',
            $clientId,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':nonce',
            $nonce,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':timestamp_requisicao',
            (int) $timestamp,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':ttl',
            (int) $ttl,
            PDO::PARAM_INT
        );

        $statement->execute();
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
 * Valida a autenticação HMAC.
 *
 * Headers obrigatórios:
 *
 * X-Flux-Client-Id
 * X-Flux-Timestamp
 * X-Flux-Nonce
 * X-Flux-Signature
 *
 * String assinada:
 *
 * METHOD \n
 * REQUEST_URI \n
 * TIMESTAMP \n
 * NONCE \n
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
    $enabled = flux_api_config_bool(
        $config,
        'FLUXEMPRESA_API_ENABLED',
        false
    );

    if (!$enabled) {
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

    $secret = flux_api_config_required(
        $config,
        'FLUXEMPRESA_API_SECRET'
    );

    if (strlen($secret) < 32) {
        throw new RuntimeException(
            'O segredo da API precisa ter pelo menos 32 caracteres.'
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

    if (!hash_equals($expectedClientId, $clientId)) {
        flux_api_error(
            401,
            'INVALID_CLIENT',
            'Cliente da integração inválido.'
        );
    }

    if (
        preg_match(
            '/^\d{10}$/',
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

    $tolerance = isset(
        $config['FLUXEMPRESA_API_TIMESTAMP_TOLERANCE']
    )
        ? (int) $config['FLUXEMPRESA_API_TIMESTAMP_TOLERANCE']
        : 300;

    $tolerance = max(
        60,
        min(
            900,
            $tolerance
        )
    );

    if (
        abs(
            time() - $timestamp
        ) > $tolerance
    ) {
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

    $requestUri = (string) (
        isset($_SERVER['REQUEST_URI'])
            ? $_SERVER['REQUEST_URI']
            : ''
    );

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
    header(
        'Allow: POST'
    );

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

$contentLength = isset(
    $_SERVER['CONTENT_LENGTH']
)
    ? (int) $_SERVER['CONTENT_LENGTH']
    : 0;

if ($contentLength > 1048576) {
    flux_api_error(
        413,
        'PAYLOAD_TOO_LARGE',
        'O corpo da requisição excede o limite permitido.'
    );
}

if (!flux_api_is_https()) {
    flux_api_error(
        400,
        'HTTPS_REQUIRED',
        'A integração exige HTTPS.'
    );
}

try {
    $envPath = flux_api_resolve_env_path();

    $fluxApiConfig = flux_api_read_env_file(
        $envPath
    );

    /*
     * Carrega o PDO atual do SO.
     *
     * Este arquivo também inicia sessão no código legado. A API não
     * utiliza essa sessão para autorização.
     */
    require_once dirname(
        dirname(
            dirname(__DIR__)
        )
    ) . '/config/database.php';

    if (
        !isset($pdo)
        || !($pdo instanceof PDO)
    ) {
        throw new RuntimeException(
            'A conexão com o banco do SO não foi inicializada.'
        );
    }

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    $pdo->setAttribute(
        PDO::ATTR_EMULATE_PREPARES,
        false
    );

    $rawBody = flux_api_raw_body();

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