<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

/**
 * Configuração privada para deploy via GitHub no caminho /obras/public/.
 * Se o projeto está em public_html/obras, procura primeiro o arquivo em
 * <dominio>/obras360-private/config/.env, fora de public_html.
 * Também aceita OBRAS360_ENV_FILE absoluto e variáveis do servidor.
 */
function obras_env_file_path(): ?string {
    $custom = getenv('OBRAS360_ENV_FILE');
    $root = realpath(APP_ROOT) ?: dirname(__DIR__);
    $candidates = [];
    if (is_string($custom) && $custom !== '') $candidates[] = $custom;
    $candidates[] = dirname(dirname($root)) . '/obras360-private/config/.env';
    // Compatibilidade com instalações antigas; a pasta config é protegida por .htaccess.
    $candidates[] = $root . '/config/.env';
    foreach ($candidates as $file) {
        if (is_file($file) && is_readable($file)) return $file;
    }
    return null;
}
function load_environment(): void {
    $file = obras_env_file_path();
    if ($file === null) return;
    $vars = parse_ini_file($file, false, INI_SCANNER_RAW);
    if (!is_array($vars)) {
        error_log('[OBRAS360 CONFIG] Arquivo .env inválido ou ilegível.');
        return;
    }
    foreach ($vars as $key => $value) {
        if (is_string($key) && preg_match('/^[A-Z][A-Z0-9_]*$/', $key)
            && is_scalar($value) && getenv($key) === false && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = (string)$value;
        }
    }
}
function env_value(string $key, string $fallback = ''): string {
    $v = getenv($key);
    if (is_string($v) && $v !== '') return $v;
    $v = $_ENV[$key] ?? null;
    return is_string($v) && $v !== '' ? $v : $fallback;
}
function request_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}
load_environment();
date_default_timezone_set(env_value('APP_TIMEZONE', 'America/Manaus'));
if (PHP_SAPI !== 'cli' && env_value('APP_FORCE_HTTPS', '1') === '1' && !request_https()) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i', $host)) {
        http_response_code(400); exit('Host inválido.');
    }
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 308); exit;
}
$OBRAS_CSP_NONCE = base64_encode(random_bytes(18));
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    if (request_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    // Leaflet e OpenStreetMap: recursos externos de mapa, sem acesso aos dados de autenticação.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$OBRAS_CSP_NONCE}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; img-src 'self' data: blob: https://cdn.jsdelivr.net https://*.tile.openstreetmap.org; connect-src 'self'; font-src 'self' data: https://fonts.gstatic.com; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'");
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name('obras360_sid');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => request_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = env_value('DB_HOST', 'localhost');
    $port = (int)env_value('DB_PORT', '3306');
    $name = env_value('DB_NAME');
    $user = env_value('DB_USER');
    $pass = env_value('DB_PASS');
    if ($name === '' || $user === '') throw new RuntimeException('Configuração do banco de dados incompleta.');
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ]);
    $pdo->exec("SET time_zone = '-04:00'");
    return $pdo;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        throw new HttpError('Sessão expirada ou token de segurança inválido. Recarregue a página.', 419);
    }
}
class HttpError extends RuntimeException {
    public function __construct(string $message, public int $httpCode = 400) { parent::__construct($message); }
}
function user(): ?array {
    static $cached = null;
    if (!empty($_SESSION['user_id']) && ((time() - (int)($_SESSION['auth_at'] ?? 0) > 43200) || (time() - (int)($_SESSION['last_activity'] ?? time()) > 1800))) {
        $_SESSION = []; return null;
    }
    if (!empty($_SESSION['user_id'])) $_SESSION['last_activity'] = time();
    if (empty($_SESSION['user_id'])) return null;
    if ($cached !== null && (int)$cached['id'] === (int)$_SESSION['user_id']) return $cached;
    $st = db()->prepare('SELECT id, username, email, name, role, active, must_change_password, password_hash FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$_SESSION['user_id']]);
    $row = $st->fetch();
    if (!$row || (int)$row['active'] !== 1) {
        unset($_SESSION['user_id']); return null;
    }
    $cached = $row;
    return $row;
}
function require_login(bool $api = false): array {
    $u = user();
    if (!$u) {
        if ($api) throw new HttpError('Faça login para continuar.', 401);
        header('Location: acesso.php', true, 303); exit;
    }
    if ((int)$u['must_change_password'] === 1 && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'minha-senha.php') {
        if ($api) throw new HttpError('Altere a senha inicial para continuar.', 403);
        header('Location: minha-senha.php', true, 303); exit;
    }
    return $u;
}
function can_write(array $u): bool { return in_array($u['role'], ['suporte', 'administrador', 'gestor', 'operador'], true); }
function can_manage_users(array $u): bool { return in_array($u['role'], ['suporte', 'administrador'], true); }
function require_write(array $u): void { if (!can_write($u)) throw new HttpError('Acesso somente de leitura.', 403); }
function require_admin(array $u): void { if (!can_manage_users($u)) throw new HttpError('Acesso restrito à administração.', 403); }
function post_string(string $key, int $max = 1000): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) throw new HttpError('Campo inválido: ' . $key);
    $value = trim($value);
    if (strlen($value) > $max * 4) throw new HttpError('Campo muito longo: ' . $key);
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) throw new HttpError('Caracteres inválidos: ' . $key);
    return $value;
}
function audit_log(string $event, ?string $module = null, ?string $code = null, array $meta = []): void {
    $u = user();
    $payload = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $st = db()->prepare('INSERT INTO audit_logs (actor_id, event, module, record_code, ip_hash, metadata) VALUES (?,?,?,?,?,?)');
    $st->execute([$u['id'] ?? null, $event, $module, $code, hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'cli') . env_value('APP_AUDIT_SALT')), $payload]);
}
function public_user(array $u): array {
    return ['id' => (int)$u['id'], 'username' => $u['username'], 'email' => $u['email'], 'name' => $u['name'], 'role' => $u['role'], 'must_change_password' => (bool)$u['must_change_password']];
}
function json_reply(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}
function redirect_to(string $where): never { header('Location: ' . $where, true, 303); exit; }
