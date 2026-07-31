<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Database;
use App\Core\Environment;
use App\Core\MigrationException;
use App\Core\MigrationRunner;
use App\Core\WebMigrationCoordinator;

/* =========================================================
 * AUTOLOAD
 * ========================================================= */

$vendorAutoload = __DIR__ . '/vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'App\\';

        if (
            strncmp(
                $class,
                $prefix,
                strlen($prefix)
            ) !== 0
        ) {
            return;
        }

        $relativeClass = substr(
            $class,
            strlen($prefix)
        );

        if (
            $relativeClass === ''
            || str_contains($relativeClass, '..')
            || str_contains($relativeClass, "\0")
        ) {
            throw new RuntimeException(
                'Classe inválida para autoload.'
            );
        }

        $relativePath = str_replace(
            '\\',
            DIRECTORY_SEPARATOR,
            $relativeClass
        ) . '.php';

        $baseDir = __DIR__
            . DIRECTORY_SEPARATOR
            . 'src'
            . DIRECTORY_SEPARATOR;

        $file = $baseDir . $relativePath;

        $realBase = realpath($baseDir);

        $realFile = is_file($file)
            ? realpath($file)
            : false;

        if (
            $realBase === false
            || $realFile === false
            || strncmp(
                $realFile,
                $realBase,
                strlen($realBase)
            ) !== 0
        ) {
            if (
                (getenv('APP_ENV') ?: 'production')
                !== 'production'
            ) {
                throw new RuntimeException(
                    'Classe não encontrada: ' . $class
                );
            }

            return;
        }

        require $realFile;
    }
);

/* =========================================================
 * CONFIGURAÇÃO INICIAL DE ERROS
 * ========================================================= */

ini_set(
    'display_errors',
    '0'
);

ini_set(
    'display_startup_errors',
    '0'
);

ini_set(
    'log_errors',
    '1'
);

ini_set(
    'error_log',
    __DIR__ . '/storage/logs/app.log'
);

error_reporting(E_ALL);

/*
 * Usado no bloco catch, inclusive quando o erro acontecer
 * antes do carregamento completo do ambiente.
 */
$bootstrapDebug = false;

/* =========================================================
 * HELPER DE RETORNO DAS ACTIONS
 * ========================================================= */

if (!function_exists('action_return_target')) {
    function action_return_target(
        Application $application,
        string $default
    ): string {
        $fallback = $application
            ->redirect()
            ->sanitize($default);

        $raw = isset($_POST['return_to'])
            ? (string) $_POST['return_to']
            : '';

        $safe = $application
            ->redirect()
            ->sanitize($raw);

        $target = (
            $raw !== ''
            && $safe !== 'dashboard.php'
        )
            ? $safe
            : $fallback;

        $defaultParts = parse_url(
            $fallback
        );

        if (
            !isset($defaultParts['query'])
            || (string) $defaultParts['query'] === ''
        ) {
            return $target;
        }

        $targetParts = parse_url(
            $target
        );

        $path = isset($targetParts['path'])
            ? (string) $targetParts['path']
            : $fallback;

        parse_str(
            isset($targetParts['query'])
                ? (string) $targetParts['query']
                : '',
            $query
        );

        parse_str(
            (string) $defaultParts['query'],
            $defaultQuery
        );

        foreach ($defaultQuery as $key => $value) {
            $query[$key] = $value;
        }

        return $path
            . (
                $query === []
                    ? ''
                    : '?' . http_build_query($query)
            );
    }
}

/* =========================================================
 * INICIALIZAÇÃO DA APLICAÇÃO
 * ========================================================= */

