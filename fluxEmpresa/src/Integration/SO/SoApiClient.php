<?php

declare(strict_types=1);

namespace App\Integration\SO;

use JsonException;
use Throwable;

/**
 * Cliente HTTP Flux Empresas → API do SO.
 *
 * Responsabilidades:
 * - criar o JSON que será enviado;
 * - gerar nonce e timestamp;
 * - assinar a requisição com HMAC-SHA256;
 * - enviar somente por HTTPS;
 * - validar a resposta da API;
 * - impedir redirects;
 * - limitar o tamanho da resposta;
 * - não expor o segredo em logs ou exceções.
 */
final class SoApiClient
{
    private const MAX_REQUEST_BYTES = 1048576;

    private const MAX_RESPONSE_BYTES = 1048576;

    private const USER_AGENT =
        'FluxEmpresas-SO-Integration/1.0';

    public function __construct(
        private readonly bool $enabled,
        private readonly string $baseUrl,
        private readonly string $acquisitionPath,
        private readonly string $clientId,
        private readonly string $secret,
        private readonly int $connectTimeout = 5,
        private readonly int $timeout = 15,
        private readonly bool $verifyTls = true
    ) {
        $this->validateConfiguration();
    }

    /**
     * Cria uma aquisição no SO.
     *
     * O array informado deve seguir o contrato do endpoint:
     *
     * - idempotency_key;
     * - empresa_flux_id;
     * - empresa_flux_uuid;
     * - origem;
     * - orcamento_flux_id ou ordem_servico_flux_id;
     * - usuario_flux_id;
     * - fornecedor_so_id;
     * - cliente;
     * - descricao;
     * - valor_total;
     * - itens.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     idempotent: bool,
     *     request_id: string|null,
     *     aquisicao: array{
     *         id: int,
     *         numero: string,
     *         codigo_entrega: string,
     *         status: string,
     *         valor_total: string|null,
     *         fornecedor_id: int|null,
     *         fornecedor_nome: string|null,
     *         criada_em: string|null
     *     }
     * }
     */
    public function createAcquisition(
        array $payload
    ): array {
        if (!$this->enabled) {
            throw new SoIntegrationException(
                message: 'A integração com o SO está desativada.',
                reason: 'integration_disabled'
            );
        }

        $this->validatePayloadIdentity(
            $payload
        );

        try {
            $body = json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $exception) {
            throw new SoIntegrationException(
                message: 'Não foi possível preparar os dados da aquisição.',
                reason: 'payload_encoding_failed',
                previous: $exception
            );
        }

        if (
            !is_string($body)
            || $body === ''
        ) {
            throw new SoIntegrationException(
                message: 'Não foi possível preparar os dados da aquisição.',
                reason: 'payload_encoding_failed'
            );
        }

        if (
            strlen($body)
            > self::MAX_REQUEST_BYTES
        ) {
            throw new SoIntegrationException(
                message: 'Os dados da aquisição excedem o limite permitido.',
                reason: 'payload_too_large'
            );
        }

        $url = $this->buildAcquisitionUrl();

        $requestUri = $this->requestUriFromUrl(
            $url
        );

        $timestamp = (string) time();

        try {
            $nonce = bin2hex(
                random_bytes(32)
            );
        } catch (Throwable $exception) {
            throw new SoIntegrationException(
                message: 'Não foi possível gerar a segurança da requisição.',
                reason: 'nonce_generation_failed',
                previous: $exception
            );
        }

        $bodyHash = hash(
            'sha256',
            $body
        );

        $canonicalRequest = implode(
            "\n",
            [
                'POST',
                $requestUri,
                $timestamp,
                $nonce,
                $bodyHash,
            ]
        );

        $signature = hash_hmac(
            'sha256',
            $canonicalRequest,
            strtolower($this->secret)
        );

        $response = $this->postJson(
            url: $url,
            body: $body,
            timestamp: $timestamp,
            nonce: $nonce,
            signature: $signature,
            payloadHash: $bodyHash
        );

        return $this->normalizeResponse(
            httpStatus: $response['http_status'],
            responseBody: $response['body'],
            responseRequestId: $response['request_id'],
            payloadHash: $bodyHash
        );
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePayloadIdentity(
        array $payload
    ): void {
        $idempotencyKey = strtolower(
            trim(
                (string) (
                    $payload['idempotency_key']
                    ?? ''
                )
            )
        );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $idempotencyKey
            ) !== 1
        ) {
            throw new SoIntegrationException(
                message: 'Chave de idempotência da aquisição inválida.',
                reason: 'invalid_idempotency_key'
            );
        }

