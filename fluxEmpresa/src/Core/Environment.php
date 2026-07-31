<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Environment
{
    private const ALLOWED_KEYS = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_TIMEZONE',
        'APP_BASE_PATH',

        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'DB_CHARSET',
        'DB_AUTO_MIGRATE',
        'DB_WEB_MIGRATIONS',

        'FISCAL_INTEGRATION_ENABLED',
        'FISCAL_PRODUCTION_ENABLED',
        'FISCAL_MASTER_KEY',

        'SESSION_NAME',
        'SESSION_TIMEOUT',
        'SESSION_ABSOLUTE_TIMEOUT',
        'SESSION_REGENERATE_INTERVAL',
        'SESSION_COOKIE_PATH',

        'LOGIN_MAX_ATTEMPTS',
        'LOGIN_LOCK_MINUTES',

        /*
         * Leitura direta e somente leitura do banco do SO.
         */
        'SO_ENV_PATH',

        /*
         * Comunicação HTTPS Flux Empresas → API do SO.
         */
        'SO_INTEGRATION_ENABLED',
        'SO_API_BASE_URL',
        'SO_API_ACQUISITION_PATH',
        'SO_API_CLIENT_ID',
        'SO_API_SECRET',
        'SO_API_CONNECT_TIMEOUT',
        'SO_API_TIMEOUT',
        'SO_API_VERIFY_TLS',
    ];

    public function __construct(
        private readonly string $filePath
    ) {
    }

    /**
     * Resolve o caminho do arquivo .env principal do Flux Empresas.
     *
     * Estrutura recomendada:
     *
     * /home/usuario/
     * ├── configuracoes/
     * │   └── fluxempresa/
     * │       └── .env
     * └── public_html/
     *     └── fluxEmpresa/
     */
    public static function resolveFilePath(
        string $projectRoot
    ): string {
        $configuredPath = getenv(
            'FLUXEMPRESA_ENV_PATH'
        );

        if (
            is_string($configuredPath)
            && trim($configuredPath) !== ''
        ) {
            return self::normalizePath(
                $configuredPath
            );
        }

        $projectRoot = rtrim(
            $projectRoot,
            DIRECTORY_SEPARATOR
        );

        /*
         * Exemplo:
         *
         * /home/usuario/public_html/fluxEmpresa
         *                    ↓
         * /home/usuario
         */
        $accountRoot = dirname(
            $projectRoot,
            2
        );

        return $accountRoot
            . DIRECTORY_SEPARATOR
            . 'configuracoes'
            . DIRECTORY_SEPARATOR
            . 'fluxempresa'
            . DIRECTORY_SEPARATOR
            . '.env';
    }

    /**
     * Carrega somente chaves permitidas.
     *
     * Variáveis definidas diretamente no servidor possuem prioridade
     * sobre o conteúdo do arquivo.
     */
    public function load(): void
    {
        if (
            !is_file($this->filePath)
            || !is_readable($this->filePath)
        ) {
            throw new RuntimeException(
                'Arquivo de ambiente ausente ou inacessível.'
            );
        }

        $lines = file(
            $this->filePath,
            FILE_IGNORE_NEW_LINES
        );

        if ($lines === false) {
            throw new RuntimeException(
                'Não foi possível carregar o arquivo de ambiente.'
            );
        }

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                $line = preg_replace(
                    '/^\xEF\xBB\xBF/',
                    '',
                    (string) $line
                ) ?? (string) $line;
            }

            $line = trim(
                (string) $line
            );

            if (
                $line === ''
                || str_starts_with($line, '#')
                || !str_contains($line, '=')
            ) {
                continue;
            }

            [$key, $value] = explode(
                '=',
                $line,
                2
            );

            $key = trim(
                $key
            );

            if (
                !in_array(
                    $key,
                    self::ALLOWED_KEYS,
                    true
                )
            ) {
                continue;
            }

            /*
             * Variáveis configuradas diretamente no servidor
             * têm prioridade.
             */
            if (getenv($key) !== false) {
                continue;
            }

            $value = $this->normalizeValue(
                $value
            );

            putenv(
                $key . '=' . $value
            );

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function get(
        string $key,
        ?string $default = null
    ): ?string {
        $this->assertAllowedKey(
            $key
        );

        $value = getenv(
            $key
        );

        return $value === false
            ? $default
            : (string) $value;
    }

    public function require(
        string $key
    ): string {
        $value = $this->get(
            $key
        );

        if (
            $value === null
            || trim($value) === ''
        ) {
            throw new RuntimeException(
                'Variável de ambiente obrigatória ausente.'
            );
        }

        return $value;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    private function normalizeValue(
        string $value
    ): string {
        $value = trim(
            $value
        );

        if ($value === '') {
            return '';
        }

        if (str_contains($value, "\0")) {
            throw new RuntimeException(
                'Valor inválido no arquivo de ambiente.'
            );
        }

        $firstCharacter = $value[0];
        $lastCharacter = substr(
            $value,
            -1
        );

        if (
            (
                $firstCharacter === '"'
                || $firstCharacter === "'"
            )
            && $lastCharacter === $firstCharacter
        ) {
            return substr(
                $value,
                1,
                -1
            );
        }

        return $value;
    }

    private function assertAllowedKey(
        string $key
    ): void {
        if (
            !in_array(
                $key,
                self::ALLOWED_KEYS,
                true
            )
        ) {
            throw new RuntimeException(
                'Variável de ambiente não permitida.'
            );
        }
    }

    private static function normalizePath(
        string $path
    ): string {
        $path = trim(
            $path
        );

        if (
            $path === ''
            || str_contains($path, "\0")
        ) {
            throw new RuntimeException(
                'Caminho do arquivo de ambiente inválido.'
            );
        }

        return rtrim(
            $path,
            DIRECTORY_SEPARATOR
        );
    }
}