try {
    /* =====================================================
     * AMBIENTE
     * ===================================================== */

    $environment = new Environment(
        Environment::resolveFilePath(
            __DIR__
        )
    );

    $environment->load();

    /*
     * Conversão estrita de configurações booleanas.
     *
     * Valores inválidos não são aceitos silenciosamente.
     */
    $parseBoolean = static function (
        ?string $value,
        string $configurationName
    ): bool {
        $parsed = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($parsed === null) {
            throw new RuntimeException(
                'Configuração booleana inválida: '
                . $configurationName
            );
        }

        return $parsed;
    };

    /*
     * Conversão estrita de configurações inteiras.
     */
    $parseInteger = static function (
        ?string $value,
        string $configurationName,
        int $default,
        int $minimum,
        int $maximum
    ): int {
        if (
            $value === null
            || trim($value) === ''
        ) {
            return $default;
        }

        $parsed = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => $minimum,
                    'max_range' => $maximum,
                ],
            ]
        );

        if ($parsed === false) {
            throw new RuntimeException(
                'Configuração inteira inválida: '
                . $configurationName
            );
        }

        return (int) $parsed;
    };

    /* =====================================================
     * CONFIGURAÇÕES GERAIS
     * ===================================================== */

    $appEnv = strtolower(
        trim(
            $environment->require(
                'APP_ENV'
            )
        )
    );

    if (
        !in_array(
            $appEnv,
            [
                'production',
                'development',
                'testing',
                'local',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'APP_ENV possui um valor inválido.'
        );
    }

    $appDebug = $parseBoolean(
        $environment->require(
            'APP_DEBUG'
        ),
        'APP_DEBUG'
    );

    $bootstrapDebug = $appDebug;

    $timezone = trim(
        $environment->require(
            'APP_TIMEZONE'
        )
    );

    try {
        new DateTimeZone($timezone);
    } catch (Throwable $exception) {
        throw new RuntimeException(
            'APP_TIMEZONE inválido.',
            0,
            $exception
        );
    }

    date_default_timezone_set(
        $timezone
    );

    $showErrors = (
        $appEnv !== 'production'
        && $appDebug
    );

    ini_set(
        'display_errors',
        $showErrors
            ? '1'
            : '0'
    );

    ini_set(
        'display_startup_errors',
        $showErrors
            ? '1'
            : '0'
    );

    ini_set(
        'log_errors',
        '1'
    );

    /* =====================================================
     * BANCO DE DADOS
     * ===================================================== */

    $port = $parseInteger(
        $environment->require(
            'DB_PORT'
        ),
        'DB_PORT',
        3306,
        1,
        65535
    );

    /* =====================================================
     * INTEGRAÇÃO FISCAL
     * ===================================================== */

    $fiscalIntegrationEnabled = $parseBoolean(
        $environment->get(
            'FISCAL_INTEGRATION_ENABLED',
            'false'
        ),
        'FISCAL_INTEGRATION_ENABLED'
    );

    $fiscalProductionEnabled = $parseBoolean(
        $environment->get(
            'FISCAL_PRODUCTION_ENABLED',
            'false'
        ),
        'FISCAL_PRODUCTION_ENABLED'
    );

    if (
        $fiscalProductionEnabled
        && !$fiscalIntegrationEnabled
    ) {
        throw new RuntimeException(
            'A produção fiscal não pode ser habilitada '
            . 'com a integração fiscal desativada.'
        );
    }

    /* =====================================================
     * SESSÃO E LOGIN
     * ===================================================== */

    $sessionTimeout = max(
        86400,
        $parseInteger(
            $environment->get(
                'SESSION_TIMEOUT',
                '86400'
            ),
            'SESSION_TIMEOUT',
            86400,
            60,
            2592000
        )
    );

    $sessionAbsoluteTimeout = max(
        86400,
        $parseInteger(
            $environment->get(
                'SESSION_ABSOLUTE_TIMEOUT',
                '86400'
            ),
            'SESSION_ABSOLUTE_TIMEOUT',
            86400,
            60,
            2592000
        )
    );

    if (
        $sessionAbsoluteTimeout
        < $sessionTimeout
    ) {
        throw new RuntimeException(
            'SESSION_ABSOLUTE_TIMEOUT não pode ser '
            . 'menor que SESSION_TIMEOUT.'
        );
    }

    $sessionRegenerateInterval = $parseInteger(
        $environment->get(
            'SESSION_REGENERATE_INTERVAL',
            '900'
        ),
        'SESSION_REGENERATE_INTERVAL',
        900,
        60,
        86400
    );

    $loginMaxAttempts = $parseInteger(
        $environment->get(
            'LOGIN_MAX_ATTEMPTS',
            '5'
        ),
        'LOGIN_MAX_ATTEMPTS',
        5,
        1,
        100
    );

    $loginLockMinutes = $parseInteger(
        $environment->get(
            'LOGIN_LOCK_MINUTES',
            '15'
        ),
        'LOGIN_LOCK_MINUTES',
        15,
        1,
        1440
    );

    /* =====================================================
     * INTEGRAÇÃO FLUX EMPRESAS → SO
     * ===================================================== */

    $soIntegrationEnabled = $parseBoolean(
        $environment->get(
            'SO_INTEGRATION_ENABLED',
            'false'
        ),
        'SO_INTEGRATION_ENABLED'
    );

    $soApiVerifyTls = $parseBoolean(
        $environment->get(
            'SO_API_VERIFY_TLS',
            'true'
        ),
        'SO_API_VERIFY_TLS'
    );

    /*
     * Nunca permitir TLS desativado em produção.
     */
    if (
        $appEnv === 'production'
        && !$soApiVerifyTls
    ) {
        throw new RuntimeException(
            'SO_API_VERIFY_TLS deve permanecer habilitado '
            . 'em produção.'
        );
    }

    $soApiConnectTimeout = $parseInteger(
        $environment->get(
            'SO_API_CONNECT_TIMEOUT',
            '5'
        ),
        'SO_API_CONNECT_TIMEOUT',
        5,
        1,
        30
    );

    $soApiTimeout = $parseInteger(
        $environment->get(
            'SO_API_TIMEOUT',
            '15'
        ),
        'SO_API_TIMEOUT',
        15,
        2,
        120
    );

    if (
        $soApiTimeout
        < $soApiConnectTimeout
    ) {
        throw new RuntimeException(
            'SO_API_TIMEOUT não pode ser menor que '
            . 'SO_API_CONNECT_TIMEOUT.'
        );
    }

    $soApiBaseUrl = rtrim(
        trim(
            (string) $environment->get(
                'SO_API_BASE_URL',
                ''
            )
        ),
        '/'
    );

    $soApiAcquisitionPath = trim(
        (string) $environment->get(
            'SO_API_ACQUISITION_PATH',
            ''
        )
    );

    $soApiClientId = trim(
        (string) $environment->get(
            'SO_API_CLIENT_ID',
            ''
        )
    );

    $soApiSecret = strtolower(
        trim(
            (string) $environment->get(
                'SO_API_SECRET',
                ''
            )
        )
    );

    /*
     * Quando a integração estiver habilitada, todas as
     * configurações passam a ser obrigatórias.
     */
    if ($soIntegrationEnabled) {
        if (
            filter_var(
                $soApiBaseUrl,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            throw new RuntimeException(
                'SO_API_BASE_URL inválida.'
            );
        }

        $baseUrlParts = parse_url(
            $soApiBaseUrl
        );

        if (
            !is_array($baseUrlParts)
            || strtolower(
                (string) (
                    $baseUrlParts['scheme']
                    ?? ''
                )
            ) !== 'https'
            || empty($baseUrlParts['host'])
            || isset($baseUrlParts['user'])
            || isset($baseUrlParts['pass'])
            || isset($baseUrlParts['query'])
            || isset($baseUrlParts['fragment'])
        ) {
            throw new RuntimeException(
                'SO_API_BASE_URL deve ser uma URL HTTPS '
                . 'sem credenciais, query ou fragmento.'
            );
        }

        if (
            $soApiAcquisitionPath === ''
            || $soApiAcquisitionPath[0] !== '/'
            || str_contains(
                $soApiAcquisitionPath,
                "\0"
            )
            || str_contains(
                $soApiAcquisitionPath,
                "\r"
            )
            || str_contains(
                $soApiAcquisitionPath,
                "\n"
            )
            || str_contains(
                $soApiAcquisitionPath,
                '..'
            )
            || str_contains(
                $soApiAcquisitionPath,
                '?'
            )
            || str_contains(
                $soApiAcquisitionPath,
                '#'
            )
            || str_contains(
                $soApiAcquisitionPath,
                '://'
            )
        ) {
            throw new RuntimeException(
                'SO_API_ACQUISITION_PATH inválido.'
            );
        }

        if (
            preg_match(
                '/^[A-Za-z0-9_.:-]{3,100}$/D',
                $soApiClientId
            ) !== 1
        ) {
            throw new RuntimeException(
                'SO_API_CLIENT_ID inválido.'
            );
        }

        if (
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $soApiSecret
            ) !== 1
        ) {
            throw new RuntimeException(
                'SO_API_SECRET deve possuir exatamente '
                . '64 caracteres hexadecimais.'
            );
        }
    }

    /* =====================================================
     * CONFIGURAÇÕES DA APLICAÇÃO
     * ===================================================== */

    $settings = [
        'app_env' => $appEnv,

        'app_debug' => $appDebug,

        'app_base_path' => $environment->get(
            'APP_BASE_PATH',
            '/fluxEmpresa'
        ),

        'project_root' => __DIR__,

        /*
         * Fiscal
         */
        'fiscal_integration_enabled'
            => $fiscalIntegrationEnabled,

        'fiscal_production_enabled'
            => $fiscalProductionEnabled,

        /*
         * Sessão
         */
        'session_name' => $environment->get(
            'SESSION_NAME',
            'FLUXEMPRESASESSID'
        ),

        'session_timeout'
            => $sessionTimeout,

        'session_absolute_timeout'
            => $sessionAbsoluteTimeout,

        'session_regenerate_interval'
            => $sessionRegenerateInterval,

        'session_cookie_path' => $environment->get(
            'SESSION_COOKIE_PATH',
            '/fluxEmpresa'
        ),

        /*
         * Login
         */
        'login_max_attempts'
            => $loginMaxAttempts,

        'login_lock_minutes'
            => $loginLockMinutes,

        /*
         * Integração SO.
         *
         * O segredo permanece exclusivamente no backend.
         * Nunca expor este array em JavaScript, sessão, HTML
         * ou resposta de API.
         */
        'so_integration_enabled'
            => $soIntegrationEnabled,

        'so_api_base_url'
            => $soApiBaseUrl,

        'so_api_acquisition_path'
            => $soApiAcquisitionPath,

        'so_api_client_id'
            => $soApiClientId,

        'so_api_secret'
            => $soApiSecret,

        'so_api_connect_timeout'
            => $soApiConnectTimeout,

        'so_api_timeout'
            => $soApiTimeout,

        'so_api_verify_tls'
            => $soApiVerifyTls,
    ];

    /* =====================================================
     * CONEXÃO PRINCIPAL DO FLUX
     * ===================================================== */

    $database = new Database(
        host: $environment->require(
            'DB_HOST'
        ),
        port: $port,
        database: $environment->require(
            'DB_DATABASE'
        ),
        username: $environment->require(
            'DB_USERNAME'
        ),
        password: $environment->require(
            'DB_PASSWORD'
        ),
        charset: $environment->require(
            'DB_CHARSET'
        )
    );

    /* =====================================================
     * MIGRATIONS
     * ===================================================== */

    if (PHP_SAPI === 'cli') {
        $autoMigrate = $parseBoolean(
            $environment->get(
                'DB_AUTO_MIGRATE',
                'false'
            ),
            'DB_AUTO_MIGRATE'
        );

        if ($autoMigrate) {
            (
                new MigrationRunner(
                    $database->connection()
                )
            )->run(
                __DIR__
                . '/database/migrations'
            );
        }
    } else {
        $webMigrations = $parseBoolean(
            $environment->get(
                'DB_WEB_MIGRATIONS',
                'false'
            ),
            'DB_WEB_MIGRATIONS'
        );

        if ($webMigrations) {
            (
                new WebMigrationCoordinator(
                    __DIR__
                    . '/database/migrations',

                    __DIR__
                    . '/storage/cache/web-migration-state.json',

                    __DIR__
                    . '/storage/cache/web-migration.lock',

                    static fn (): bool => (
                        new MigrationRunner(
                            $database->connection()
                        )
                    )->run(
                        __DIR__
                        . '/database/migrations',
                        0
                    )
                )
            )->run();
        }
    }

    /* =====================================================
     * RETORNO DO BOOTSTRAP
     * ===================================================== */

    return [
        'environment' => $environment,

        'settings' => $settings,

        'database' => $database,

        'application' => new Application(
            $database,
            $settings
        ),
    ];
} catch (Throwable $exception) {
    /*
     * Não registrar conteúdo de arquivos .env, payloads,
     * senhas ou segredos da API.
     */
    $logMessage = 'Bootstrap failed.'
        . ' type='
        . get_class($exception)
        . ' code='
        . (string) $exception->getCode();

    if ($bootstrapDebug) {
        $logMessage .= ' message='
            . preg_replace(
                '/[\r\n\t]+/',
                ' ',
                $exception->getMessage()
            );
    } else {
        $logMessage .= ' message_hash='
            . hash(
                'sha256',
                $exception->getMessage()
            );
    }

    error_log(
        $logMessage
    );

    if (PHP_SAPI === 'cli') {
        throw new RuntimeException(
            'Configuração do ambiente inválida.',
            0,
            $exception
        );
    }

    if ($exception instanceof MigrationException) {
        http_response_code(503);

        header(
            'Retry-After: 30'
        );
    } else {
        http_response_code(500);
    }

    exit(
        'Não foi possível inicializar o sistema. '
        . 'Entre em contato com o administrador.'
    );
}