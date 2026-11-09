<?php

declare(strict_types=1);

/**
 * Conexão PDO opcional para scripts PHP independentes do Laravel.
 * Uso: $pdo = require __DIR__ . '/conexao.php';
 * O Laravel continua usando config/database.php e suas dependências.
 */

$envPath = __DIR__ . '/.env';
$dotenv = [];

if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        if (!in_array($key, [
            'DB_CONNECTION', 'DB_HOST', 'DB_PORT',
            'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        ], true)) {
            continue;
        }

        $value = trim(substr($line, $separator + 1));
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        // Preserva caracteres especiais como $, +, # e = em senhas.
        $dotenv[$key] = $value;
    }
}

$env = static function (string $name, string $default = '') use ($dotenv): string {
    $systemValue = getenv($name);
    if ($systemValue !== false) {
        return (string) $systemValue;
    }
    if (isset($_ENV[$name])) {
        return (string) $_ENV[$name];
    }
    return $dotenv[$name] ?? $default;
};

if ($env('DB_CONNECTION', 'mysql') !== 'mysql') {
    throw new RuntimeException('conexao.php aceita apenas DB_CONNECTION=mysql.');
}

$host = $env('DB_HOST', '127.0.0.1');
$port = $env('DB_PORT', '3306');
$database = $env('DB_DATABASE');
$username = $env('DB_USERNAME');
$password = $env('DB_PASSWORD');

if ($host === '' || $database === '' || $username === '' ||
    !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('Configuração MySQL inválida. Confira as variáveis DB_* do .env.');
}

if (!extension_loaded('pdo_mysql')) {
    throw new RuntimeException('A extensão PHP pdo_mysql não está habilitada.');
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    return $pdo;
} catch (PDOException $exception) {
    error_log('[PCCT] Falha de conexão PDO: código ' . $exception->getCode());
    throw new RuntimeException('Não foi possível conectar ao banco MySQL. Confira o .env.', 0, $exception);
}
