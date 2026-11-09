<?php

declare(strict_types=1);

require_once __DIR__ . '/SigasEnvironment.php';
require_once __DIR__ . '/SigasDatabaseConfig.php';
require_once __DIR__ . '/SigasDatabase.php';

class SigasConnection
{
    private static $pdo = null;
    private static $attempted = false;
    private static $lastError = '';
    private static $configPath = '';

    public static function connection()
    {
        if (self::$attempted) {
            return self::$pdo;
        }

        self::$attempted = true;

        try {
            $path = SigasEnvironment::locate();

            if ($path === null) {
                self::$lastError =
                    'Arquivo privado da integração SIGAS não foi localizado.';
                return null;
            }

            self::$configPath = $path;

            $environment = SigasEnvironment::load($path);
            $config = SigasDatabaseConfig::fromEnvironment($environment);

            if (!$config->enabled()) {
                self::$lastError =
                    'Integração SIGAS está desabilitada no arquivo privado.';
                return null;
            }

            $database = new SigasDatabase($config);
            self::$pdo = $database->connection();
            self::$lastError = '';

            return self::$pdo;
        } catch (Throwable $e) {
            self::$pdo = null;

            @error_log(
                '[SEMAS][SIGAS] Integração indisponível: '
                . get_class($e)
                . ' | '
                . $e->getMessage()
            );

            /*
             * Classificação segura para a interface.
             * Não mostra usuário, senha ou DSN.
             */
            $previous = $e->getPrevious();

            if ($previous instanceof PDOException) {
                $code = (string)$previous->getCode();

                if ($code === '1045' || $code === '28000') {
                    self::$lastError =
                        'O banco recusou o usuário/senha da integração SIGAS.';
                } elseif ($code === '1044') {
                    self::$lastError =
                        'O usuário da integração não possui acesso ao banco SIGAS.';
                } elseif ($code === '1049') {
                    self::$lastError =
                        'O banco SIGAS configurado não foi encontrado.';
                } elseif ($code === '2002') {
                    self::$lastError =
                        'Não foi possível alcançar o servidor MySQL do SIGAS.';
                } else {
                    self::$lastError =
                        'Não foi possível abrir a conexão com o banco SIGAS.';
                }
            } else {
                self::$lastError = $e->getMessage();
            }

            return null;
        }
    }

    public static function available()
    {
        return self::connection() instanceof PDO;
    }

    public static function lastError()
    {
        return self::$lastError;
    }

    public static function configPath()
    {
        return self::$configPath;
    }
}
