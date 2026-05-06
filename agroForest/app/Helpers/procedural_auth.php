<?php

function app_log_path(): string
{
    return BASE_PATH . '/storage/logs/app.log';
}

function app_log_init(): void
{
    $directory = dirname(app_log_path());

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    if (is_dir($directory) && is_writable($directory) && !is_file(app_log_path())) {
        @file_put_contents(app_log_path(), '');
    }
}

function app_log_write(string $level, string $message, ?Throwable $exception = null): void
{
    app_log_init();

    $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), strtoupper($level), $message);

    if ($exception instanceof Throwable) {
        $line .= sprintf(
            ' | %s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
    }

    if (@file_put_contents(app_log_path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        error_log($line);
    }
}

function db_config(): array
{
    $config = require APP_PATH . '/Config/database.php';

    return [
        'driver' => $config['driver'] ?? 'mysql',
        'host' => trim((string) ($config['host'] ?? 'localhost')),
        'port' => trim((string) ($config['port'] ?? '3306')),
        'database' => trim((string) ($config['database'] ?? '')),
        'username' => trim((string) ($config['username'] ?? '')),
        'password' => (string) ($config['password'] ?? ''),
        'charset' => trim((string) ($config['charset'] ?? 'utf8mb4')),
    ];
}

function db_safe_context(?array $config = null): string
{
    $config ??= db_config();

    return sprintf(
        'driver=%s host=%s port=%s database=%s username=%s pdo_mysql=%s',
        $config['driver'],
        $config['host'],
        $config['port'],
        $config['database'],
        $config['username'],
        extension_loaded('pdo_mysql') ? 'loaded' : 'missing'
    );
}

function db_dsn(array $config): string
{
    return sprintf(
        '%s:host=%s;port=%s;dbname=%s;charset=%s',
        $config['driver'],
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );
}

function db_connect_with_config(array $config): PDO
{
    return new PDO(db_dsn($config), $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function db_fallback_configs(array $config): array
{
    if ($config['host'] === 'localhost') {
        return [array_replace($config, ['host' => '127.0.0.1'])];
    }

    if ($config['host'] === '127.0.0.1') {
        return [array_replace($config, ['host' => 'localhost'])];
    }

    return [];
}

function db(): PDO
{
    static $pdo = null;
    static $activeConfig = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = db_config();
    $activeConfig = $config;

    try {
        $pdo = db_connect_with_config($config);
        return $pdo;
    } catch (PDOException $exception) {
        foreach (db_fallback_configs($config) as $fallbackConfig) {
            try {
                $pdo = db_connect_with_config($fallbackConfig);
                $activeConfig = $fallbackConfig;
                app_log_write('info', 'PDO connected using fallback: ' . db_safe_context($fallbackConfig));
                return $pdo;
            } catch (PDOException $fallbackException) {
                app_log_write('error', 'PDO fallback failed: ' . db_safe_context($fallbackConfig), $fallbackException);
            }
        }

        app_log_write('error', 'PDO connection failed: ' . db_safe_context($activeConfig), $exception);
        throw $exception;
    }
}

function csrf_token_value(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['_csrf'];
}

function csrf_token_valid(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);
}

function auth_check(): bool
{
    return !empty($_SESSION['user']);
}

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function auth_cargo_por_nivel(string $nivel): string
{
    return match ($nivel) {
        'dono' => 'Dono',
        'administrativo' => 'Administrativo',
        default => 'Recepção',
    };
}

function auth_home_por_nivel(string $nivel): string
{
    return match ($nivel) {
        'dono' => route_url('dono', 'dashboard'),
        'administrativo' => route_url('administrativo', 'dashboard'),
        default => route_url('recepcao', 'dashboard'),
    };
}

function auth_login_session(array $usuario): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $usuario['id'],
        'nome' => (string) $usuario['nome'],
        'email' => (string) $usuario['email'],
        'nivel' => (string) $usuario['nivel'],
        'cargo' => auth_cargo_por_nivel((string) $usuario['nivel']),
    ];
}

function auth_logout_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function auth_required(): void
{
    if (!auth_check()) {
        flash_set('error', 'Faça login para acessar o sistema.');
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }
}

function role_required(string $area): void
{
    auth_required();

    $permissoes = require APP_PATH . '/Config/permissions.php';
    $usuario = auth_user() ?? [];
    $nivel = $usuario['nivel'] ?? '';
    $perfisPermitidos = $permissoes[$area] ?? [];

    if (in_array($nivel, $perfisPermitidos, true)) {
        return;
    }

    http_response_code(403);
    require APP_PATH . '/Views/errors/403.php';
    exit;
}

function router_resolve(string $area, string $pagina): ?string
{
    $map = require BASE_PATH . '/routes/web.php';

    return $map[$area][$pagina] ?? null;
}

