<?php
/**
 * OBRAS360 — conexão central de testes SEM .env.
 * Todos os módulos continuam usando db(), que delega a obras_conectar().
 * Os segredos ficam em um arquivo PHP privado no SERVIDOR:
 *   <diretorio-do-dominio>/obras360-private/conexao.php
 * Nunca comite esse arquivo privado no GitHub.
 */
declare(strict_types=1);

function obras_arquivo_conexao(): ?string {
    $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    // No deploy /public_html/obras/ => a pasta privada é irmã de public_html.
    $files = [
        dirname(dirname($root)) . '/obras360-private/conexao.php',
        // Desenvolvimento local ou instalações existentes: também NÃO versionar.
        $root . '/config/conexao.local.php',
    ];
    foreach ($files as $file) {
        if (is_file($file) && is_readable($file)) return $file;
    }
    return null;
}

/** @return array<string, string> */
function obras_configuracao_conexao(): array {
    static $config = null;
    if (is_array($config)) return $config;
    $config = [];
    $file = obras_arquivo_conexao();
    if ($file === null) return $config;
    try {
        $data = (static function (string $privateFile) {
            return require $privateFile;
        })($file);
        if (!is_array($data)) {
            error_log('[OBRAS360] Configuração PHP da conexão inválida.');
            return $config;
        }
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/^[A-Z][A-Z0-9_]*$/D', $key)
                && (is_string($value) || is_int($value))) {
                $config[$key] = (string)$value;
            }
        }
    } catch (Throwable $e) {
        error_log('[OBRAS360] Não foi possível ler a configuração privada de conexão.');
    }
    return $config;
}

function obras_valor_conexao(string $key, string $fallback = ''): string {
    $config = obras_configuracao_conexao();
    return isset($config[$key]) && $config[$key] !== '' ? $config[$key] : $fallback;
}

function obras_conectar(): PDO {
    static $connection = null;
    if ($connection instanceof PDO) return $connection;

    $config = obras_configuracao_conexao();
    $host = $config['DB_HOST'] ?? 'srv1885.hstgr.io';
    $port = (int)($config['DB_PORT'] ?? 3306);
    $name = $config['DB_NAME'] ?? '';
    $user = $config['DB_USER'] ?? '';
    $password = $config['DB_PASS'] ?? '';
    $salt = $config['APP_AUDIT_SALT'] ?? '';
    if ($name === '' || $user === '' || $password === '' ||
        str_contains($password, 'COLE_SENHA') ||
        !preg_match('/^[a-f0-9]{64}$/iD', $salt) ||
        $port < 1 || $port > 65535 ||
        !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.\-]*$/D', $host)) {
        throw new RuntimeException('Configuração do banco de dados incompleta.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Driver pdo_mysql não instalado.');
    }
    $connection = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
    $connection->exec("SET time_zone = '-04:00'");
    return $connection;
}