        $companyId = filter_var(
            $payload['empresa_flux_id']
                ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($companyId === false) {
            throw new SoIntegrationException(
                message: 'Empresa da integração inválida.',
                reason: 'invalid_company'
            );
        }

        $origin = trim(
            (string) (
                $payload['origem']
                ?? ''
            )
        );

        if (
            !in_array(
                $origin,
                [
                    'orcamento',
                    'ordem_servico',
                ],
                true
            )
        ) {
            throw new SoIntegrationException(
                message: 'Origem da aquisição inválida.',
                reason: 'invalid_origin'
            );
        }

        $items = $payload['itens']
            ?? null;

        if (
            !is_array($items)
            || $items === []
            || count($items) > 200
        ) {
            throw new SoIntegrationException(
                message: 'Os itens da aquisição são inválidos.',
                reason: 'invalid_items'
            );
        }
    }

    private function validateConfiguration(): void
    {
        if (!$this->enabled) {
            /*
             * Quando desativada, a aplicação pode inicializar antes
             * de todas as credenciais serem configuradas.
             */
            return;
        }

        if (
            !extension_loaded('curl')
            || !function_exists('curl_init')
        ) {
            throw new SoIntegrationException(
                message: 'A extensão cURL não está disponível.',
                reason: 'curl_unavailable'
            );
        }

        $baseUrl = rtrim(
            trim($this->baseUrl),
            '/'
        );

        if (
            filter_var(
                $baseUrl,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            throw new SoIntegrationException(
                message: 'URL da API do SO inválida.',
                reason: 'configuration_invalid'
            );
        }

        $parts = parse_url(
            $baseUrl
        );

        if (
            !is_array($parts)
            || strtolower(
                (string) (
                    $parts['scheme']
                    ?? ''
                )
            ) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new SoIntegrationException(
                message: 'URL da API do SO inválida.',
                reason: 'configuration_invalid'
            );
        }

        if (
            $this->acquisitionPath === ''
            || $this->acquisitionPath[0] !== '/'
            || str_contains(
                $this->acquisitionPath,
                "\0"
            )
            || str_contains(
                $this->acquisitionPath,
                "\r"
            )
            || str_contains(
                $this->acquisitionPath,
                "\n"
            )
            || str_contains(
                $this->acquisitionPath,
                '..'
            )
            || str_contains(
                $this->acquisitionPath,
                '?'
            )
            || str_contains(
                $this->acquisitionPath,
                '#'
            )
            || str_contains(
                $this->acquisitionPath,
                '://'
            )
        ) {
            throw new SoIntegrationException(
                message: 'Caminho da API do SO inválido.',
                reason: 'configuration_invalid'
            );
        }

        if (
            preg_match(
                '/^[A-Za-z0-9_.:-]{3,100}$/D',
                $this->clientId
            ) !== 1
        ) {
            throw new SoIntegrationException(
                message: 'Cliente da API do SO inválido.',
                reason: 'configuration_invalid'
            );
        }

        if (
            preg_match(
                '/^[a-f0-9]{64}$/Di',
                $this->secret
            ) !== 1
        ) {
            throw new SoIntegrationException(
                message: 'Segredo da API do SO inválido.',
                reason: 'configuration_invalid'
            );
        }

        if (
            $this->connectTimeout < 1
            || $this->connectTimeout > 30
            || $this->timeout < 2
            || $this->timeout > 120
            || $this->timeout < $this->connectTimeout
        ) {
            throw new SoIntegrationException(
                message: 'Timeout da API do SO inválido.',
                reason: 'configuration_invalid'
            );
        }
    }

    private function buildAcquisitionUrl(): string
    {
        $url = rtrim(
            $this->baseUrl,
            '/'
        ) . $this->acquisitionPath;

        $parts = parse_url(
            $url
        );

        if (
            !is_array($parts)
            || strtolower(
                (string) (
                    $parts['scheme']
                    ?? ''
                )
            ) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new SoIntegrationException(
                message: 'Destino da API do SO inválido.',
                reason: 'configuration_invalid'
            );
        }

        return $url;
    }

    private function requestUriFromUrl(
        string $url
    ): string {
        $parts = parse_url(
            $url
        );

        if (!is_array($parts)) {
            throw new SoIntegrationException(
                message: 'Destino da API do SO inválido.',
                reason: 'configuration_invalid'
            );
        }

        $path = (string) (
            $parts['path']
            ?? ''
        );

        if (
            $path === ''
            || $path[0] !== '/'
        ) {
            throw new SoIntegrationException(
                message: 'Caminho da API do SO inválido.',
                reason: 'configuration_invalid'
            );
        }

        /*
         * O endpoint atual não aceita query string. Caso ela seja
         * introduzida futuramente, deverá participar da assinatura.
         */
        return $path;
    }

    /**
     * @return array{
     *     http_status: int,
     *     body: string,
     *     request_id: string|null
     * }
     */
    private function postJson(
        string $url,
        string $body,
        string $timestamp,
        string $nonce,
        string $signature,
        string $payloadHash
    ): array {
        $curl = curl_init();

        if ($curl === false) {
            throw new SoIntegrationException(
                message: 'Não foi possível iniciar a comunicação com o SO.',
                reason: 'curl_initialization_failed'
            );
        }

        $responseBody = '';
        $responseRequestId = null;
        $responseTooLarge = false;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,

            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_NOSIGNAL => true,

            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,

            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls
                ? 2
                : 0,

            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json; charset=UTF-8',
                'Content-Length: ' . strlen($body),
                'X-Flux-Client-Id: ' . $this->clientId,
                'X-Flux-Timestamp: ' . $timestamp,
                'X-Flux-Nonce: ' . $nonce,
                'X-Flux-Signature: ' . $signature,
            ],

            CURLOPT_USERAGENT => self::USER_AGENT,

            CURLOPT_HEADERFUNCTION => static function (
                mixed $handle,
                string $headerLine
            ) use (
                &$responseRequestId
            ): int {
                $length = strlen(
                    $headerLine
                );

                if (
                    stripos(
                        $headerLine,
                        'X-Request-Id:'
                    ) === 0
                ) {
                    $candidate = trim(
                        substr(
                            $headerLine,
                            strlen('X-Request-Id:')
                        )
                    );

                    if (
                        preg_match(
                            '/^[A-Za-z0-9_.:-]{1,100}$/D',
                            $candidate
                        ) === 1
                    ) {
                        $responseRequestId = $candidate;
                    }
                }

                return $length;
            },

            CURLOPT_WRITEFUNCTION => static function (
                mixed $handle,
                string $chunk
            ) use (
                &$responseBody,
                &$responseTooLarge
            ): int {
                $newLength = strlen(
                    $responseBody
                ) + strlen(
                    $chunk
                );

                if (
                    $newLength
                    > self::MAX_RESPONSE_BYTES
                ) {
                    $responseTooLarge = true;

                    /*
                     * Retornar zero interrompe o recebimento.
                     */
                    return 0;
                }

                $responseBody .= $chunk;

                return strlen(
                    $chunk
                );
            },
        ];