function somente_digitos(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function db_column_exists(string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = db()->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
    $stmt->execute([$column]);

    $cache[$key] = (bool) $stmt->fetch();

    return $cache[$key];
}

function usuario_buscar_por_identificacao(string $identificacao): ?array
{
    $identificacao = trim($identificacao);
    $email = strtolower($identificacao);
    $cpf = somente_digitos($identificacao);

    if (db_column_exists('usuarios', 'cpf')) {
        $stmt = db()->prepare(
            "SELECT id, nome, email, cpf, senha, nivel, ativo
             FROM usuarios
             WHERE email = ?
                OR nome = ?
                OR cpf = ?
                OR REPLACE(REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', ''), ' ', '') = ?
             ORDER BY id ASC
             LIMIT 1"
        );
        $stmt->execute([$email, $identificacao, $identificacao, $cpf]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    $stmt = db()->prepare(
        'SELECT id, nome, email, senha, nivel, ativo
         FROM usuarios
         WHERE email = ? OR nome = ?
         ORDER BY id ASC
         LIMIT 1'
    );
    $stmt->execute([$email, $identificacao]);

    $usuario = $stmt->fetch();

    return $usuario ?: null;
}

function usuario_registrar_ultimo_login(int $id): void
{
    try {
        $stmt = db()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '42S22') {
            throw $exception;
        }
    }
}

function senha_confere(string $senha, string $hash): bool
{
    if (strncmp($hash, 'pbkdf2_sha256$', 14) === 0) {
        return senha_pbkdf2_confere($senha, $hash);
    }

    return password_verify($senha, $hash);
}

function senha_pbkdf2_confere(string $senha, string $hash): bool
{
    $partes = explode('$', $hash);

    if (count($partes) !== 4 || $partes[0] !== 'pbkdf2_sha256') {
        return false;
    }

    [, $iteracoes, $salt, $hashEsperado] = $partes;
    $iteracoes = filter_var($iteracoes, FILTER_VALIDATE_INT, ['options' => ['min_range' => 10000]]);

    if (!$iteracoes || $salt === '' || $hashEsperado === '') {
        return false;
    }

    $hashCalculado = hash_pbkdf2('sha256', $senha, $salt, $iteracoes, 0);

    return hash_equals($hashEsperado, $hashCalculado);
}

function mensagem_erro_banco(PDOException $exception): string
{
    $driverCode = (int) ($exception->errorInfo[1] ?? 0);
    $message = $exception->getMessage();

    if (str_contains($message, 'could not find driver')) {
        return 'A extensão pdo_mysql do PHP não está habilitada no servidor.';
    }

    if (str_contains($message, 'Invalid parameter number') || $exception->getCode() === 'HY093') {
        return 'Erro interno na consulta de login. O sistema registrou os detalhes em storage/logs/app.log.';
    }

    if ($driverCode === 1045 || $driverCode === 1044 || str_contains($message, 'Access denied')) {
        return 'Usuário ou senha do banco MySQL estão incorretos.';
    }

    if ($driverCode === 1049 || str_contains($message, 'Unknown database')) {
        return 'O banco MySQL configurado não existe ou não está vinculado ao site.';
    }

    if (
        in_array($driverCode, [2002, 2003, 2005], true)
        || str_contains($message, 'No such file')
        || str_contains($message, 'Connection refused')
        || str_contains($message, 'php_network_getaddresses')
    ) {
        return 'O host do MySQL não respondeu. Confira se o host é localhost e a porta é 3306.';
    }

    if (str_contains($message, 'Base table or view not found') || str_contains($message, "doesn't exist")) {
        return 'A tabela usuarios não existe. Rode o installAuth.php ou o SQL de criação no phpMyAdmin.';
    }

    if (str_contains($message, 'Unknown column')) {
        return 'A tabela usuarios existe, mas está com colunas faltando. Rode o SQL de atualização.';
    }

    return 'Falha no banco MySQL: ' . $message . '. Verifique o arquivo storage/logs/app.log.';
}

function login_exibir(): void
{
    if (auth_check()) {
        header('Location: ' . auth_home_por_nivel((string) auth_user()['nivel']));
        exit;
    }

    render_view('auth/login');
}

function login_processar(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    if (!csrf_token_valid($_POST['_csrf'] ?? '')) {
        flash_set('error', 'Sessão expirada. Tente novamente.');
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    $identificacao = trim((string) ($_POST['identificacao'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($identificacao === '' || $senha === '') {
        flash_set('error', 'Informe nome, e-mail ou CPF e senha.');
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    try {
        $usuario = usuario_buscar_por_identificacao($identificacao);
    } catch (PDOException $exception) {
        app_log_write('error', 'Login database failed: ' . db_safe_context(), $exception);
        flash_set('error', mensagem_erro_banco($exception));
        header('Location: ' . route_url('auth', 'login'));
        exit;
    } catch (Throwable $exception) {
        app_log_write('error', 'Login query failed: ' . db_safe_context(), $exception);
        flash_set('error', 'Erro interno no login. Verifique o arquivo storage/logs/app.log.');
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    if (!$usuario) {
        flash_set('error', 'Nome, e-mail, CPF ou senha inválidos.');
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    if ((int) ($usuario['ativo'] ?? 0) !== 1) {
        flash_set('error', 'Usuário inativo. Solicite a liberação do acesso.');
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    if (!senha_confere($senha, (string) $usuario['senha'])) {
        app_log_write('info', 'Login denied for user id=' . (int) $usuario['id']);
        flash_set('error', 'Nome, e-mail, CPF ou senha inválidos.');
        header('Location: ' . route_url('auth', 'login'));
        exit;
    }

    auth_login_session($usuario);

    try {
        usuario_registrar_ultimo_login((int) $usuario['id']);
    } catch (Throwable $exception) {
        app_log_write('error', 'Failed to update ultimo_login for user id=' . (int) $usuario['id'], $exception);
    }

    header('Location: ' . auth_home_por_nivel((string) $usuario['nivel']));
    exit;
}
