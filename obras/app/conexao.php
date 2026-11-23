<?php
/**
 * OBRAS360 - ÚNICO ponto de conexão MySQL.
 * Fase atual: conexao.php privado, SEM .env.
 * O login, as APIs, os relatórios e todos os módulos chamam db(), em bootstrap.php.
 */
declare(strict_types=1);

function obras_connection_candidates(): array {
    $root = dirname(__DIR__);
    $private = dirname(dirname($root)) . '/obras360-private/conexao.php';
    $local = $root . '/config/conexao.local.php';
    return [$private, $local];
}

function obras_arquivo_conexao(): ?string {
    foreach (obras_connection_candidates() as $file) {
        if (is_file($file) && is_readable($file)) return $file;
    }
    return null;
}

/** @return array<string,string> */
function obras_configuracao_conexao(): array {
    static $cached = null;
    if (is_array($cached)) return $cached;
    $file = obras_arquivo_conexao();
    if ($file === null) return [];
    try {
        $data = (static function (string $name): mixed { return require $name; })($file);
        if (!is_array($data)) throw new UnexpectedValueException('Tipo de configuração inválido.');
        $required = ['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'];
        foreach ($required as $key) {
            if (!isset($data[$key]) || !is_scalar($data[$key]) || trim((string)$data[$key]) === '') {
                throw new UnexpectedValueException('Configuração do banco de dados incompleta.');
            }
        }
        $values = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/^[A-Z][A-Z0-9_]*$/D', $key)
                && (is_string($value) || is_int($value))) $values[$key] = (string)$value;
        }
        $cached = $values;
        return $values;
    } catch (Throwable $e) {
        error_log('[OBRAS360 CONFIG] Arquivo de conexão privada inválido: ' . get_class($e));
        return [];
    }
}

function obras_valor_conexao(string $name, string $fallback = ''): string {
    $c = obras_configuracao_conexao();
    return $c[$name] ?? $fallback;
}

/**
 * Separa falhas de configuração de problemas de rede ou credenciais.
 * O sal de auditoria NÃO interfere na abertura de conexão MySQL.
 */
function obras_conectar(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = obras_configuracao_conexao();
    $host = $c['DB_HOST'] ?? '';
    $name = $c['DB_NAME'] ?? '';
    $user = $c['DB_USER'] ?? '';
    $pass = $c['DB_PASS'] ?? '';
    $port = filter_var($c['DB_PORT'] ?? '3306', FILTER_VALIDATE_INT);
    if (!$host || !$name || !$user || !$pass || $port === false || $port < 1 || $port > 65535
        || str_contains($pass, 'COLE_') || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*$/D', $host)) {
        throw new RuntimeException('Configuração do banco de dados incompleta.');
    }
    if (!extension_loaded('pdo_mysql')) throw new RuntimeException('Driver pdo_mysql não instalado.');
    $pdo = new PDO('mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
        $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    $pdo->exec("SET time_zone = '-04:00'");
    return $pdo;
}

/**
 * Gera arquivo com credenciais somente no servidor de destino.
 * Chamado apenas pelo instalador depois de autenticar com sucesso no MySQL.
 * Retorna o caminho onde as credenciais privadas foram instaladas.
 */
function obras_gravar_conexao_privada(array $config, bool $replaceExisting = false): string {
    $paths = obras_connection_candidates();
    $body = "<?php\n/** ARQUIVO PRIVADO GERADO PELO INSTALADOR. NÃO VERSIONAR NO GIT. */\nreturn "
        . var_export($config, true) . ";\n";
    foreach ($paths as $file) {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) continue;
        if (!is_writable($dir) || (file_exists($file) && !$replaceExisting)) continue;
        $tmp = @tempnam($dir, '.obras_');
        if ($tmp === false) continue;
        @chmod($tmp, 0600);
        try {
            if (file_put_contents($tmp, $body, LOCK_EX) === false) continue;
            if (!@rename($tmp, $file)) continue;
            @chmod($file, 0600);
            // Atualizar cache de stat() após instalar.
            clearstatcache(true, $file);
            return $file;
        } finally {
            if (is_file($tmp)) @unlink($tmp);
        }
    }
    throw new RuntimeException('Não foi possível gravar o arquivo privado da conexão na hospedagem.');
}