        /*
         * Restringe o protocolo no próprio cURL quando a versão
         * instalada oferecer suporte.
         */
        if (
            defined('CURLOPT_PROTOCOLS_STR')
        ) {
            $options[CURLOPT_PROTOCOLS_STR] =
                'https';
        } elseif (
            defined('CURLOPT_PROTOCOLS')
            && defined('CURLPROTO_HTTPS')
        ) {
            $options[CURLOPT_PROTOCOLS] =
                CURLPROTO_HTTPS;
        }

        if (
            defined('CURLOPT_REDIR_PROTOCOLS_STR')
        ) {
            $options[CURLOPT_REDIR_PROTOCOLS_STR] =
                'https';
        } elseif (
            defined('CURLOPT_REDIR_PROTOCOLS')
            && defined('CURLPROTO_HTTPS')
        ) {
            $options[CURLOPT_REDIR_PROTOCOLS] =
                CURLPROTO_HTTPS;
        }

        curl_setopt_array(
            $curl,
            $options
        );

        $executed = curl_exec(
            $curl
        );

        $curlErrorNumber = curl_errno(
            $curl
        );

        $curlError = curl_error(
            $curl
        );

        $httpStatus = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close(
            $curl
        );

        if ($responseTooLarge) {
            $this->logFailure(
                reason: 'response_too_large',
                httpStatus: $httpStatus,
                curlErrorNumber: $curlErrorNumber,
                curlError: $curlError,
                requestId: $responseRequestId,
                payloadHash: $payloadHash
            );

            throw new SoIntegrationException(
                message: 'A resposta do SO excedeu o limite permitido.',
                reason: 'response_too_large'
            );
        }

