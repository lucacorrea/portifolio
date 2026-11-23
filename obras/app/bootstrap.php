<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

// Fase de testes: um único conector PHP substitui temporariamente o .env.
// Alteração futura para .env exigirá apenas trocar a origem em app/conexao.php.
require_once __DIR__ . '/conexao.php';
function env_value(string $key, string $fallback = ''): string {
    return obras_valor_conexao($key, $fallback);
}
function request_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}
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
/** Conexão única para login, usuários, mapas, cadastros, estoque e API. */
function db(): PDO {
    return obras_conectar();
}
/** Identificadores técnicos de auditoria; não há campo extra para configurar. */
function audit_salt(): string {
    return hash('sha256', 'obras360-audit|' . env_value('DB_PASS'));
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
    $st->execute([$u['id'] ?? null, $event, $module, $code, hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'cli') . audit_salt()), $payload]);
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
