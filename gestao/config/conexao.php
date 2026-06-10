<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

if (!defined('DB_HOST')) {
    define('DB_HOST', (string) env_first(['DB_HOST'], 'localhost'));
    define('DB_PORT', (int) env_first(['DB_PORT'], 3306));
    define('DB_DATABASE', (string) env_first(['DB_DATABASE', 'DB_NAME'], ''));
    define('DB_USERNAME', (string) env_first(['DB_USERNAME', 'DB_USER'], ''));
    define('DB_PASSWORD', (string) env_first(['DB_PASSWORD', 'DB_PASS'], ''));
    define('DB_CHARSET', (string) env_first(['DB_CHARSET'], 'utf8mb4'));

    define('DB_NAME', DB_DATABASE);
    define('DB_USER', DB_USERNAME);
    define('DB_PASS', DB_PASSWORD);
}

if (!function_exists('gestao_pdo')) {
    function gestao_pdo(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        if (DB_DATABASE === '' || DB_USERNAME === '') {
            throw new RuntimeException('Configuração do banco de dados incompleta. Verifique DB_DATABASE/DB_USERNAME ou DB_NAME/DB_USER no .env.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_DATABASE,
            DB_CHARSET
        );

        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }
}