        if (
            $executed === false
            || $curlErrorNumber !== 0
        ) {
            $reason = $this->mapCurlErrorReason(
                $curlErrorNumber
            );

            $this->logFailure(
                reason: $reason,
                httpStatus: $httpStatus,
                curlErrorNumber: $curlErrorNumber,
                curlError: $curlError,
                requestId: $responseRequestId,
                payloadHash: $payloadHash
            );

            throw new SoIntegrationException(
                message: $reason === 'timeout'
                    ? 'A comunicação com o SO excedeu o tempo limite.'
                    : 'Não foi possível comunicar com o SO.',
                reason: $reason
            );
        }

        if (
            $httpStatus < 100
            || $httpStatus > 599
        ) {
            $this->logFailure(
                reason: 'invalid_http_status',
                httpStatus: $httpStatus,
                curlErrorNumber: 0,
                curlError: '',
                requestId: $responseRequestId,
                payloadHash: $payloadHash
            );

            throw new SoIntegrationException(
                message: 'O SO retornou uma resposta HTTP inválida.',
                reason: 'invalid_http_status'
            );
        }

        return [
            'http_status' => $httpStatus,
            'body' => $responseBody,
            'request_id' => $responseRequestId,
        ];
    }

    /**
     * @return array{
     *     idempotent: bool,
     *     request_id: string|null,
     *     aquisicao: array{
     *         id: int,
     *         numero: string,
     *         codigo_entrega: string,
     *         status: string,
     *         valor_total: string|null,
     *         fornecedor_id: int|null,
     *         fornecedor_nome: string|null,
     *         criada_em: string|null
     *     }
     * }
     */
    private function normalizeResponse(
        int $httpStatus,
        string $responseBody,
        ?string $responseRequestId,
        string $payloadHash
    ): array {
        if ($responseBody === '') {
            $this->logFailure(
                reason: 'empty_response',
                httpStatus: $httpStatus,
                curlErrorNumber: 0,
                curlError: '',
                requestId: $responseRequestId,
                payloadHash: $payloadHash
            );

            throw new SoIntegrationException(
                message: 'O SO retornou uma resposta vazia.',
                reason: 'empty_response'
            );
        }

        try {
            $decoded = json_decode(
                $responseBody,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            $this->logFailure(
                reason: 'invalid_json_response',
                httpStatus: $httpStatus,
                curlErrorNumber: 0,
                curlError: '',
                requestId: $responseRequestId,
                payloadHash: $payloadHash
            );

            throw new SoIntegrationException(
                message: 'O SO retornou uma resposta inválida.',
                reason: 'invalid_json_response',
                previous: $exception
            );
        }

        if (!is_array($decoded)) {
            throw new SoIntegrationException(
                message: 'O SO retornou uma resposta inválida.',
                reason: 'invalid_json_response'
            );
        }

        $requestId = $this->normalizeRequestId(
            $decoded['request_id']
                ?? $responseRequestId
        );

        if (
            $httpStatus < 200
            || $httpStatus >= 300
            || ($decoded['success'] ?? false) !== true
        ) {
            $errorCode = strtoupper(
                trim(
                    (string) (
                        $decoded['error']['code']
                        ?? 'UNKNOWN_API_ERROR'
                    )
                )
            );

            $errorMessage = trim(
                (string) (
                    $decoded['error']['message']
                    ?? 'Não foi possível processar a aquisição no SO.'
                )
            );

            if (
                $errorMessage === ''
                || strlen($errorMessage) > 500
                || str_contains($errorMessage, "\0")
            ) {
                $errorMessage =
                    'Não foi possível processar a aquisição no SO.';
            }

            $reason = $this->mapApiErrorReason(
                $httpStatus,
                $errorCode
            );

            $this->logFailure(
                reason: $reason,
                httpStatus: $httpStatus,
                curlErrorNumber: 0,
                curlError: '',
                requestId: $requestId,
                payloadHash: $payloadHash
            );

            throw new SoIntegrationException(
                message: $errorMessage,
                reason: $reason
            );
        }

        $acquisition = $decoded['aquisicao']
            ?? null;

        if (!is_array($acquisition)) {
            throw new SoIntegrationException(
                message: 'O SO não retornou os dados da aquisição.',
                reason: 'invalid_success_response'
            );
        }

        $acquisitionId = filter_var(
            $acquisition['id']
                ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        $number = trim(
            (string) (
                $acquisition['numero']
                ?? ''
            )
        );

        $deliveryCode = trim(
            (string) (
                $acquisition['codigo_entrega']
                ?? ''
            )
        );

        $status = trim(
            (string) (
                $acquisition['status']
                ?? ''
            )
        );

        if (
            $acquisitionId === false
            || $number === ''
            || strlen($number) > 50
            || $deliveryCode === ''
            || strlen($deliveryCode) > 50
            || $status === ''
            || strlen($status) > 80
        ) {
            throw new SoIntegrationException(
                message: 'O SO retornou dados incompletos da aquisição.',
                reason: 'invalid_success_response'
            );
        }

        $supplierId = filter_var(
            $acquisition['fornecedor_id']
                ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        return [
            'idempotent' => (
                $decoded['idempotent']
                ?? false
            ) === true,

            'request_id' => $requestId,

            'aquisicao' => [
                'id' => (int) $acquisitionId,

                'numero' => $number,

                'codigo_entrega' => $deliveryCode,

                'status' => $status,

                'valor_total' => isset(
                    $acquisition['valor_total']
                )
                    ? (string) $acquisition['valor_total']
                    : null,

                'fornecedor_id' => $supplierId === false
                    ? null
                    : (int) $supplierId,

                'fornecedor_nome' => isset(
                    $acquisition['fornecedor_nome']
                )
                    ? trim(
                        (string) $acquisition['fornecedor_nome']
                    )
                    : null,

                'criada_em' => isset(
                    $acquisition['criada_em']
                )
                    ? trim(
                        (string) $acquisition['criada_em']
                    )
                    : null,
            ],
        ];
    }

    private function mapCurlErrorReason(
        int $curlErrorNumber
    ): string {
        $timeoutErrors = [
            defined('CURLE_OPERATION_TIMEDOUT')
                ? CURLE_OPERATION_TIMEDOUT
                : 28,
        ];

        if (
            in_array(
                $curlErrorNumber,
                $timeoutErrors,
                true
            )
        ) {
            return 'timeout';
        }

        $tlsErrors = [
            35, // CURLE_SSL_CONNECT_ERROR
            51, // CURLE_PEER_FAILED_VERIFICATION antigo
            53, // CURLE_SSL_ENGINE_NOTFOUND
            54, // CURLE_SSL_ENGINE_SETFAILED
            58, // CURLE_SSL_CERTPROBLEM
            59, // CURLE_SSL_CIPHER
            60, // CURLE_PEER_FAILED_VERIFICATION
            64, // CURLE_USE_SSL_FAILED
            66, // CURLE_SSL_ENGINE_INITFAILED
            77, // CURLE_SSL_CACERT_BADFILE
            80, // CURLE_SSL_SHUTDOWN_FAILED
            82, // CURLE_SSL_CRL_BADFILE
            83, // CURLE_SSL_ISSUER_ERROR
            90, // CURLE_SSL_PINNEDPUBKEYNOTMATCH
            91, // CURLE_SSL_INVALIDCERTSTATUS
        ];

        if (
            in_array(
                $curlErrorNumber,
                $tlsErrors,
                true
            )
        ) {
            return 'tls_failed';
        }

        $connectionErrors = [
            5,  // CURLE_COULDNT_RESOLVE_PROXY
            6,  // CURLE_COULDNT_RESOLVE_HOST
            7,  // CURLE_COULDNT_CONNECT
            52, // CURLE_GOT_NOTHING
        ];

        if (
            in_array(
                $curlErrorNumber,
                $connectionErrors,
                true
            )
        ) {
            return 'connection_failed';
        }

        return 'http_transport_failed';
    }

    private function mapApiErrorReason(
        int $httpStatus,
        string $errorCode
    ): string {
        $map = [
            'INTEGRATION_DISABLED'
                => 'remote_integration_disabled',

            'AUTH_HEADERS_MISSING'
                => 'authentication_failed',

            'INVALID_CLIENT'
                => 'authentication_failed',

            'INVALID_TIMESTAMP'
                => 'authentication_failed',

            'INVALID_NONCE'
                => 'authentication_failed',

            'INVALID_SIGNATURE'
                => 'authentication_failed',

            'REQUEST_EXPIRED'
                => 'request_expired',

            'REPLAY_DETECTED'
                => 'replay_detected',

            'INVALID_IDEMPOTENCY_KEY'
                => 'invalid_idempotency_key',

            'IDEMPOTENCY_CONFLICT'
                => 'idempotency_conflict',

            'ORIGIN_ALREADY_LINKED'
                => 'origin_already_linked',

            'SUPPLIER_NOT_FOUND'
                => 'supplier_not_found',

            'TOTAL_MISMATCH'
                => 'total_mismatch',

            'VALIDATION_ERROR'
                => 'validation_failed',

            'RESOURCE_BUSY'
                => 'remote_resource_busy',

            'ACQUISITION_CREATE_FAILED'
                => 'remote_creation_failed',
        ];

        if (isset($map[$errorCode])) {
            return $map[$errorCode];
        }

        return match (true) {
            $httpStatus === 401,
            $httpStatus === 403
                => 'authentication_failed',

            $httpStatus === 409
                => 'remote_conflict',

            $httpStatus === 422
                => 'validation_failed',

            $httpStatus === 429
                => 'remote_rate_limited',

            $httpStatus >= 500
                => 'remote_unavailable',

            default
                => 'remote_api_error',
        };
    }

    private function normalizeRequestId(
        mixed $requestId
    ): ?string {
        $requestId = trim(
            (string) $requestId
        );

        if (
            $requestId === ''
            || preg_match(
                '/^[A-Za-z0-9_.:-]{1,100}$/D',
                $requestId
            ) !== 1
        ) {
            return null;
        }

        return $requestId;
    }

    private function logFailure(
        string $reason,
        int $httpStatus,
        int $curlErrorNumber,
        string $curlError,
        ?string $requestId,
        string $payloadHash
    ): void {
        $host = parse_url(
            $this->baseUrl,
            PHP_URL_HOST
        );

        $safeReason = preg_replace(
            '/[^A-Za-z0-9_.:-]/',
            '_',
            $reason
        ) ?: 'unknown';

        $safeRequestId = $this->normalizeRequestId(
            $requestId
        );

        $curlErrorHash = $curlError !== ''
            ? hash(
                'sha256',
                $curlError
            )
            : '';

        error_log(
            'SO API request failed.'
            . ' reason=' . $safeReason
            . ' host=' . (
                is_string($host)
                    ? $host
                    : 'unknown'
            )
            . ' http_status=' . $httpStatus
            . ' curl_code=' . $curlErrorNumber
            . ' curl_error_hash=' . $curlErrorHash
            . ' request_id=' . (
                $safeRequestId
                    ?? ''
            )
            . ' payload_hash=' . $payloadHash
        );
    }
}