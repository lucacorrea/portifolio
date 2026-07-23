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
    ];

    public function __construct(
        private readonly string $filePath
    ) {
    }

    /**
     * Resolve o caminho do arquivo .env.
     *
     * Estrutura esperada:
     *
     * /home/usuario/
     * ├── configuracoes/
     * │   └── yk/
     * │       └── .env
     * └── public_html/
     *     └── YK/
     *
     * Quando $projectRoot for:
     * /home/usuario/public_html/YK
     *
     * O resultado será:
     * /home/usuario/configuracoes/yk/.env
     */
    public static function resolveFilePath(string $projectRoot): string
    {
        $configuredPath = getenv('YK_ENV_PATH');

        if (
            is_string($configuredPath)
            && trim($configuredPath) !== ''
        ) {
            return self::normalizePath($configuredPath);
        }

        $projectRoot = rtrim(
            $projectRoot,
            DIRECTORY_SEPARATOR
        );

        /*
         * Sobe dois níveis:
         *
         * /home/usuario/public_html/YK
         *              ↓ 1
         * /home/usuario/public_html
         *              ↓ 2
         * /home/usuario
         */
        $accountRoot = dirname($projectRoot, 2);

        return $accountRoot
            . DIRECTORY_SEPARATOR
            . 'configuracoes'
            . DIRECTORY_SEPARATOR
            . 'yk'
            . DIRECTORY_SEPARATOR
            . '.env';
    }

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

        foreach ($lines as $line) {
            $line = trim($line);

            if (
                $line === ''
                || str_starts_with($line, '#')
            ) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);

            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }

            /*
             * Variáveis configuradas diretamente no servidor
             * têm prioridade sobre o conteúdo do arquivo .env.
             */
            if (getenv($key) !== false) {
                continue;
            }

            $value = $this->normalizeValue($value);

            putenv($key . '=' . $value);

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function get(
        string $key,
        ?string $default = null
    ): ?string {
        $this->assertAllowedKey($key);

        $value = getenv($key);

        return $value === false
            ? $default
            : (string) $value;
    }

    public function require(string $key): string
    {
        $value = $this->get($key);

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

    private function normalizeValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $firstCharacter = $value[0];
        $lastCharacter = substr($value, -1);

        if (
            ($firstCharacter === '"' || $firstCharacter === "'")
            && $lastCharacter === $firstCharacter
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function assertAllowedKey(string $key): void
    {
        if (!in_array($key, self::ALLOWED_KEYS, true)) {
            throw new RuntimeException(
                'Variável de ambiente não permitida.'
            );
        }
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (str_contains($path, "\0")) {
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
