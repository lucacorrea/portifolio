<?php
require_once __DIR__ . '/config.php';

function db_required_env(string $key): string
{
    $value = trim((string) env_value($key, ''));

    if ($value === '') {
        throw new RuntimeException("Variável obrigatória ausente no .env: {$key}");
    }

    return $value;
}

function db_connection_config(): array
{
    $connection = strtolower((string) env_value('DB_CONNECTION', 'mysql'));
    if ($connection !== 'mysql') {
        throw new RuntimeException('DB_CONNECTION deve ser mysql.');
    }

    $port = (int) env_value('DB_PORT', '3306');
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('DB_PORT inválida.');
    }

    $charset = strtolower((string) env_value('DB_CHARSET', 'utf8mb4'));
    if (!in_array($charset, ['utf8mb4', 'utf8'], true)) {
        throw new RuntimeException('DB_CHARSET inválido.');
    }

    $password = (string) env_value('DB_PASSWORD', '');
    if ($password === '' && !env_bool('DB_ALLOW_EMPTY_PASSWORD', false)) {
        throw new RuntimeException('DB_PASSWORD não pode ficar vazio em produção.');
    }

    return [
        'host' => db_required_env('DB_HOST'),
        'port' => $port,
        'socket' => trim((string) env_value('DB_SOCKET', '')),
        'database' => db_required_env('DB_DATABASE'),
        'username' => db_required_env('DB_USERNAME'),
        'password' => $password,
        'charset' => $charset,
        'timeout' => max(1, min(30, (int) env_value('DB_TIMEOUT', '5'))),
    ];
}

function db_open_connection(): PDO
{
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('A extensão pdo_mysql não está ativa no servidor.');
    }

    $config = db_connection_config();
    $dsn = $config['socket'] !== ''
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $config['socket'], $config['database'], $config['charset'])
        : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => $config['timeout'],
    ]);

    try {
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (Throwable $error) {
        error_log('[ArteFlor][database-session] ' . $error->getMessage());
    }

    $pdo->query('SELECT 1');

    return $pdo;
}

function db_bootstrap_failure(Throwable $error): void
{
    error_log('[ArteFlor][database] ' . $error->getMessage());

    if (app_is_cli()) {
        throw new RuntimeException('Falha ao conectar ao banco de dados. Verifique o .env e o MySQL.', 0, $error);
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, private');
    }

    $debug = app_debug();
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sistema indisponível | <?= SITE_NAME ?></title>
  <style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Arial, sans-serif; background: #f7f1e8; color: #244836; }
    main { width: min(560px, calc(100% - 32px)); padding: 28px; border: 1px solid rgba(47,72,58,.14); border-radius: 14px; background: #fffdf8; box-shadow: 0 16px 40px rgba(45,55,48,.08); }
    h1 { margin: 0 0 10px; font-size: 1.45rem; }
    p { margin: 0; line-height: 1.55; color: #56625a; }
    code { display: block; margin-top: 16px; padding: 12px; border-radius: 10px; background: #f3ece2; white-space: pre-wrap; overflow-wrap: anywhere; color: #244836; }
  </style>
</head>
<body>
  <main>
    <h1>Sistema temporariamente indisponível</h1>
    <p>Não foi possível abrir a conexão segura com o banco de dados. A equipe técnica deve verificar as variáveis do servidor e o serviço MySQL.</p>
    <?php if ($debug): ?>
      <code><?= htmlspecialchars(get_class($error) . ': ' . $error->getMessage(), ENT_QUOTES, 'UTF-8') ?></code>
    <?php endif; ?>
  </main>
</body>
</html>
    <?php
    exit;
}

function db_bootstrap(): PDO
{
    try {
        return db_open_connection();
    } catch (Throwable $error) {
        db_bootstrap_failure($error);
    }
}

function db(): PDO
{
    return $GLOBALS['arteflor_db'];
}

function db_is_connected(): bool
{
    return db()->query('SELECT 1') !== false;
}

$GLOBALS['arteflor_db'] = db_bootstrap();
