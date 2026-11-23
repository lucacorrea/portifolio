<?php
/**
 * OBRAS360 - CONEXÃO SIMPLES PARA DEMONSTRAÇÃO.
 * Tudo (login, cadastros, mapas, API) chama db() -> obras_conectar().
 * O arquivo de senha fica fora da área pública e fora do GitHub.
 * Não requer .env, DB_HOST, DB_PORT ou APP_AUDIT_SALT na configuração.
 */
declare(strict_types=1);

function obras_connection_candidates(): array {
    $root = dirname(__DIR__);
    return [
        dirname(dirname($root)) . '/obras360-private/conexao.php',
        $root . '/config/conexao.local.php',
    ];
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
        // require executado em escopo isolado; não exibe conteúdo da configuração.
        $data = (static function (string $path): mixed { return require $path; })($file);
        if (!is_array($data)) throw new UnexpectedValueException('Tipo de configuração inválido.');
        $values = [];
        foreach (['DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
                throw new UnexpectedValueException('Configuração do banco de dados incompleta.');
            }
            $values[$key] = $data[$key];
        }
        // Estas opções só servem para execução do site, não são obrigatórias.
        foreach (['APP_TIMEZONE', 'APP_FORCE_HTTPS'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) $values[$key] = $data[$key];
        }
        $cached = $values;
        return $values;
    } catch (Throwable $e) {
        error_log('[OBRAS360 CONFIG] Configuração PHP privada inválida.');
        return [];
    }
}

function obras_valor_conexao(string $name, string $fallback = ''): string {
    $config = obras_configuracao_conexao();
    return $config[$name] ?? $fallback;
}

/** Abre PDO sem precisar selecionar servidor no config; Hostinger usa 3306. */
function obras_conectar_com_dados(string $name, string $user, string $password): PDO {
    if (!extension_loaded('pdo_mysql')) throw new RuntimeException('Driver pdo_mysql não instalado.');
    if ($name === '' || $user === '' || $password === '') {
        throw new RuntimeException('Configuração do banco de dados incompleta.');
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => 5,
    ];
    $last = null;
    // MySQL da própria hospedagem normalmente responde em localhost.
    // Em algumas instalações Hostinger, o painel indica srv1885.hstgr.io.
    // Ambos são internos ao conector, não são campos para o usuário configurar.
    foreach (['localhost', 'srv1885.hstgr.io'] as $host) {
        try {
            $pdo = new PDO(
                'mysql:host=' . $host . ';port=3306;dbname=' . $name . ';charset=utf8mb4',
                $user, $password, $options
            );
            $pdo->exec("SET time_zone = '-04:00'");
            return $pdo;
        } catch (PDOException $e) {
            $last = $e;
            $driver = (int)($e->errorInfo[1] ?? 0);
            // Não tenta o segundo host para erros que não são de conexão.
            if (!in_array($driver, [2002, 2003, 2005, 2006, 2013], true)) throw $e;
        }
    }
    if ($last instanceof PDOException) throw $last;
    throw new RuntimeException('Não foi possível conectar ao banco.');
}

function obras_conectar(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = obras_configuracao_conexao();
    $name = $config['DB_NAME'] ?? '';
    $user = $config['DB_USER'] ?? '';
    $password = $config['DB_PASS'] ?? '';
    if ($name === '' || $user === '' || $password === '' || str_starts_with($password, 'COLE_')) {
        throw new RuntimeException('Configuração do banco de dados incompleta.');
    }
    $pdo = obras_conectar_com_dados($name, $user, $password);
    return $pdo;
}

/** Grava credenciais recebidas via instalador no PHP privado, nunca no repositório. */
function obras_gravar_conexao_privada(array $config, bool $replaceExisting = false): string {
    foreach (obras_connection_candidates() as $file) {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) continue;
        if (!is_writable($dir) || (file_exists($file) && !$replaceExisting)) continue;
        $data = [
            'DB_NAME' => (string)($config['DB_NAME'] ?? ''),
            'DB_USER' => (string)($config['DB_USER'] ?? ''),
            'DB_PASS' => (string)($config['DB_PASS'] ?? ''),
        ];
        if (in_array('', $data, true)) throw new RuntimeException('Configuração do banco de dados incompleta.');
        $content = "<?php\n/** Configuração privada do OBRAS360. NÃO COMITAR. */\nreturn " . var_export($data, true) . ";\n";
        $tmp = @tempnam($dir, '.obras_');
        if ($tmp === false) continue;
        @chmod($tmp, 0600);
        try {
            if (file_put_contents($tmp, $content, LOCK_EX) === false) continue;
            if (!@rename($tmp, $file)) continue;
            @chmod($file, 0600);
            clearstatcache(true, $file);
            return $file;
        } finally {
            if (is_file($tmp)) @unlink($tmp);
        }
    }
    throw new RuntimeException('Não foi possível gravar a conexão privada; use o arquivo preparado para a Hostinger.');
}
